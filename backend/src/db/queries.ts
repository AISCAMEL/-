// 管理画面API用のデータアクセス層。
// DB接続時は PostgreSQL、未接続(デモモード)時はインメモリ fixtures を操作する。
import { dbEnabled, query } from './index.js';
import { summarizeCall } from '../ai/summarize.js';
import {
  demoCalls, demoFaqs, demoSettings, demoPhoneNumbers, demoTenant, newId,
  type DemoCall, type DemoFaq,
} from '../demo/fixtures.js';
import type { CallSummary } from '../types.js';

export interface CallListFilter {
  status?: string;
  category?: string;
  q?: string;
  limit?: number;
}

// ---------------- Dashboard ----------------
export async function getDashboard(tenantId: string) {
  if (!dbEnabled) {
    const today = demoCalls; // デモは全件を当日扱い
    const recent = [...today].sort((a, b) => b.started_at.localeCompare(a.started_at)).slice(0, 10);
    return {
      calls_today: today.length,
      calls_this_month: today.length,
      completed_count: today.filter((c) => c.status === 'completed').length,
      callback_count: today.filter((c) => c.status === 'callback_requested').length,
      transfer_count: today.filter((c) => c.status === 'transferred').length,
      unhandled_count: today.filter((c) => ['new', 'need_human'].includes(c.status)).length,
      avg_duration_sec: Math.round(today.reduce((s, c) => s + (c.duration_sec ?? 0), 0) / (today.length || 1)),
      recent: recent.map(toCallListItem),
    };
  }
  const [agg] = await query<any>(
    `select
       count(*) filter (where started_at >= date_trunc('day', now()))   as calls_today,
       count(*) filter (where started_at >= date_trunc('month', now())) as calls_this_month,
       count(*) filter (where status = 'completed')          as completed_count,
       count(*) filter (where status = 'callback_requested') as callback_count,
       count(*) filter (where status = 'transferred')        as transfer_count,
       count(*) filter (where status in ('new','need_human'))as unhandled_count,
       coalesce(avg(duration_sec) filter (where started_at >= date_trunc('day', now())),0)::int as avg_duration_sec
     from calls where tenant_id = $1`,
    [tenantId],
  );
  const recent = await query<any>(
    `select id, from_number, customer_name, company_name, category, status, summary, started_at, duration_sec
       from calls where tenant_id = $1 order by started_at desc nulls last limit 10`,
    [tenantId],
  );
  return { ...agg, recent };
}

// ---------------- Calls ----------------
export async function listCalls(tenantId: string, f: CallListFilter) {
  if (!dbEnabled) {
    let rows = demoCalls.filter((c) => c.tenant_id === tenantId || tenantId === demoTenant.id);
    if (f.status) rows = rows.filter((c) => c.status === f.status);
    if (f.category) rows = rows.filter((c) => c.category === f.category);
    if (f.q) {
      const q = f.q;
      rows = rows.filter((c) =>
        [c.customer_name, c.company_name, c.summary, c.from_number].some((v) => v?.includes(q)));
    }
    return rows
      .sort((a, b) => b.started_at.localeCompare(a.started_at))
      .slice(0, f.limit ?? 50)
      .map(toCallListItem);
  }
  const where: string[] = ['tenant_id = $1'];
  const params: unknown[] = [tenantId];
  if (f.status) { params.push(f.status); where.push(`status = $${params.length}`); }
  if (f.category) { params.push(f.category); where.push(`category = $${params.length}`); }
  if (f.q) { params.push(`%${f.q}%`); where.push(`(customer_name ilike $${params.length} or company_name ilike $${params.length} or summary ilike $${params.length} or from_number ilike $${params.length})`); }
  params.push(f.limit ?? 50);
  return query<any>(
    `select id, from_number, customer_name, company_name, category, status, summary, started_at, duration_sec
       from calls where ${where.join(' and ')} order by started_at desc nulls last limit $${params.length}`,
    params,
  );
}

