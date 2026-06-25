// アウトバウンド架電（AI営業/催促 等）のデータアクセス。DB or デモ(インメモリ)。
import { dbEnabled, query } from '../db/index.js';
import { demoCampaigns, demoTargets, demoTenant, newId, type DemoCampaign, type DemoTarget } from '../demo/fixtures.js';

const PURPOSES = ['sales', 'reminder', 'survey', 'followup', 'other'];

export async function listCampaigns(tenantId: string) {
  if (!dbEnabled) {
    return demoCampaigns
      .filter((c) => c.tenant_id === tenantId || tenantId === demoTenant.id)
      .map((c) => ({ ...c, target_count: demoTargets.filter((t) => t.campaign_id === c.id).length }));
  }
  return query<any>(
    `select c.*, (select count(*)::int from outbound_targets t where t.campaign_id = c.id) as target_count
       from outbound_campaigns c where c.tenant_id = $1 order by c.created_at desc`, [tenantId]);
}

export async function getCampaign(tenantId: string, id: string) {
  if (!dbEnabled) {
    const c = demoCampaigns.find((x) => x.id === id && (x.tenant_id === tenantId || tenantId === demoTenant.id));
    if (!c) return null;
    return { ...c, targets: demoTargets.filter((t) => t.campaign_id === id) };
  }
  const [c] = await query<any>(`select * from outbound_campaigns where id=$1 and tenant_id=$2`, [id, tenantId]);
  if (!c) return null;
  const targets = await query<any>(`select * from outbound_targets where campaign_id=$1 order by created_at`, [id]);
  return { ...c, targets };
}

export async function createCampaign(tenantId: string, input: any) {
  const purpose = PURPOSES.includes(input.purpose) ? input.purpose : 'sales';
  if (!dbEnabled) {
    const c: DemoCampaign = { id: newId('camp'), tenant_id: tenantId, name: input.name ?? '無題のキャンペーン', purpose, goal_prompt: input.goal_prompt ?? null, opening: input.opening ?? null, status: 'draft', created_at: new Date().toISOString() };
    demoCampaigns.push(c);
    return c;
  }
  const [row] = await query<any>(
    `insert into outbound_campaigns (tenant_id, name, purpose, goal_prompt, opening)
     values ($1,$2,$3,$4,$5) returning *`,
    [tenantId, input.name ?? '無題のキャンペーン', purpose, input.goal_prompt ?? null, input.opening ?? null]);
  return row;
}

export async function updateCampaign(tenantId: string, id: string, patch: any) {
  if (!dbEnabled) {
    const c = demoCampaigns.find((x) => x.id === id && (x.tenant_id === tenantId || tenantId === demoTenant.id));
    if (!c) return null;
    for (const k of ['name', 'purpose', 'goal_prompt', 'opening', 'status']) if (k in patch) (c as any)[k] = patch[k];
    return c;
  }
  const [row] = await query<any>(
    `update outbound_campaigns set name=coalesce($3,name), purpose=coalesce($4,purpose),
        goal_prompt=$5, opening=$6, status=coalesce($7,status) where id=$1 and tenant_id=$2 returning *`,
    [id, tenantId, patch.name ?? null, patch.purpose ?? null, patch.goal_prompt ?? null, patch.opening ?? null, patch.status ?? null]);
  return row ?? null;
}

export async function addTargets(tenantId: string, campaignId: string, targets: { name?: string; company?: string; phone_number: string; amount?: number | null; due_date?: string | null }[]) {
  const clean = targets.filter((t) => t.phone_number && String(t.phone_number).trim());
  if (!dbEnabled) {
    const created = clean.map((t) => ({ id: newId('tgt'), campaign_id: campaignId, tenant_id: tenantId, name: t.name ?? null, company: t.company ?? null, phone_number: String(t.phone_number).trim(), status: 'pending', outcome: null, note: null, amount: t.amount ?? null, due_date: t.due_date ?? null, created_at: new Date().toISOString() }));
    demoTargets.push(...created);
    return created;
  }
  const out: any[] = [];
  for (const t of clean) {
    const [row] = await query<any>(
      `insert into outbound_targets (campaign_id, tenant_id, name, company, phone_number, amount, due_date)
       values ($1,$2,$3,$4,$5,$6,$7) returning *`,
      [campaignId, tenantId, t.name ?? null, t.company ?? null, String(t.phone_number).trim(), t.amount ?? null, t.due_date ?? null]);
    out.push(row);
  }
  return out;
}

// 架電時に相手を特定して個別案内するための検索（Webhook内部用）。
export async function getTargetByPhone(campaignId: string, phone: string) {
  if (!phone) return null;
  if (!dbEnabled) return demoTargets.find((t) => t.campaign_id === campaignId && t.phone_number === phone) ?? null;
  const [row] = await query<any>(`select * from outbound_targets where campaign_id=$1 and phone_number=$2 limit 1`, [campaignId, phone]);
  return row ?? null;
}

export async function updateTarget(tenantId: string, id: string, patch: { status?: string; outcome?: string; note?: string }) {
  if (!dbEnabled) {
    const t = demoTargets.find((x) => x.id === id && (x.tenant_id === tenantId || tenantId === demoTenant.id));
    if (!t) return null;
    if (patch.status) t.status = patch.status;
    if (patch.outcome !== undefined) t.outcome = patch.outcome;
    if (patch.note !== undefined) t.note = patch.note;
    return t;
  }
  const [row] = await query<any>(
    `update outbound_targets set status=coalesce($3,status), outcome=coalesce($4,outcome), note=coalesce($5,note)
      where id=$1 and tenant_id=$2 returning *`,
    [id, tenantId, patch.status ?? null, patch.outcome ?? null, patch.note ?? null]);
  return row ?? null;
}

// テナント横断でキャンペーンを取得（Twilio Webhook内部用。署名検証前提）。
export async function getCampaignById(id: string) {
  if (!dbEnabled) return demoCampaigns.find((c) => c.id === id) ?? null;
  const [c] = await query<any>(`select * from outbound_campaigns where id=$1`, [id]);
  return c ?? null;
}

export async function getPendingTargets(tenantId: string, campaignId: string): Promise<DemoTarget[] | any[]> {
  if (!dbEnabled) {
    return demoTargets.filter((t) => t.campaign_id === campaignId && (t.tenant_id === tenantId || tenantId === demoTenant.id) && t.status === 'pending');
  }
  return query<any>(`select * from outbound_targets where campaign_id=$1 and tenant_id=$2 and status='pending'`, [campaignId, tenantId]);
}
