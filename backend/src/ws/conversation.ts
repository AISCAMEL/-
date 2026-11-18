import type { FastifyInstance } from 'fastify';
import type { WebSocket } from '@fastify/websocket';
import { config } from '../config.js';
import { ConversationOrchestrator } from '../ai/orchestrator.js';
import { putSession, getSession } from '../twilio/sessionStore.js';
import { resolveTenantByPhone, createCall, saveTranscriptLine } from '../db/index.js';
import { getTenantAiContext } from '../db/queries.js';
import { getCampaignById, getTargetByPhone } from '../outbound/repo.js';
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
    notificationEmail: null, mailFrom: null, slackWebhookUrl: null,
    notifyOnCallEnd: true, notifyOnCallback: true, notifyOnTransfer: true,
    fallbackMessage: null,
    aiInstructions: null, receptionTypes: [], salesCallReply: null, onlineBookingUrl: null,
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
            // 相手（架電先=to）を特定し、入金案内なら金額・期日を文脈に追加。
            const target = await getTargetByPhone(campaignId, to).catch(() => null);
            let goal = campaign?.goal_prompt ?? '';
            if (target) {
              const bits: string[] = [];
              if (target.name) bits.push(`相手のお名前: ${target.name}`);
              if (target.company) bits.push(`会社: ${target.company}`);
              if (target.amount != null) bits.push(`未収金額: ${target.amount}円`);
              if (target.due_date) bits.push(`お支払期日: ${target.due_date}`);
              if (bits.length) goal += `\n# 今回の相手の情報\n${bits.join('、')}`;
            }
            outbound = { purpose: campaign?.purpose ?? 'sales', goal };
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

          // 返答をストリーミング生成：トークンが届くたびに即発話（合間を最小化）。
          // Conversation Relay は last:false のtextを順次受け取り、届いた分から読み上げる。
          const result = await session.orchestrator.handleUserUtteranceStream(
            m.voicePrompt,
            (delta) => send(socket, { type: 'text', token: delta, last: false }),
          );
          // 生成完了を通知（これで読み上げが確定する）。
          send(socket, { type: 'text', token: '', last: true });

          // 文字起こしは発話を止めないよう、await せずに永続化（遅延の原因にしない）。
          if (session.callId) {
            const cid = session.callId, tid = session.tenantId;
            void saveTranscriptLine(cid, tid, { speaker: 'customer', message: m.voicePrompt }, sequence++).catch(() => {});
            void saveTranscriptLine(cid, tid, { speaker: 'ai', message: result.reply }, sequence++).catch(() => {});
          }

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
