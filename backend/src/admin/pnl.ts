// 運営の損益計算書（P&L）。売上 − 売上原価 = 粗利 − 販管費(固定経費) = 営業利益。
// トライアル/休止テナントは売上0（原価は発生）として正直に計上する。
import { dbEnabled, query } from '../db/index.js';
import { getAdminUsageSummary, listTenants } from '../db/queries.js';
import { demoExpenses, type DemoExpense } from '../demo/fixtures.js';
import { PLANS } from '../billing/rates.js';

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

  const tenantsBilled = usage.tenants.filter((t: any) => BILLED_STATUSES.includes(statusById.get(t.tenant_id) ?? 'active')).length;

  // 損益分岐点：固定費(販管費) ÷ 限界利益率(粗利/売上)。あと何社で黒字かも算出。
  const contributionRatio = revenue > 0 ? grossProfit / revenue : 0;
  const breakEvenRevenue = contributionRatio > 0 ? Math.round(opexTotal / contributionRatio) : null;
  const avgContributionPerTenant = tenantsBilled > 0 ? grossProfit / tenantsBilled : 0;
  const breakEvenTenants = avgContributionPerTenant > 0 ? Math.ceil(opexTotal / avgContributionPerTenant) : null;
  const additionalTenantsNeeded = breakEvenTenants !== null ? Math.max(0, breakEvenTenants - tenantsBilled) : null;

  return {
    month: usage.month,
    revenue: { total: revenue, base: Math.round(baseRevenue), overage: Math.round(overageRevenue) },
    cogs: { total: cogs, ai: Math.round(costAi), transfer: Math.round(costTransfer), trial_ai: Math.round(trialCostAi) },
    gross_profit: grossProfit,
    gross_margin_rate: pct(grossProfit),
    opex: { total: opexTotal, by_category: opexByCategory, items: expenses },
    operating_profit: operatingProfit,
    operating_margin_rate: pct(operatingProfit),
    tenants_billed: tenantsBilled,
    break_even: {
      is_profitable: operatingProfit >= 0,
      contribution_margin_rate: Math.round(contributionRatio * 1000) / 10,
      break_even_revenue: breakEvenRevenue,
      break_even_tenants: breakEvenTenants,
      additional_tenants_needed: additionalTenantsNeeded,
      avg_revenue_per_tenant: tenantsBilled > 0 ? Math.round(revenue / tenantsBilled) : 0,
      margin_of_safety_jpy: breakEvenRevenue !== null ? revenue - breakEvenRevenue : null,
    },
  };
}

/**
 * 月次推移（推定）。過去Nヶ月について、その月に稼働していたテナントの確定MRRを基に
 * 現在の粗利率・当月販管費で営業利益を推定する。履歴が薄いデモでも傾向が見える。
 */
export async function computeTrend(months = 6) {
  const [tenants, expenses, current] = await Promise.all([listTenants(), listExpenses(), computePnl()]);
  const opexTotal = expenses.reduce((s, e) => s + Number(e.monthly_jpy), 0);
  const grossRate = current.gross_margin_rate / 100; // 現在の粗利率で近似
  const now = new Date();
  const out: any[] = [];
  for (let i = months - 1; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    const monthEnd = new Date(now.getFullYear(), now.getMonth() - i + 1, 0, 23, 59, 59);
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    // その月末時点で「稼働(active)」かつ契約/登録がその月までに始まっているテナント
    let mrr = 0, count = 0;
    for (const t of tenants) {
      if (t.status !== 'active') continue;
      const startStr = t.contract_started_at || t.created_at;
      const start = startStr ? new Date(startStr) : null;
      if (start && start <= monthEnd) { mrr += planBase(t.plan); count++; }
    }
    const grossEst = Math.round(mrr * (grossRate || 1));
    out.push({ month: key, mrr: Math.round(mrr), tenants: count, operating_profit_est: grossEst - Math.round(opexTotal) });
  }
  return { months: out, opex_monthly: Math.round(opexTotal), gross_margin_rate: current.gross_margin_rate };
}

function planBase(plan?: string | null): number {
  return PLANS[plan ?? 'starter']?.baseJpy ?? 0;
}
