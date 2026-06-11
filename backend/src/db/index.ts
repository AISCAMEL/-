import pg from 'pg';
import { config } from '../config.js';
import type { TenantContext, TranscriptLine, CallSummary } from '../types.js';

// DATABASE_URL 未設定時は「DBなしデモモード」で動作（ローカル/PoC 用）。
const enabled = Boolean(config.databaseUrl);
const pool = enabled ? new pg.Pool({ connectionString: config.databaseUrl }) : null;

export const dbEnabled = enabled;

export async function query<T = any>(text: string, params: unknown[] = []): Promise<T[]> {
  if (!pool) return [];
  const res = await pool.query(text, params);
  return res.rows as T[];
}

/** 着信先番号(E.164)からテナントを解決し、設定・FAQをまとめて返す。 */
export async function resolveTenantByPhone(toNumber: string): Promise<TenantContext | null> {
  if (!pool) return null;
  const rows = await query<any>(
    `select t.id as tenant_id, t.company_name, t.industry,
            s.greeting_message, s.ai_tone, s.business_hours, s.holiday_settings,
            s.human_transfer_enabled, s.transfer_phone_number, s.fallback_message,
            s.notification_email
       from phone_numbers p
       join tenants t        on t.id = p.tenant_id
       left join tenant_settings s on s.tenant_id = t.id
      where p.phone_number = $1 and p.status = 'active'
      limit 1`,
    [toNumber],
  );
  if (rows.length === 0) return null;
  const r = rows[0];
  const faqs = await query<any>(
    `select question, answer, category from faqs
      where tenant_id = $1 and is_active = true order by created_at`,
    [r.tenant_id],
  );
  return {
    tenantId: r.tenant_id,
    companyName: r.company_name,
    industry: r.industry,
    greetingMessage: r.greeting_message ?? config.defaultGreeting,
    aiTone: r.ai_tone ?? 'polite',
    businessHours: r.business_hours ?? {},
    holidaySettings: r.holiday_settings ?? {},
    humanTransferEnabled: r.human_transfer_enabled ?? true,
    transferPhoneNumber: r.transfer_phone_number,
    notificationEmail: r.notification_email,
    fallbackMessage: r.fallback_message,
    faqs,
  };
}

/** 着信時に call レコードを作成し id を返す。 */
export async function createCall(input: {
  tenantId: string;
  callSid: string;
  sessionId: string | null;
  from: string;
  to: string;
}): Promise<string | null> {
  if (!pool) return null;
  const rows = await query<{ id: string }>(
    `insert into calls (tenant_id, twilio_call_sid, twilio_session_id, from_number, to_number, status, started_at)
     values ($1,$2,$3,$4,$5,'in_progress', now())
     on conflict (twilio_call_sid) do update set twilio_session_id = excluded.twilio_session_id
     returning id`,
    [input.tenantId, input.callSid, input.sessionId, input.from, input.to],
  );
  return rows[0]?.id ?? null;
}

export async function saveTranscriptLine(
  callId: string,
  tenantId: string,
  line: TranscriptLine,
  sequence: number,
): Promise<void> {
  if (!pool) return;
  await query(
    `insert into transcripts (call_id, tenant_id, speaker, message, sequence)
     values ($1,$2,$3,$4,$5)
     on conflict (call_id, sequence) do nothing`,
    [callId, tenantId, line.speaker, line.message, sequence],
  );
}

export async function getTranscript(callSid: string): Promise<{
  callId: string; tenantId: string; lines: TranscriptLine[];
} | null> {
  if (!pool) return null;
  const call = await query<{ id: string; tenant_id: string }>(
    `select id, tenant_id from calls where twilio_call_sid = $1`,
    [callSid],
  );
  if (call.length === 0) return null;
  const lines = await query<TranscriptLine>(
    `select speaker, message from transcripts where call_id = $1 order by sequence`,
    [call[0].id],
  );
  return { callId: call[0].id, tenantId: call[0].tenant_id, lines };
}

/** 通話終了時: 要約・分類・所要時間を calls に書き戻す。 */
export async function finalizeCall(
  callId: string, tenantId: string, summary: CallSummary, durationSec: number,
): Promise<void> {
  if (!pool) return;
  await query(
    `update calls set
        status = case when $3 then 'callback_requested' else 'completed' end,
        category = $4, summary = $5, customer_name = $6, company_name = $7,
        requested_datetime = $8, request_detail = $9, next_action = $10,
        urgency = $11, sentiment = $12, duration_sec = $13, ended_at = now()
      where id = $1 and tenant_id = $2`,
    [
      callId, tenantId, summary.callback_requested, summary.category,
      summary.summary, summary.customer_name, summary.company_name,
      summary.requested_datetime, summary.request_detail, summary.next_action,
      summary.urgency, summary.sentiment, durationSec,
    ],
  );
}

export async function recordNotification(input: {
  tenantId: string; callId: string | null; type: string; destination: string | null;
  status: 'pending' | 'sent' | 'failed'; subject: string | null; payload: unknown; error?: string | null;
}): Promise<void> {
  if (!pool) return;
  await query(
    `insert into notifications (tenant_id, call_id, type, destination, status, subject, payload, error_message, sent_at)
     values ($1,$2,$3,$4,$5,$6,$7,$8, case when $5='sent' then now() else null end)`,
    [input.tenantId, input.callId, input.type, input.destination, input.status,
     input.subject, JSON.stringify(input.payload ?? {}), input.error ?? null],
  );
}

/** 利用量・原価を usage_records に記録（課金・原価モニタリングの台帳）。 */
export async function recordUsage(input: {
  tenantId: string; callId: string | null; usageType: string;
  quantity: number; unit: string; costAmount: number; currency?: string; metadata?: unknown;
}): Promise<void> {
  if (!pool) return;
  await query(
    `insert into usage_records (tenant_id, call_id, usage_type, quantity, unit, cost_amount, currency, metadata)
     values ($1,$2,$3,$4,$5,$6,$7,$8)`,
    [input.tenantId, input.callId, input.usageType, input.quantity, input.unit,
     input.costAmount, input.currency ?? 'JPY', JSON.stringify(input.metadata ?? {})],
  );
}
