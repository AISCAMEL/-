import type { FastifyInstance } from 'fastify';
import type { WebSocket } from '@fastify/websocket';
import { config } from '../config.js';
import { ConversationOrchestrator } from '../ai/orchestrator.js';
import { putSession, getSession } from '../twilio/sessionStore.js';
import { resolveTenantByPhone, createCall, saveTranscriptLine } from '../db/index.js';
import { getTenantAiContext } from '../db/queries.js';
import { getCampaignById } from '../outbound/repo.js';
import type { TenantContext } from '../types.js';

// Twilio Conversation Relay から届くメッセージ種別（主要なもの）。
interface SetupMsg { type: 'setup'; callSid: string; sessionId?: string; from?: string; to?: string; customParameters?: Record<string, string>; }
interface PromptMsg { type: 'prompt'; voicePrompt: string; last?: boolean; }
interface InterruptMsg { type: 'interrupt'; }
type IncomingMsg = SetupMsg | PromptMsg | InterruptMsg | { type: string; [k: string]: unknown };

// DBが無い/テナント未解決時のデモ用フォールバックコンテキスト。
function demoContext(): TenantContext {
  return {
    tenantId: 'demo', companyName: 'AIオペレーター24 デモ', industry: null,
    greetingMessage: config.defaultGreeting, aiTone: 'polite',
    businessHours: {}, holidaySettings: {},
    humanTransferEnabled: true, transferPhoneNumber: '+810000000000',
    notificationEmail: null, slackWebhookUrl: null,
    notifyOnCallEnd: true, notifyOnCallback: true, notifyOnTransfer: true,
    fallbackMessage: null,
    faqs: [
      { question: '営業時間を教えてください', answer: '営業時間は平日10時から18時までです。土日祝日はお休みです。', category: '営業案内' },
    ],
  };
}

export async function registerConversationWs(app: FastifyInstance): Promise<void> {
  app.get('/ws/conversation', { websocket: true }, (socket: WebSocket) => {
    // 1接続 = 1通話。setup を受けて状態を確定する。
    let callSid = '';
    let sequence = 0;

    socket.on('message', async (data: Buffer) => {
      let msg: IncomingMsg;
      try {
        msg = JSON.parse(data.toString());
      } catch {
        return;
      }

      switch (msg.type) {
        case 'setup': {
          const m = msg as SetupMsg;
          callSid = m.callSid;
          const to = m.to ?? '';
          const from = m.from ?? '';

          // アウトバウンド架電なら campaignId が渡る → 発信側プロンプトに切替。
          const campaignId = m.customParameters?.campaignId;
          let tenant: TenantContext;
          let outbound: { purpose: string; goal: string } | undefined;
          if (campaignId) {
            const campaign = await getCampaignById(campaignId).catch(() => null);
            const tId = campaign?.tenant_id ?? config.demoTenantId;
            tenant = await getTenantAiContext(tId).catch(() => demoContext());
            outbound = { purpose: campaign?.purpose ?? 'sales', goal: campaign?.goal_prompt ?? '' };
          } else {
            tenant = (await resolveTenantByPhone(to).catch(() => null)) ?? demoContext();
          }
          // セッションには DB 上の tenant_id を保持（デモは seed の固定 UUID にマップ）。
          const tenantId = dbTenant(tenant.tenantId);
          const callId = await createCall({
            tenantId,
            callSid: m.callSid,
            sessionId: m.sessionId ?? null,
            from, to,
          }).catch(() => null);

          putSession({
            callSid: m.callSid,
            sessionId: m.sessionId ?? null,
            tenantId,
            callId,
            from, to,
            startedAt: Date.now(),
            orchestrator: new ConversationOrchestrator(tenant, outbound),
          });
          app.log.info({ callSid, from, to, tenant: tenant.companyName, outbound: Boolean(outbound) }, 'ws setup');
          break;
        }

        case 'prompt': {
          const m = msg as PromptMsg;
          if (!m.voicePrompt) break;
          const session = getSession(callSid);
          if (!session) break;

          const result = await session.orchestrator.handleUserUtterance(m.voicePrompt);

          // 文字起こしを永続化（customer 発話 → AI 応答の2行）。
          if (session.callId) {
            await saveTranscriptLine(session.callId, session.tenantId, { speaker: 'customer', message: m.voicePrompt }, sequence++).catch(() => {});
            await saveTranscriptLine(session.callId, session.tenantId, { speaker: 'ai', message: result.reply }, sequence++).catch(() => {});
          }

          // AI 応答を発話（Conversation Relay の text メッセージ）。
          send(socket, { type: 'text', token: result.reply, last: true });

          // 転送指示があれば end して TwiML 側の転送に委ねる、または通話終了。
          if (result.should_transfer || result.should_end_call) {
            send(socket, { type: 'end' });
          }
          break;
        }

        case 'interrupt':
          // ユーザの割り込み。MVPでは特別処理なし（次の prompt を待つ）。
          break;

        default:
          break;
      }
    });

    socket.on('close', () => {
      app.log.info({ callSid }, 'ws closed');
    });
  });
}

function send(socket: WebSocket, payload: unknown): void {
  try {
    socket.send(JSON.stringify(payload));
  } catch {
    /* socket closed */
  }
}

// デモテナントは固定 UUID（seed.sql）にマップして DB 書き込みを成立させる。
function dbTenant(tenantId: string): string {
  return tenantId === 'demo' ? '00000000-0000-0000-0000-000000000001' : tenantId;
}
