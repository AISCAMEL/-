// 見込み客・取引先リスト（CRM的）のデータアクセス。DB or デモ(インメモリ)。
import { dbEnabled, query } from '../db/index.js';
import { demoContacts, demoContactActivities, demoTenant, newId, type DemoContact } from '../demo/fixtures.js';
import { addTargets, createCampaign } from './repo.js';

export interface ContactFilter { category?: string; q?: string; status?: string; }

// 営業ステータス（パイプライン）の表示名。do_not_contact は配信停止フラグ。
export const CONTACT_STATUS_LABEL: Record<string, string> = {
  active: '見込み', in_progress: '商談中', won: '成約', lost: '見送り', do_not_contact: '連絡しない',
};

export async function listContacts(tenantId: string, f: ContactFilter = {}) {
  if (!dbEnabled) {
    let rows = demoContacts.filter((c) => c.tenant_id === tenantId || tenantId === demoTenant.id);
    if (f.category) rows = rows.filter((c) => c.category === f.category);
    if (f.status) rows = rows.filter((c) => (c.status || 'active') === f.status);
    if (f.q) rows = rows.filter((c) => [c.name, c.company, c.phone_number, c.email, c.note].some((v) => v?.includes(f.q!)));
    return rows.sort((a, b) => b.created_at.localeCompare(a.created_at));
  }
  const where: string[] = ['tenant_id = $1']; const params: unknown[] = [tenantId];
  if (f.category) { params.push(f.category); where.push(`category = $${params.length}`); }
  if (f.status) { params.push(f.status); where.push(`coalesce(status,'active') = $${params.length}`); }
  if (f.q) { params.push(`%${f.q}%`); where.push(`(name ilike $${params.length} or company ilike $${params.length} or phone_number ilike $${params.length} or email ilike $${params.length} or note ilike $${params.length})`); }
  return query<any>(`select * from contacts where ${where.join(' and ')} order by created_at desc limit 500`, params);
}

/** ステータス別の連絡先件数サマリー（ダッシュボード用）。 */
export async function contactStatusSummary(tenantId: string): Promise<{ total: number; by_status: Record<string, number> }> {
  const by_status: Record<string, number> = {};
  let total = 0;
  if (!dbEnabled) {
    for (const c of demoContacts.filter((c) => c.tenant_id === tenantId || tenantId === demoTenant.id)) {
      const s = c.status || 'active';
      by_status[s] = (by_status[s] ?? 0) + 1; total++;
    }
    return { total, by_status };
  }
  const rows = await query<any>(`select coalesce(status,'active') as status, count(*)::int as n from contacts where tenant_id=$1 group by status`, [tenantId]);
  rows.forEach((r) => { by_status[r.status] = r.n; total += r.n; });
  return { total, by_status };
}

/** 連絡先ごとの活動履歴（メール送信・ステータス変更など）を記録する。 */
export async function logActivity(tenantId: string, contactId: string, type: string, detail?: string | null) {
  if (!dbEnabled) {
    demoContactActivities.push({ id: newId('ca'), tenant_id: tenantId, contact_id: contactId, type, detail: detail ?? null, created_at: new Date().toISOString() });
    return;
  }
  await query(`insert into contact_activities (tenant_id, contact_id, type, detail) values ($1,$2,$3,$4)`, [tenantId, contactId, type, detail ?? null]);
}

/** 連絡先の活動履歴を新しい順に取得する。 */
export async function listActivities(tenantId: string, contactId: string) {
  if (!dbEnabled) {
    return demoContactActivities
      .filter((a) => a.contact_id === contactId && (a.tenant_id === tenantId || tenantId === demoTenant.id))
      .sort((a, b) => b.created_at.localeCompare(a.created_at));
  }
  return query<any>(`select * from contact_activities where tenant_id=$1 and contact_id=$2 order by created_at desc limit 100`, [tenantId, contactId]);
}

export async function contactCategories(tenantId: string): Promise<string[]> {
  if (!dbEnabled) {
    return Array.from(new Set(demoContacts.filter((c) => c.tenant_id === tenantId || tenantId === demoTenant.id).map((c) => c.category).filter(Boolean) as string[]));
  }
  const rows = await query<any>(`select distinct category from contacts where tenant_id=$1 and category is not null order by category`, [tenantId]);
  return rows.map((r) => r.category);
}

