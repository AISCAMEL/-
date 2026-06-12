// 問い合わせ導線（リード管理）のデータアクセス + ステップメール worker。
// DB接続時は PostgreSQL、未接続時はインメモリ（デモ）。
import { dbEnabled, query } from '../db/index.js';
import { sendEmail } from '../notify/email.js';
import { DEFAULT_SEQUENCE, renderTemplate } from './sequence.js';

export interface Lead {
  id: string; source: string; category: string; status: string;
  name: string | null; company: string | null; email: string | null; phone: string | null;
  industry: string | null; message: string | null; assigned_to: string | null;
  meta: Record<string, unknown>; created_at: string; updated_at: string;
}
interface LeadNote { id: string; lead_id: string; note: string; created_at: string; }
interface Meeting {
  id: string; lead_id: string; title: string; scheduled_at: string | null;
  status: string; meeting_url: string | null; note: string | null; created_at: string;
}
interface ScheduledEmail {
  id: string; lead_id: string; step_no: number; subject: string; body: string;
  to_email: string; scheduled_at: string; status: string; sent_at: string | null; error: string | null;
}

// ---- デモ用インメモリストア ----
const mem = { leads: [] as Lead[], notes: [] as LeadNote[], meetings: [] as Meeting[], emails: [] as ScheduledEmail[] };
const uid = (p: string) => `${p}-${Math.random().toString(36).slice(2, 10)}`;
seedDemo();

export interface CreateLeadInput {
  source?: string; category?: string; name?: string; company?: string;
  email?: string; phone?: string; industry?: string; message?: string; meta?: Record<string, unknown>;
}

/** リード作成 + ステップメール予約（アウトボックスに積む）。 */
export async function createLead(input: CreateLeadInput): Promise<Lead> {
  const now = new Date();
  if (!dbEnabled) {
    const lead: Lead = {
      id: uid('lead'), source: input.source ?? 'lp_form', category: input.category ?? 'inquiry',
      status: 'new', name: input.name ?? null, company: input.company ?? null,
      email: input.email ?? null, phone: input.phone ?? null, industry: input.industry ?? null,
      message: input.message ?? null, assigned_to: null, meta: input.meta ?? {},
      created_at: now.toISOString(), updated_at: now.toISOString(),
    };
    mem.leads.push(lead);
    enqueueDemoSequence(lead, now);
    return lead;
  }
  const [lead] = await query<Lead>(
    `insert into leads (source, category, name, company, email, phone, industry, message, meta)
     values ($1,$2,$3,$4,$5,$6,$7,$8,$9) returning *`,
    [input.source ?? 'lp_form', input.category ?? 'inquiry', input.name ?? null, input.company ?? null,
     input.email ?? null, input.phone ?? null, input.industry ?? null, input.message ?? null,
     JSON.stringify(input.meta ?? {})],
  );
  if (lead.email) {
    for (const step of DEFAULT_SEQUENCE) {
      const at = new Date(now.getTime() + step.delayHours * 3600_000);
      await query(
        `insert into scheduled_emails (lead_id, step_no, subject, body, to_email, scheduled_at)
         values ($1,$2,$3,$4,$5,$6)`,
        [lead.id, step.stepNo, step.subject,
         renderTemplate(step.body, lead), lead.email, at.toISOString()],
      );
    }
  }
  return lead;
}

function enqueueDemoSequence(lead: Lead, now: Date) {
  if (!lead.email) return;
  for (const step of DEFAULT_SEQUENCE) {
    mem.emails.push({
      id: uid('mail'), lead_id: lead.id, step_no: step.stepNo, subject: step.subject,
      body: renderTemplate(step.body, lead), to_email: lead.email,
      scheduled_at: new Date(now.getTime() + step.delayHours * 3600_000).toISOString(),
      status: 'pending', sent_at: null, error: null,
    });
  }
}

export interface LeadFilter { status?: string; category?: string; q?: string; }

