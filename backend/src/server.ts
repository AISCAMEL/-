import Fastify, { type FastifyInstance } from 'fastify';
import formbody from '@fastify/formbody';
import websocket from '@fastify/websocket';
import cors from '@fastify/cors';
import { pathToFileURL } from 'node:url';
import { config } from './config.js';
import { dbEnabled } from './db/index.js';
import { llmEnabled } from './ai/llm.js';
import { registerTwilioRoutes } from './twilio/routes.js';
import { registerConversationWs } from './ws/conversation.js';
import { registerApiRoutes } from './api/routes.js';
import { registerLeadRoutes } from './leads/routes.js';
import { processDueEmails } from './leads/repo.js';

// アプリ本体を組み立てて返す（listen はしない）。テストから inject で利用する。
export async function buildApp(opts: { logger?: boolean } = {}): Promise<FastifyInstance> {
  const app = Fastify({ logger: opts.logger ?? true });

  // 管理画面フロントからのアクセスを許可（CORS）。
  await app.register(cors, { origin: config.corsOrigin });
  // Twilio Webhook は application/x-www-form-urlencoded。
  await app.register(formbody);
  await app.register(websocket);

  app.get('/health', async () => ({
    ok: true,
    db: dbEnabled ? 'connected' : 'disabled (demo mode)',
    llm: llmEnabled ? 'enabled' : 'disabled (fallback mode)',
  }));

  await registerTwilioRoutes(app);
  await registerConversationWs(app);
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
