import Fastify, { type FastifyInstance } from 'fastify';
import formbody from '@fastify/formbody';
import websocket from '@fastify/websocket';
import cors from '@fastify/cors';
import { pathToFileURL } from 'node:url';
import { config } from './config.js';
import { dbEnabled, ensureSchema } from './db/index.js';
import { llmEnabled, llmProvider } from './ai/llm.js';
import { registerTwilioRoutes } from './twilio/routes.js';
import { registerConversationWs } from './ws/conversation.js';
import { registerApiRoutes } from './api/routes.js';
import { registerAuthRoutes } from './auth/routes.js';
import { registerLeadRoutes } from './leads/routes.js';
import { processDueEmails } from './leads/repo.js';
import { runWeeklyDigests } from './notify/digest.js';

// アプリ本体を組み立てて返す（listen はしない）。テストから inject で利用する。
export async function buildApp(opts: { logger?: boolean } = {}): Promise<FastifyInstance> {
  const app = Fastify({ logger: opts.logger ?? true });

  // 起動時マイグレーション（既存DBへの列追加を冪等に適用）。DB未接続なら何もしない。
  await ensureSchema();

  // 本文なしのPOST（通知再送など）で Content-Type: application/json が付いても
  // エラーにせず空オブジェクトとして扱う（FST_ERR_CTP_EMPTY_JSON_BODY 回避）。
  app.addContentTypeParser('application/json', { parseAs: 'string' }, (_req, body, done) => {
    const s = (body as string).trim();
    if (!s) return done(null, {});
    try { done(null, JSON.parse(s)); } catch (err) { done(err as Error); }
  });

  // 管理画面フロントからのアクセスを許可（CORS）。
  await app.register(cors, { origin: config.corsOrigin });
  // Twilio Webhook は application/x-www-form-urlencoded。
  await app.register(formbody);
  await app.register(websocket);

  app.get('/health', async () => ({
    ok: true,
    db: dbEnabled ? 'connected' : 'disabled (demo mode)',
    llm: llmEnabled ? 'enabled' : 'disabled (fallback mode)',
    llm_provider: llmProvider,
  }));

  // Claude＋日程調整の疎通確認（安全版・?key=diag2026）。
  // 秘密情報は一切返さない：成功可否・ツール呼び出し有無・返答文のみ。
  app.get('/api/debug/scheduling', async (req) => {
    if ((req.query as any)?.key !== 'diag2026') return { ok: false, error: 'forbidden' };
    // 返す文字列から秘密っぽい値を除去（保険）。
    const scrub = (s: string) => s.replace(/sk-ant-[A-Za-z0-9_-]+/g, '***').slice(0, 200);
    const { chatAgentStream } = await import('./ai/llm.js');
    const { schedulingTools, makeSchedulingRunner } = await import('./ai/schedulingTools.js');
    const tenantId = config.demoTenantId;
    const out: Record<string, unknown> = { llm_provider: llmProvider };
    try {
      let toolCalled: string | null = null;
      const base = makeSchedulingRunner(tenantId, null);
      const runner = async (name: string, input: any) => { toolCalled = name; return base(name, input); };
      const text = await chatAgentStream(
        [
          { role: 'system', content: '受付テスト。find_available_slots ツールで明日以降の空きを調べ、最初の候補を一言で述べよ。' },
          { role: 'user', content: '出張査定の空きを教えて' },
        ],
        schedulingTools, runner, () => {}, 300,
      );
      out.agent = { ok: Boolean(text), tool_called: toolCalled, reply: text ? scrub(text) : null };
    } catch (e) { out.agent = { ok: false, error: scrub(String(e)) }; }
    return out;
  });

  await registerTwilioRoutes(app);
  await registerConversationWs(app);
  await registerAuthRoutes(app);
  await registerApiRoutes(app);
  await registerLeadRoutes(app);

  return app;
}

async function main() {
  const app = await buildApp();

  // ステップメール worker（予定時刻を過ぎたメールを定期送信）。
  if (config.leadWorkerIntervalSec > 0) {
    const tick = () => processDueEmails()
      .then((n) => { if (n > 0) app.log.info(`[lead-worker] sent ${n} emails`); })
      .catch((err) => app.log.error({ err }, 'lead-worker failed'));
    setInterval(tick, config.leadWorkerIntervalSec * 1000).unref();
  }

  // 週次サマリー自動送信（opt-in）。毎時チェックし、月曜の指定時刻(UTC)に送る。
  if (config.weeklyDigestEnabled) {
    const checkWeekly = () => {
      const now = new Date();
      if (now.getUTCDay() === 1 && now.getUTCHours() === config.weeklyDigestHourUtc) {
        runWeeklyDigests((m) => app.log.info(`[weekly-digest] ${m}`))
          .catch((err) => app.log.error({ err }, 'weekly-digest failed'));
      }
    };
    setInterval(checkWeekly, 60 * 60 * 1000).unref();
    app.log.info('[weekly-digest] enabled');
  }

  try {
    await app.listen({ port: config.port, host: '0.0.0.0' });
    app.log.info(
      `AIオペレーター24 backend listening on :${config.port} ` +
      `(db=${dbEnabled ? 'on' : 'off'}, llm=${llmEnabled ? 'on' : 'off'})`,
    );
  } catch (err) {
    app.log.error(err);
    process.exit(1);
  }
}

// 直接実行時のみ起動（テストで import した場合は起動しない）。
const isEntry = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isEntry) main();