export async function getCall(tenantId: string, callId: string) {
  if (!dbEnabled) {
    const c = demoCalls.find((x) => x.id === callId);
    if (!c) return null;
    return {
      ...stripDemoCall(c),
      transcripts: c.transcripts,
      notes: c.notes,
    };
  }
  const [call] = await query<any>(`select * from calls where id = $1 and tenant_id = $2`, [callId, tenantId]);
  if (!call) return null;
  const transcripts = await query<any>(
    `select speaker, message, sequence from transcripts where call_id = $1 order by sequence`, [callId]);
  const notes = await query<any>(
    `select id, note, created_at from call_notes where call_id = $1 order by created_at`, [callId]);
  return { ...call, transcripts, notes };
}

export async function updateCallStatus(tenantId: string, callId: string, status: string) {
  if (!dbEnabled) {
    const c = demoCalls.find((x) => x.id === callId);
    if (!c) return null;
    c.status = status;
    return stripDemoCall(c);
  }
  const [row] = await query<any>(
    `update calls set status = $3 where id = $1 and tenant_id = $2 returning id, status`,
    [callId, tenantId, status]);
  return row ?? null;
}

export async function addCallNote(tenantId: string, callId: string, userId: string | null, note: string) {
  if (!dbEnabled) {
    const c = demoCalls.find((x) => x.id === callId);
    if (!c) return null;
    const n = { id: newId('note'), note, created_at: new Date().toISOString() };
    c.notes.push(n);
    return n;
  }
  const [row] = await query<any>(
    `insert into call_notes (call_id, tenant_id, user_id, note) values ($1,$2,$3,$4)
     returning id, note, created_at`,
    [callId, tenantId, userId, note]);
  return row ?? null;
}

export async function resummarizeCall(tenantId: string, callId: string): Promise<CallSummary | null> {
  if (!dbEnabled) {
    const c = demoCalls.find((x) => x.id === callId);
    if (!c) return null;
    const summary = await summarizeCall(c.transcripts.map((t) => ({ speaker: t.speaker as any, message: t.message })));
    c.summary = summary.summary; c.category = summary.category; c.next_action = summary.next_action;
    return summary;
  }
  const detail = await getCall(tenantId, callId);
  if (!detail) return null;
  const summary = await summarizeCall(detail.transcripts.map((t: any) => ({ speaker: t.speaker, message: t.message })));
  await query(
    `update calls set summary=$3, category=$4, next_action=$5, urgency=$6, sentiment=$7 where id=$1 and tenant_id=$2`,
    [callId, tenantId, summary.summary, summary.category, summary.next_action, summary.urgency, summary.sentiment]);
  return summary;
}

// ---------------- FAQ ----------------
export async function listFaqs(tenantId: string) {
  if (!dbEnabled) return demoFaqs.filter((f) => f.tenant_id === tenantId || tenantId === demoTenant.id);
  return query<any>(`select * from faqs where tenant_id = $1 order by created_at`, [tenantId]);
}

export async function createFaq(tenantId: string, input: Partial<DemoFaq>) {
  if (!dbEnabled) {
    const f: DemoFaq = {
      id: newId('faq'), tenant_id: tenantId, question: input.question ?? '', answer: input.answer ?? '',
      category: input.category ?? null, keywords: input.keywords ?? [], is_active: input.is_active ?? true,
      created_at: new Date().toISOString(), updated_at: new Date().toISOString(),
    };
    demoFaqs.push(f);
    return f;
  }
  const [row] = await query<any>(
    `insert into faqs (tenant_id, question, answer, category, keywords, is_active)
     values ($1,$2,$3,$4,$5,$6) returning *`,
    [tenantId, input.question, input.answer, input.category ?? null, input.keywords ?? [], input.is_active ?? true]);
  return row;
}

export async function updateFaq(tenantId: string, faqId: string, input: Partial<DemoFaq>) {
  if (!dbEnabled) {
    const f = demoFaqs.find((x) => x.id === faqId);
    if (!f) return null;
    Object.assign(f, {
      question: input.question ?? f.question, answer: input.answer ?? f.answer,
      category: input.category ?? f.category, keywords: input.keywords ?? f.keywords,
      is_active: input.is_active ?? f.is_active, updated_at: new Date().toISOString(),
    });
    return f;
  }
  const [row] = await query<any>(
    `update faqs set question=coalesce($3,question), answer=coalesce($4,answer),
        category=$5, keywords=coalesce($6,keywords), is_active=coalesce($7,is_active)
      where id=$1 and tenant_id=$2 returning *`,
    [faqId, tenantId, input.question, input.answer, input.category ?? null, input.keywords, input.is_active]);
  return row ?? null;
}