export async function createContacts(tenantId: string, items: Partial<DemoContact>[]) {
  const clean = items.filter((c) => (c.name || c.company || c.phone_number || c.email));
  if (!dbEnabled) {
    const created = clean.map((c) => ({
      id: newId('ct'), tenant_id: tenantId, name: c.name ?? null, company: c.company ?? null,
      phone_number: c.phone_number ?? null, email: c.email ?? null, category: c.category ?? null,
      note: c.note ?? null, tags: c.tags ?? [], status: 'active', created_at: new Date().toISOString(),
    }));
    demoContacts.push(...created);
    return created;
  }
  const out: any[] = [];
  for (const c of clean) {
    const [row] = await query<any>(
      `insert into contacts (tenant_id, name, company, phone_number, email, category, note, tags)
       values ($1,$2,$3,$4,$5,$6,$7,$8) returning *`,
      [tenantId, c.name ?? null, c.company ?? null, c.phone_number ?? null, c.email ?? null, c.category ?? null, c.note ?? null, c.tags ?? []]);
    out.push(row);
  }
  return out;
}

export async function updateContact(tenantId: string, id: string, patch: any) {
  if (!dbEnabled) {
    const c = demoContacts.find((x) => x.id === id && (x.tenant_id === tenantId || tenantId === demoTenant.id));
    if (!c) return null;
    const prevStatus = c.status || 'active';
    for (const k of ['name', 'company', 'phone_number', 'email', 'category', 'note', 'status']) if (k in patch) (c as any)[k] = patch[k];
    if (Array.isArray(patch.tags)) c.tags = patch.tags;
    if (patch.status && patch.status !== prevStatus) {
      await logActivity(tenantId, id, 'status_changed', `${CONTACT_STATUS_LABEL[prevStatus] ?? prevStatus} → ${CONTACT_STATUS_LABEL[patch.status] ?? patch.status}`);
    }
    return c;
  }
  const prev = await query<any>(`select status from contacts where id=$1 and tenant_id=$2`, [id, tenantId]);
  const prevStatus = prev[0]?.status || 'active';
  const [row] = await query<any>(
    `update contacts set name=coalesce($3,name), company=coalesce($4,company), phone_number=$5, email=$6,
        category=$7, note=$8, status=coalesce($9,status), tags=coalesce($10,tags)
      where id=$1 and tenant_id=$2 returning *`,
    [id, tenantId, patch.name ?? null, patch.company ?? null, patch.phone_number ?? null, patch.email ?? null, patch.category ?? null, patch.note ?? null, patch.status ?? null, patch.tags ?? null]);
  if (row && patch.status && patch.status !== prevStatus) {
    await logActivity(tenantId, id, 'status_changed', `${CONTACT_STATUS_LABEL[prevStatus] ?? prevStatus} → ${CONTACT_STATUS_LABEL[patch.status] ?? patch.status}`);
  }
  return row ?? null;
}

export async function deleteContact(tenantId: string, id: string) {
  if (!dbEnabled) {
    const i = demoContacts.findIndex((x) => x.id === id && (x.tenant_id === tenantId || tenantId === demoTenant.id));
    if (i === -1) return false;
    demoContacts.splice(i, 1); return true;
  }
  const rows = await query(`delete from contacts where id=$1 and tenant_id=$2 returning id`, [id, tenantId]);
  return rows.length > 0;
}

/** カテゴリ（または全件）の連絡先から架電キャンペーンを作成する。 */
export async function campaignFromContacts(tenantId: string, opts: { name: string; purpose?: string; opening?: string; goal_prompt?: string; category?: string }) {
  const contacts = await listContacts(tenantId, { category: opts.category });
  const withPhone = contacts.filter((c: any) => c.phone_number && c.status !== 'do_not_contact');
  const campaign = await createCampaign(tenantId, { name: opts.name, purpose: opts.purpose ?? 'sales', opening: opts.opening, goal_prompt: opts.goal_prompt });
  await addTargets(tenantId, campaign.id, withPhone.map((c: any) => ({ name: c.name, company: c.company, phone_number: c.phone_number })));
  return { campaign, added: withPhone.length };
}