export async function listLeads(f: LeadFilter) {
  if (!dbEnabled) {
    let rows = [...mem.leads];
    if (f.status) rows = rows.filter((l) => l.status === f.status);
    if (f.category) rows = rows.filter((l) => l.category === f.category);
    if (f.q) rows = rows.filter((l) => [l.name, l.company, l.email, l.message].some((v) => v?.includes(f.q!)));
    return rows.sort((a, b) => b.created_at.localeCompare(a.created_at));
  }
  const where: string[] = []; const params: unknown[] = [];
  if (f.status) { params.push(f.status); where.push(`status = $${params.length}`); }
  if (f.category) { params.push(f.category); where.push(`category = $${params.length}`); }
  if (f.q) { params.push(`%${f.q}%`); where.push(`(name ilike $${params.length} or company ilike $${params.length} or email ilike $${params.length} or message ilike $${params.length})`); }
  const sql = `select * from leads ${where.length ? 'where ' + where.join(' and ') : ''} order by created_at desc limit 200`;
  return query<Lead>(sql, params);
}

export async function getLead(id: string) {
  if (!dbEnabled) {
    const lead = mem.leads.find((l) => l.id === id);
    if (!lead) return null;
    return {
      ...lead,
      notes: mem.notes.filter((n) => n.lead_id === id).sort((a, b) => a.created_at.localeCompare(b.created_at)),
      meetings: mem.meetings.filter((m) => m.lead_id === id).sort((a, b) => a.created_at.localeCompare(b.created_at)),
      emails: mem.emails.filter((e) => e.lead_id === id).sort((a, b) => a.step_no - b.step_no),
    };
  }
  const [lead] = await query<Lead>(`select * from leads where id = $1`, [id]);
  if (!lead) return null;
  const notes = await query(`select id, note, created_at from lead_notes where lead_id=$1 order by created_at`, [id]);
  const meetings = await query(`select * from meetings where lead_id=$1 order by created_at`, [id]);
  const emails = await query(`select id, step_no, subject, scheduled_at, status, sent_at, error from scheduled_emails where lead_id=$1 order by step_no`, [id]);
  return { ...lead, notes, meetings, emails };
}

export async function updateLead(id: string, patch: { status?: string; category?: string; assigned_to?: string | null }) {
  if (!dbEnabled) {
    const l = mem.leads.find((x) => x.id === id);
    if (!l) return null;
    if (patch.status) l.status = patch.status;
    if (patch.category) l.category = patch.category;
    if (patch.assigned_to !== undefined) l.assigned_to = patch.assigned_to;
    l.updated_at = new Date().toISOString();
    // ステータスが won/lost/closed になったら未送信のステップメールを取消。
    if (patch.status && ['won', 'lost', 'closed'].includes(patch.status)) {
      mem.emails.forEach((e) => { if (e.lead_id === id && e.status === 'pending') e.status = 'canceled'; });
    }
    return l;
  }
  const [row] = await query<Lead>(
    `update leads set status=coalesce($2,status), category=coalesce($3,category), assigned_to=$4 where id=$1 returning *`,
    [id, patch.status ?? null, patch.category ?? null, patch.assigned_to ?? null]);
  if (row && patch.status && ['won', 'lost', 'closed'].includes(patch.status)) {
    await query(`update scheduled_emails set status='canceled' where lead_id=$1 and status='pending'`, [id]);
  }
  return row ?? null;
}

export async function addLeadNote(leadId: string, note: string) {
  if (!dbEnabled) {
    const n = { id: uid('lnote'), lead_id: leadId, note, created_at: new Date().toISOString() };
    mem.notes.push(n);
    return n;
  }
  const [row] = await query(`insert into lead_notes (lead_id, note) values ($1,$2) returning id, note, created_at`, [leadId, note]);
  return row;
}

export async function createMeeting(leadId: string, input: { title?: string; scheduled_at?: string; meeting_url?: string; note?: string }) {
  if (!dbEnabled) {
    const m: Meeting = {
      id: uid('mtg'), lead_id: leadId, title: input.title ?? '商談・ご相談',
      scheduled_at: input.scheduled_at ?? null, status: input.scheduled_at ? 'confirmed' : 'proposed',
      meeting_url: input.meeting_url ?? null, note: input.note ?? null, created_at: new Date().toISOString(),
    };
    mem.meetings.push(m);
    const lead = mem.leads.find((l) => l.id === leadId);
    if (lead && lead.status === 'new') lead.status = 'meeting_scheduled';
    return m;
  }
  const [row] = await query<Meeting>(
    `insert into meetings (lead_id, title, scheduled_at, status, meeting_url, note)
     values ($1,$2,$3,$4,$5,$6) returning *`,
    [leadId, input.title ?? '商談・ご相談', input.scheduled_at ?? null,
     input.scheduled_at ? 'confirmed' : 'proposed', input.meeting_url ?? null, input.note ?? null]);
  await query(`update leads set status='meeting_scheduled' where id=$1 and status in ('new','contacted','in_progress')`, [leadId]);
  return row;
}

