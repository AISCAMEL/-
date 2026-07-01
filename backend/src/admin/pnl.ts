// 運営の損益計算書（P&L）。売上 − 売上原価 = 粗利 − 販管費(固定経費) = 営業利益。
// トライアル/休止テナントは売上0（原価は発生）として正直に計上する。
import { dbEnabled, query } from '../db/index.js';
import { getAdminUsageSummary, listTenants } from '../db/queries.js';
import { demoExpenses, type DemoExpense } from '../demo/fixtures.js';

const BILLED_STATUSES = ['active'];

export async function listExpenses(): Promise<any[]> {
  if (!dbEnabled) return [...demoExpenses].sort((a, b) => b.monthly_jpy - a.monthly_jpy);
  return query<any>(`select * from operator_expenses order by monthly_jpy desc`);
}

export async function createExpense(input: { label: string; category?: string; monthly_jpy: number }) {
  if (!dbEnabled) {
    const row: DemoExpense = { id: `ex-${Math.random().toString(36).slice(2, 8)}`, label: input.label, category: input.category ?? 'other', monthly_jpy: Math.round(input.monthly_jpy) || 0, created_at: new Date().toISOString() };
    demoExpenses.push(row); return row;
  }
  const [row] = await query<any>(
    `insert into operator_expenses (label, category, monthly_jpy) values ($1,$2,$3) returning *`,
    [input.label, input.category ?? 'other', Math.round(input.monthly_jpy) || 0]);
  return row;
}

export async function deleteExpense(id: string): Promise<boolean> {
  if (!dbEnabled) {
    const i = demoExpenses.findIndex((e) => e.id === id);
    if (i === -1) return false; demoExpenses.splice(i, 1); return true;
  }
  const rows = await query(`delete from operator_expenses where id=$1 returning id`, [id]);
  return rows.length > 0;
}

/** 指定月の損益計算書を組み立てる。 */
export async function computePnl(month?: string) {
  const [usage, tenants, expenses] = await Promise.all([getAdminUsageSummary(month), listTenants(), listExpenses()]);
  const statusById = new Map<string, string>();
  for (const t of tenants) statusById.set(t.id, t.status);

  let baseRevenue = 0, overageRevenue = 0, costAi = 0, costTransfer = 0;
  let trialCostAi = 0; // 参考：無償トライアルにかかっている原価
  for (const t of usage.tenants) {
    const billed = BILLED_STATUSES.includes(statusById.get(t.tenant_id) ?? 'active');
    const base = t.plan?.base_jpy ?? 0;
    if (billed) {
      baseRevenue += base;
      overageRevenue += Math.max(0, (t.revenue_jpy ?? 0) - base);
    } else {
      trialCostAi += t.cost?.ai_jpy ?? 0;
    }
    costAi += t.cost?.ai_jpy ?? 0;
    costTransfer += t.cost?.transfer_jpy ?? 0;
  }
  const revenue = Math.round(baseRevenue + overageRevenue);
  const cogs = Math.round(costAi + costTransfer);
  const grossProfit = revenue - cogs;
  const opexTotal = Math.round(expenses.reduce((s, e) => s + Number(e.monthly_jpy), 0));
  const operatingProfit = grossProfit - opexTotal;
  const pct = (n: number) => (revenue > 0 ? Math.round((n / revenue) * 1000) / 10 : 0);

  // 経費のカテゴリ別内訳
  const opexByCategory: Record<string, number> = {};
  for (const e of expenses) opexByCategory[e.category] = (opexByCategory[e.category] ?? 0) + Number(e.monthly_jpy);

  return {
    month: usage.month,
    revenue: { total: revenue, base: Math.round(baseRevenue), overage: Math.round(overageRevenue) },
    cogs: { total: cogs, ai: Math.round(costAi), transfer: Math.round(costTransfer), trial_ai: Math.round(trialCostAi) },
    gross_profit: grossProfit,
    gross_margin_rate: pct(grossProfit),
    opex: { total: opexTotal, by_category: opexByCategory, items: expenses },
    operating_profit: operatingProfit,
    operating_margin_rate: pct(operatingProfit),
    tenants_billed: usage.tenants.filter((t: any) => BILLED_STATUSES.includes(statusById.get(t.tenant_id) ?? 'active')).length,
  };
}
