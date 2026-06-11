import type { FastifyInstance } from 'fastify';
import { config } from '../config.js';
import { verifyTwilioSignature } from './signature.js';
import { getSession, deleteSession } from './sessionStore.js';
import { resolveTenantByPhone, finalizeCall, recordNotification, recordUsage } from '../db/index.js';
import { summarizeCall } from '../ai/summarize.js';
import { sendCallNotification } from '../notify/email.js';
import { billableMinutes, aiCostJpy, transferAddCostJpy } from '../billing/rates.js';

// TwiML 生成（ConversationRelay へ接続）。
function buildConnectTwiml(greeting: string): string {
  const wsUrl = `${config.publicWsBaseUrl}/ws/conversation`;
  const action = `${config.publicApiBaseUrl}/api/twilio/connect-ended`;
  // welcomeGreeting は属性値なので XML エスケープする。
  const greet = escapeXml(greeting);
  return `<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Connect action="${action}">
    <ConversationRelay url="${wsUrl}" welcomeGreeting="${greet}" language="ja-JP" interruptible="any" />
  </Connect>
</Response>`;
}

function escapeXml(s: string): string {
  return s.replace(/[<>&"']/g, (c) =>
    ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&apos;' }[c] as string));
}

export async function registerTwilioRoutes(app: FastifyInstance): Promise<void> {
  // 着信。テナント別の挨拶文で ConversationRelay を開始する。
  app.post('/api/twilio/incoming-call', async (req, reply) => {
    const body = (req.body ?? {}) as Record<string, string>;
    if (!verifyTwilioSignature(req, body)) {
      return reply.code(403).send('invalid signature');
    }

    const to = body.To ?? '';
    const tenant = await resolveTenantByPhone(to).catch(() => null);
    const greeting = tenant?.greetingMessage ?? config.defaultGreeting;

    reply.header('Content-Type', 'text/xml');
    return reply.send(buildConnectTwiml(greeting));
  });

  // 通話ステータスコールバック（記録用。MVPはログのみ）。
  app.post('/api/twilio/call-status', async (req, reply) => {
    const body = (req.body ?? {}) as Record<string, string>;
    if (!verifyTwilioSignature(req, body)) return reply.code(403).send('invalid signature');
    app.log.info({ callSid: body.CallSid, status: body.CallStatus }, 'call-status');
    return reply.send('ok');
  });

  // <Connect> 終了後。要約生成→DB保存→通知 をここでキックする。
  app.post('/api/twilio/connect-ended', async (req, reply) => {
    const body = (req.body ?? {}) as Record<string, string>;
    if (!verifyTwilioSignature(req, body)) return reply.code(403).send('invalid signature');

    const callSid = body.CallSid ?? '';
    // 非同期で後処理（応答はすぐ返して Twilio をブロックしない）。
    finalizeAndNotify(app, callSid).catch((err) =>
      app.log.error({ err, callSid }, 'finalizeAndNotify failed'));

    reply.header('Content-Type', 'text/xml');
    return reply.send('<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>');
  });
}

// 通話後処理: 要約→calls更新→メール通知→通知ログ。
async function finalizeAndNotify(app: FastifyInstance, callSid: string): Promise<void> {
  const session = getSession(callSid);
  if (!session) {
    app.log.warn({ callSid }, 'no session for connect-ended');
    return;
  }

  const lines = session.orchestrator.transcript;
  const durationSec = Math.round((Date.now() - session.startedAt) / 1000);
  const summary = await summarizeCall(lines);

  const last = session.orchestrator.latest;
  const statusLabel = last?.should_transfer
    ? '担当者転送'
    : summary.callback_requested
      ? '折り返し希望'
      : 'AI対応完了';

  if (session.callId) {
    await finalizeCall(session.callId, session.tenantId, summary, durationSec).catch((err) =>
      app.log.error({ err }, 'finalizeCall failed'));

    // 利用量・原価を台帳(usage_records)に記録。
    const minutes = billableMinutes(durationSec);
    if (minutes > 0) {
      await recordUsage({
        tenantId: session.tenantId, callId: session.callId, usageType: 'ai_minutes',
        quantity: minutes, unit: 'minute', costAmount: aiCostJpy(minutes),
        metadata: { duration_sec: durationSec },
      }).catch((err) => app.log.error({ err }, 'recordUsage(ai) failed'));

      if (last?.should_transfer) {
        await recordUsage({
          tenantId: session.tenantId, callId: session.callId, usageType: 'transfer_minutes',
          quantity: minutes, unit: 'minute', costAmount: transferAddCostJpy(minutes),
          metadata: { note: 'transfer outbound leg (estimated, mobile)' },
        }).catch((err) => app.log.error({ err }, 'recordUsage(transfer) failed'));
      }
    }
  }

  // 通知先メール（テナント設定）。着信先番号からテナント設定を引く。
  const tenant = await resolveTenantByPhone(session.to).catch(() => null);
  const dest = tenant?.notificationEmail ?? 'owner@example.com';

  const result = await sendCallNotification(dest, {
    fromNumber: session.from,
    summary,
    statusLabel,
  });

  await recordNotification({
    tenantId: session.tenantId,
    callId: session.callId,
    type: 'email',
    destination: dest,
    status: result.ok ? 'sent' : 'failed',
    subject: '【AIオペレーター24】新しい電話受付がありました',
    payload: summary,
    error: result.error ?? null,
  }).catch((err) => app.log.error({ err }, 'recordNotification failed'));

  deleteSession(callSid);
}
