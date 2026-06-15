// 管理画面API用のデータアクセス層。
// DB接続時は PostgreSQL、未接続(デモモード)時はインメモリ fixtures を操作する。
import { dbEnabled, query } from './index.js';
import { summarizeCall } from '../ai/summarize.js';
import {
  demoCalls, demoFaqs, demoSettings, demoPhoneNumbers, demoTenant, demoUsers, newId,
  type DemoCall, type DemoFaq, type DemoUser,
} from '../demo/fixtures.js';
import type { CallSummary, TenantContext } from '../types.js';
import {
  planDef, billableMinutes, aiCostJpy, transferAddCostJpy, monthlyRevenueJpy,
  AI_COST_USD_PER_MIN, USD_JPY,
} from '../billing/rates.js';

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

/** AI応対テスト用に、テナントの設定＋FAQをまとめた会話コンテキストを返す。 */
export async function getTenantAiContext(tenantId: string): Promise<TenantContext> {
  const s = await getSettings(tenantId);
  const faqRows = await listFaqs(tenantId);
  let companyName = demoTenant.company_name;
  let industry: string | null = demoTenant.industry;
  if (dbEnabled) {
    const [t] = await query<any>(`select company_name, industry from tenants where id = $1`, [tenantId]);
    companyName = t?.company_name ?? '（テナント）';
    industry = t?.industry ?? null;
  }
  return {
    tenantId,
    companyName,
    industry,
    greetingMessage: s?.greeting_message ?? 'お電話ありがとうございます。AI受付です。ご用件をお話しください。',
    aiTone: s?.ai_tone ?? 'polite',
    businessHours: s?.business_hours ?? {},
    holidaySettings: s?.holiday_settings ?? {},
    humanTransferEnabled: s?.human_transfer_enabled ?? true,
    transferPhoneNumber: s?.transfer_phone_number ?? null,
    notificationEmail: s?.notification_email ?? null,
    slackWebhookUrl: s?.slack_webhook_url ?? null,
    notifyOnCallEnd: s?.notify_on_call_end ?? true,
    notifyOnCallback: s?.notify_on_callback ?? true,
    notifyOnTransfer: s?.notify_on_transfer ?? true,
    fallbackMessage: s?.fallback_message ?? null,
    faqs: (faqRows ?? []).filter((f: any) => f.is_active !== false)
      .map((f: any) => ({ question: f.question, answer: f.answer, category: f.category ?? null })),
  };
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

// ---------------- 利用量・原価モニタリング ----------------
// 'YYYY-MM' → [開始, 翌月開始) のISO範囲。未指定は当月。
function monthRange(month?: string): { from: Date; to: Date; key: string } {
  const now = new Date();
  const [y, m] = month
    ? month.split('-').map(Number)
    : [now.getUTCFullYear(), now.getUTCMonth() + 1];
  const from = new Date(Date.UTC(y, m - 1, 1));
  const to = new Date(Date.UTC(y, m, 1));
  return { from, to, key: `${y}-${String(m).padStart(2, '0')}` };
}

function buildSummary(
  plan: string | null, calls: number, billableMin: number, transferMin: number, monthKey: string,
) {
  const p = planDef(plan);
  const costAi = aiCostJpy(billableMin);
  const costTransfer = transferAddCostJpy(transferMin);
  const totalCost = round2(costAi + costTransfer);
  const revenue = monthlyRevenueJpy(p, billableMin);
  const margin = round2(revenue - totalCost);
  return {
    month: monthKey,
    plan: { key: plan ?? 'starter', label: p.label, allowance_min: p.allowanceMin, base_jpy: p.baseJpy, overage_jpy_per_min: p.overageJpyPerMin },
    calls,
    billable_minutes: billableMin,
    transfer_minutes: transferMin,
    overage_minutes: Math.max(0, billableMin - p.allowanceMin),
    cost: { ai_jpy: costAi, transfer_jpy: costTransfer, total_jpy: totalCost },
    revenue_jpy: revenue,
    margin_jpy: margin,
    margin_rate: revenue > 0 ? Math.round((margin / revenue) * 1000) / 10 : 0,
    ai_cost_per_min_jpy: round2(AI_COST_USD_PER_MIN * USD_JPY),
    usd_jpy: USD_JPY,
  };
}

function round2(n: number): number { return Math.round(n * 100) / 100; }

export async function getUsageSummary(tenantId: string, month?: string) {
  const { from, to, key } = monthRange(month);
  if (!dbEnabled) {
    const rows = demoCalls.filter((c) => {
      const t = new Date(c.started_at);
      return (c.tenant_id === tenantId || tenantId === demoTenant.id) && t >= from && t < to;
    });
    const billable = rows.reduce((s, c) => s + billableMinutes(c.duration_sec), 0);
    const transfer = rows.filter((c) => c.status === 'transferred')
      .reduce((s, c) => s + billableMinutes(c.duration_sec), 0);
    return buildSummary(demoTenant.plan, rows.length, billable, transfer, key);
  }
  const [agg] = await query<any>(
    `select count(*)::int as calls,
            coalesce(sum(ceil(duration_sec/60.0)),0)::int as billable_min,
            coalesce(sum(ceil(duration_sec/60.0)) filter (where status='transferred'),0)::int as transfer_min
       from calls where tenant_id = $1 and started_at >= $2 and started_at < $3`,
    [tenantId, from.toISOString(), to.toISOString()],
  );
  const [tenant] = await query<any>(`select plan from tenants where id = $1`, [tenantId]);
  return buildSummary(tenant?.plan ?? 'starter', agg.calls, agg.billable_min, agg.transfer_min, key);
}

export async function getAdminUsageSummary(month?: string) {
  const { from, to, key } = monthRange(month);
  if (!dbEnabled) {
    const t = await getUsageSummary(demoTenant.id, month);
    return {
      month: key,
      tenants: [{ tenant_id: demoTenant.id, company_name: demoTenant.company_name, ...t }],
      totals: { calls: t.calls, billable_minutes: t.billable_minutes, cost_jpy: t.cost.total_jpy, revenue_jpy: t.revenue_jpy, margin_jpy: t.margin_jpy },
    };
  }
  const rows = await query<any>(
    `select t.id as tenant_id, t.company_name, t.plan,
            count(c.*)::int as calls,
            coalesce(sum(ceil(c.duration_sec/60.0)),0)::int as billable_min,
            coalesce(sum(ceil(c.duration_sec/60.0)) filter (where c.status='transferred'),0)::int as transfer_min
       from tenants t
       left join calls c on c.tenant_id = t.id and c.started_at >= $1 and c.started_at < $2
      group by t.id, t.company_name, t.plan
      order by billable_min desc`,
    [from.toISOString(), to.toISOString()],
  );
  const tenants = rows.map((r) => ({
    tenant_id: r.tenant_id, company_name: r.company_name,
    ...buildSummary(r.plan, r.calls, r.billable_min, r.transfer_min, key),
  }));
  const totals = tenants.reduce(
    (acc, t) => ({
      calls: acc.calls + t.calls, billable_minutes: acc.billable_minutes + t.billable_minutes,
      cost_jpy: round2(acc.cost_jpy + t.cost.total_jpy), revenue_jpy: acc.revenue_jpy + t.revenue_jpy,
      margin_jpy: round2(acc.margin_jpy + t.margin_jpy),
    }),
    { calls: 0, billable_minutes: 0, cost_jpy: 0, revenue_jpy: 0, margin_jpy: 0 },
  );
  return { month: key, tenants, totals };
}

// ---------------- ユーザー管理（テナント内スタッフ） ----------------
const ASSIGNABLE_ROLES = ['owner', 'admin', 'staff'] as const;
type AssignableRole = (typeof ASSIGNABLE_ROLES)[number];

export function isAssignableRole(r: string): r is AssignableRole {
  return (ASSIGNABLE_ROLES as readonly string[]).includes(r);
}

export async function listUsers(tenantId: string) {
  if (!dbEnabled) {
    return demoUsers.filter((u) => u.tenant_id === tenantId || tenantId === demoTenant.id);
  }
  return query<any>(
    `select id, name, email, role, is_active, created_at
       from app_users where tenant_id = $1 order by created_at`,
    [tenantId],
  );
}

export async function createUser(tenantId: string, input: { name?: string; email: string; role: string }) {
  const role = isAssignableRole(input.role) ? input.role : 'staff';
  if (!dbEnabled) {
    if (demoUsers.some((u) => u.tenant_id === tenantId && u.email === input.email)) {
      return { error: 'duplicate' as const };
    }
    const u: DemoUser = {
      id: newId('user'), tenant_id: tenantId, name: input.name ?? '', email: input.email,
      role, is_active: true, created_at: new Date().toISOString(),
    };
    demoUsers.push(u);
    return { user: u };
  }
  try {
    const [row] = await query<any>(
      `insert into app_users (tenant_id, name, email, role, is_active)
       values ($1,$2,$3,$4,true)
       returning id, name, email, role, is_active, created_at`,
      [tenantId, input.name ?? null, input.email, role],
    );
    return { user: row };
  } catch (err: any) {
    if (String(err?.code) === '23505') return { error: 'duplicate' as const };
    throw err;
  }
}

export async function updateUser(
  tenantId: string, userId: string, patch: { role?: string; is_active?: boolean; name?: string },
) {
  const role = patch.role && isAssignableRole(patch.role) ? patch.role : undefined;
  if (!dbEnabled) {
    const u = demoUsers.find((x) => x.id === userId && (x.tenant_id === tenantId || tenantId === demoTenant.id));
    if (!u) return null;
    if (role) u.role = role;
    if (patch.is_active !== undefined) u.is_active = patch.is_active;
    if (patch.name !== undefined) u.name = patch.name;
    return u;
  }
  const [row] = await query<any>(
    `update app_users set role = coalesce($3, role), is_active = coalesce($4, is_active),
        name = coalesce($5, name)
      where id = $1 and tenant_id = $2
      returning id, name, email, role, is_active, created_at`,
    [userId, tenantId, role ?? null, patch.is_active ?? null, patch.name ?? null],
  );
  return row ?? null;
}

export async function deleteUser(tenantId: string, userId: string) {
  if (!dbEnabled) {
    const i = demoUsers.findIndex((x) => x.id === userId && (x.tenant_id === tenantId || tenantId === demoTenant.id));
    if (i === -1) return false;
    demoUsers.splice(i, 1);
    return true;
  }
  const rows = await query(`delete from app_users where id = $1 and tenant_id = $2 returning id`, [userId, tenantId]);
  return rows.length > 0;
}

/** テナントの owner が最低1人残るかをチェック（最後の owner の降格/無効化/削除を防ぐ）。 */
export async function countActiveOwners(tenantId: string, excludeUserId?: string): Promise<number> {
  if (!dbEnabled) {
    return demoUsers.filter((u) =>
      (u.tenant_id === tenantId || tenantId === demoTenant.id) &&
      u.role === 'owner' && u.is_active && u.id !== excludeUserId).length;
  }
  const [r] = await query<any>(
    `select count(*)::int as n from app_users
      where tenant_id = $1 and role = 'owner' and is_active = true and id <> $2`,
    [tenantId, excludeUserId ?? '00000000-0000-0000-0000-000000000000'],
  );
  return r?.n ?? 0;
}

// ---------------- 請求書・明細 ----------------
const TAX_RATE = 0.1; // 消費税10%

/** 顧客向け請求書データ（charges のみ。原価・粗利は含めない）。 */
export async function getInvoice(tenantId: string, month?: string) {
  const summary = await getUsageSummary(tenantId, month);
  const tenant = await getTenantBilling(tenantId);
  const p = summary.plan;

  const lines: { desc: string; qty: number; unit: string; unitPrice: number; amount: number }[] = [
    { desc: `基本料金（${p.label}プラン / 月${p.allowance_min}分まで）`, qty: 1, unit: '式', unitPrice: p.base_jpy, amount: p.base_jpy },
  ];
  if (summary.overage_minutes > 0) {
    const amt = summary.overage_minutes * p.overage_jpy_per_min;
    lines.push({ desc: '超過通話料', qty: summary.overage_minutes, unit: '分', unitPrice: p.overage_jpy_per_min, amount: amt });
  }
  const subtotal = lines.reduce((s, l) => s + l.amount, 0);
  const tax = Math.round(subtotal * TAX_RATE);
  const total = subtotal + tax;

  return {
    invoice_no: `INV-${summary.month.replace('-', '')}-${tenantId.slice(0, 8)}`,
    issued_date: new Date().toISOString().slice(0, 10),
    month: summary.month,
    tenant,
    plan: p,
    usage: { calls: summary.calls, billable_minutes: summary.billable_minutes, overage_minutes: summary.overage_minutes },
    lines,
    subtotal,
    tax_rate: TAX_RATE,
    tax,
    total,
    currency: 'JPY',
  };
}

async function getTenantBilling(tenantId: string) {
  if (!dbEnabled) {
    return {
      company_name: demoTenant.company_name,
      billing_email: 'owner@example.com',
      address: '東京都〇〇区サンプル1-2-3',
    };
  }
  const [t] = await query<any>(`select company_name, billing_email, address from tenants where id = $1`, [tenantId]);
  return t ?? { company_name: '—', billing_email: null, address: null };
}

/** 通話明細（CSV用の行データ）。 */
export async function getCallLineItems(tenantId: string, month?: string) {
  const { from, to } = monthRange(month);
  if (!dbEnabled) {
    return demoCalls
      .filter((c) => {
        const t = new Date(c.started_at);
        return (c.tenant_id === tenantId || tenantId === demoTenant.id) && t >= from && t < to;
      })
      .sort((a, b) => a.started_at.localeCompare(b.started_at))
      .map((c) => lineItem(c.started_at, c.from_number, c.customer_name, c.company_name, c.category, c.status, c.duration_sec));
  }
  const rows = await query<any>(
    `select started_at, from_number, customer_name, company_name, category, status, duration_sec
       from calls where tenant_id = $1 and started_at >= $2 and started_at < $3 order by started_at`,
    [tenantId, from.toISOString(), to.toISOString()],
  );
  return rows.map((c) => lineItem(c.started_at, c.from_number, c.customer_name, c.company_name, c.category, c.status, c.duration_sec));
}

function lineItem(
  startedAt: string, from: string | null, customer: string | null, company: string | null,
  category: string | null, status: string, durationSec: number | null,
) {
  const min = billableMinutes(durationSec);
  return {
    started_at: startedAt,
    from_number: from ?? '',
    customer_name: customer ?? '',
    company_name: company ?? '',
    category: category ?? '',
    status,
    duration_sec: durationSec ?? 0,
    billable_minutes: min,
    est_cost_jpy: aiCostJpy(min),
  };
}

/** 明細をCSV文字列に整形（Excel向けにBOM付与は呼び出し側で）。 */
export function lineItemsToCsv(items: Awaited<ReturnType<typeof getCallLineItems>>): string {
  const header = ['通話日時', '発信者番号', '顧客名', '会社名', '要件', 'ステータス', '通話秒数', '課金分', '推定原価(円)'];
  const rows = items.map((i) => [
    i.started_at, i.from_number, i.customer_name, i.company_name, i.category, i.status,
    String(i.duration_sec), String(i.billable_minutes), String(i.est_cost_jpy),
  ]);
  return [header, ...rows].map((r) => r.map(csvCell).join(',')).join('\r\n');
}

function csvCell(v: string): string {
  return /[",\r\n]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v;
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

/** テナント詳細（契約情報＋設定＋番号＋当月利用＋ユーザ数）。super_admin用。 */
export async function getTenantDetail(tenantId: string) {
  const usage = await getUsageSummary(tenantId).catch(() => null);
  if (!dbEnabled) {
    const isDemo = tenantId === demoTenant.id;
    return {
      tenant: {
        ...demoTenant,
        billing_email: 'owner@example.com', phone: '+815000000000',
        address: '東京都〇〇区サンプル1-2-3', memo: '', created_at: new Date(Date.now() - 86400_000 * 30).toISOString(),
      },
      settings: demoSettings,
      phone_numbers: demoPhoneNumbers.filter((p) => isDemo || p.tenant_id === tenantId),
      user_count: 2,
      usage,
    };
  }
  const [tenant] = await query<any>(`select * from tenants where id = $1`, [tenantId]);
  if (!tenant) return null;
  const [settings] = await query<any>(`select * from tenant_settings where tenant_id = $1`, [tenantId]);
  const phone_numbers = await query<any>(`select * from phone_numbers where tenant_id = $1 order by created_at`, [tenantId]);
  const [uc] = await query<any>(`select count(*)::int as n from app_users where tenant_id = $1`, [tenantId]);
  return { tenant, settings: settings ?? null, phone_numbers, user_count: uc?.n ?? 0, usage };
}

const TENANT_FIELDS = ['company_name', 'industry', 'plan', 'status', 'billing_email', 'phone', 'address', 'memo'] as const;
const PLAN_VALUES = ['starter', 'business', 'pro', 'enterprise'];
const STATUS_VALUES = ['active', 'inactive', 'suspended', 'trial', 'closed'];

export async function updateTenant(tenantId: string, patch: Record<string, unknown>) {
  if (patch.plan && !PLAN_VALUES.includes(String(patch.plan))) return { error: 'invalid plan' as const };
  if (patch.status && !STATUS_VALUES.includes(String(patch.status))) return { error: 'invalid status' as const };
  if (!dbEnabled) {
    if (tenantId === demoTenant.id) {
      for (const k of TENANT_FIELDS) if (k in patch) (demoTenant as any)[k] = patch[k];
    }
    return { tenant: { ...demoTenant } };
  }
  const cols = TENANT_FIELDS.filter((k) => k in patch);
  if (cols.length === 0) {
    const [row] = await query<any>(`select * from tenants where id = $1`, [tenantId]);
    return row ? { tenant: row } : null;
  }
  const sets = cols.map((c, i) => `${c} = $${i + 2}`).join(', ');
  const [row] = await query<any>(
    `update tenants set ${sets} where id = $1 returning *`,
    [tenantId, ...cols.map((c) => patch[c])]);
  return row ? { tenant: row } : null;
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
