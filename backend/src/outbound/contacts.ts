// 見込み客・取引先リスト（CRM的）のデータアクセス。DB or デモ(インメモリ)。
import { dbEnabled, query } from '../db/index.js';
import { demoContacts, demoTenant, newId, type DemoContact } from '../demo/fixtures.js';
import { addTargets, createCampaign } from './repo.js';

export interface ContactFilter { category?: string; q?: string; }

export async function listContacts(tenantId: string, f: ContactFilter = {}) {
  if (!dbEnabled) {
    let rows = demoContacts.filter((c) => c.tenant_id === tenantId || tenantId === demoTenant.id);
    if (f.category) rows = rows.filter((c) => c.category === f.category);
    if (f.q) rows = rows.filter((c) => [c.name, c.company, c.phone_number, c.email, c.note].some((v) => v?.includes(f.q!)));
    return rows.sort((a, b) => b.created_at.localeCompare(a.created_at));
  }
  const where: string[] = ['tenant_id = $1']; const params: unknown[] = [tenantId];
  if (f.category) { params.push(f.category); where.push(`category = $${params.length}`); }
  if (f.q) { params.push(`%${f.q}%`); where.push(`(name ilike $${params.length} or company ilike $${params.length} or phone_number ilike $${params.length} or email ilike $${params.length} or note ilike $${params.length})`); }
  return query<any>(`select * from contacts where ${where.join(' and ')} order by created_at desc limit 500`, params);
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
    for (const k of ['name', 'company', 'phone_number', 'email', 'category', 'note', 'status']) if (k in patch) (c as any)[k] = patch[k];
    if (Array.isArray(patch.tags)) c.tags = patch.tags;
    return c;
  }
  const [row] = await query<any>(
    `update contacts set name=coalesce($3,name), company=coalesce($4,company), phone_number=$5, email=$6,
        category=$7, note=$8, status=coalesce($9,status), tags=coalesce($10,tags)
      where id=$1 and tenant_id=$2 returning *`,
    [id, tenantId, patch.name ?? null, patch.company ?? null, patch.phone_number ?? null, patch.email ?? null, patch.category ?? null, patch.note ?? null, patch.status ?? null, patch.tags ?? null]);
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