export async function updateMeeting(id: string, patch: { status?: string; scheduled_at?: string }) {
  if (!dbEnabled) {
    const m = mem.meetings.find((x) => x.id === id);
    if (!m) return null;
    if (patch.status) m.status = patch.status;
    if (patch.scheduled_at !== undefined) m.scheduled_at = patch.scheduled_at;
    return m;
  }
  const [row] = await query<Meeting>(
    `update meetings set status=coalesce($2,status), scheduled_at=coalesce($3,scheduled_at) where id=$1 returning *`,
    [id, patch.status ?? null, patch.scheduled_at ?? null]);
  return row ?? null;
}

/** 手動メール送信（即時）。アウトボックスにも記録。 */
export async function sendManualEmail(leadId: string, subject: string, body: string) {
  const lead = await getLead(leadId);
  if (!lead || !lead.email) return { ok: false, error: 'no email' };
  const res = await sendEmail(lead.email, subject, body);
  if (!dbEnabled) {
    mem.emails.push({
      id: uid('mail'), lead_id: leadId, step_no: 0, subject, body, to_email: lead.email,
      scheduled_at: new Date().toISOString(), status: res.ok ? 'sent' : 'failed',
      sent_at: res.ok ? new Date().toISOString() : null, error: res.error ?? null,
    });
  } else {
    await query(
      `insert into scheduled_emails (lead_id, step_no, subject, body, to_email, scheduled_at, status, sent_at, error)
       values ($1,0,$2,$3,$4, now(), $5, $6, $7)`,
      [leadId, subject, body, lead.email, res.ok ? 'sent' : 'failed', res.ok ? new Date().toISOString() : null, res.error ?? null]);
  }
  return res;
}

// ---- ステップメール worker ----
/** 予定時刻を過ぎた pending メールを送信する。 */
export async function processDueEmails(): Promise<number> {
  const nowIso = new Date().toISOString();
  let sent = 0;
  if (!dbEnabled) {
    const due = mem.emails.filter((e) => e.status === 'pending' && e.scheduled_at <= nowIso);
    for (const e of due) {
      const res = await sendEmail(e.to_email, e.subject, e.body);
      e.status = res.ok ? 'sent' : 'failed';
      e.sent_at = res.ok ? new Date().toISOString() : null;
      e.error = res.error ?? null;
      if (res.ok) sent++;
    }
    return sent;
  }
  const due = await query<ScheduledEmail>(
    `select * from scheduled_emails where status='pending' and scheduled_at <= now() order by scheduled_at limit 50`);
  for (const e of due) {
    const res = await sendEmail(e.to_email, e.subject, e.body);
    await query(`update scheduled_emails set status=$2, sent_at=$3, error=$4 where id=$1`,
      [e.id, res.ok ? 'sent' : 'failed', res.ok ? new Date().toISOString() : null, res.error ?? null]);
    if (res.ok) sent++;
  }
  return sent;
}

function seedDemo() {
  if (dbEnabled) return;
  const now = Date.now();
  const lead: Lead = {
    id: 'lead-demo1', source: 'lp_form', category: 'consultation', status: 'contacted',
    name: '田中一郎', company: '田中工務店', email: 'tanaka@example.com', phone: '+819012345678',
    industry: '工事業', message: '営業時間外の電話を取りこぼしているので相談したい。',
    assigned_to: null, meta: { utm_source: 'google' },
    created_at: new Date(now - 86400_000 * 2).toISOString(), updated_at: new Date(now - 86400_000).toISOString(),
  };
  mem.leads.push(lead);
  mem.notes.push({ id: 'lnote-1', lead_id: lead.id, note: '初回メール送信済み。返信待ち。', created_at: new Date(now - 86400_000).toISOString() });
  // 既に送信済み/予約済みのステップメールを表現
  DEFAULT_SEQUENCE.forEach((s) => {
    const at = new Date(new Date(lead.created_at).getTime() + s.delayHours * 3600_000);
    mem.emails.push({
      id: uid('mail'), lead_id: lead.id, step_no: s.stepNo, subject: s.subject,
      body: renderTemplate(s.body, lead), to_email: lead.email!, scheduled_at: at.toISOString(),
      status: at.getTime() <= now ? 'sent' : 'pending', sent_at: at.getTime() <= now ? at.toISOString() : null, error: null,
    });
  });
}