export async function deleteFaq(tenantId: string, faqId: string) {
  if (!dbEnabled) {
    const i = demoFaqs.findIndex((x) => x.id === faqId);
    if (i === -1) return false;
    demoFaqs.splice(i, 1);
    return true;
  }
  const rows = await query(`delete from faqs where id=$1 and tenant_id=$2 returning id`, [faqId, tenantId]);
  return rows.length > 0;
}

// ---------------- Settings ----------------
export async function getSettings(tenantId: string) {
  if (!dbEnabled) return demoSettings;
  const [row] = await query<any>(`select * from tenant_settings where tenant_id = $1`, [tenantId]);
  return row ?? null;
}

const SETTING_FIELDS = [
  'business_hours', 'holiday_settings', 'greeting_message', 'ai_tone', 'default_language',
  'recording_enabled', 'human_transfer_enabled', 'transfer_phone_number', 'notification_email',
  'slack_webhook_url', 'notify_on_call_end', 'notify_on_callback', 'notify_on_transfer', 'fallback_message',
] as const;

export async function updateSettings(tenantId: string, patch: Record<string, unknown>) {
  if (!dbEnabled) {
    for (const k of SETTING_FIELDS) if (k in patch) (demoSettings as any)[k] = patch[k];
    return demoSettings;
  }
  const cols = SETTING_FIELDS.filter((k) => k in patch);
  if (cols.length === 0) return getSettings(tenantId);
  const sets = cols.map((c, i) => `${c} = $${i + 2}`).join(', ');
  const values = cols.map((c) => {
    const v = patch[c];
    return (c === 'business_hours' || c === 'holiday_settings') ? JSON.stringify(v) : v;
  });
  const [row] = await query<any>(
    `insert into tenant_settings (tenant_id) values ($1)
       on conflict (tenant_id) do update set ${sets} returning *`,
    [tenantId, ...values]);
  return row;
}

// ---------------- Phone numbers ----------------
export async function listPhoneNumbers(tenantId: string) {
  if (!dbEnabled) return demoPhoneNumbers.filter((p) => p.tenant_id === tenantId || tenantId === demoTenant.id);
  return query<any>(`select * from phone_numbers where tenant_id = $1 order by created_at`, [tenantId]);
}

export async function updatePhoneNumber(tenantId: string, id: string, patch: { status?: string; type?: string }) {
  if (!dbEnabled) {
    const p = demoPhoneNumbers.find((x) => x.id === id);
    if (!p) return null;
    if (patch.status) p.status = patch.status;
    if (patch.type) p.type = patch.type;
    return p;
  }
  const [row] = await query<any>(
    `update phone_numbers set status=coalesce($3,status), type=coalesce($4,type)
      where id=$1 and tenant_id=$2 returning *`,
    [id, tenantId, patch.status, patch.type]);
  return row ?? null;
}

// ---------------- Super Admin ----------------
export async function listTenants() {
  if (!dbEnabled) return [demoTenant];
  return query<any>(`select id, company_name, industry, plan, status, created_at from tenants order by created_at desc`);
}

export async function createTenant(input: { company_name: string; industry?: string; plan?: string }) {
  if (!dbEnabled) return { ...demoTenant, ...input, id: newId('tenant') };
  const [row] = await query<any>(
    `insert into tenants (company_name, industry, plan) values ($1,$2,$3) returning *`,
    [input.company_name, input.industry ?? null, input.plan ?? 'starter']);
  return row;
}

export async function listAllCalls(filter: CallListFilter) {
  if (!dbEnabled) return demoCalls.map(toCallListItem);
  const params: unknown[] = [filter.limit ?? 100];
  return query<any>(
    `select id, tenant_id, from_number, customer_name, category, status, summary, started_at
       from calls order by started_at desc nulls last limit $1`, params);
}

// ---------------- helpers ----------------
function toCallListItem(c: DemoCall) {
  return {
    id: c.id, from_number: c.from_number, customer_name: c.customer_name, company_name: c.company_name,
    category: c.category, status: c.status, summary: c.summary, started_at: c.started_at, duration_sec: c.duration_sec,
  };
}

function stripDemoCall(c: DemoCall) {
  const { transcripts, notes, ...rest } = c;
  return rest;
}
