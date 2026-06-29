// 運営側の経営管理：確定MRR・プラン別売上・契約アラート（解約/更新/滞納）。
import { listTenants } from '../db/queries.js';
import { PLANS, planDef } from '../billing/rates.js';

const ACTIVE_STATUSES = ['active'];

/** 確定MRR（稼働テナントの月額合計）とプラン別内訳。利用超過は含まない安定指標。 */
export async function committedMrr() {
  const tenants = await listTenants();
  const by_plan: Record<string, { label: string; count: number; mrr_jpy: number }> = {};
  for (const key of Object.keys(PLANS)) by_plan[key] = { label: PLANS[key].label, count: 0, mrr_jpy: 0 };
  let committed = 0, activeCount = 0, trialCount = 0;
  for (const t of tenants) {
    if (t.status === 'trial') trialCount++;
    if (!ACTIVE_STATUSES.includes(t.status)) continue;
    activeCount++;
    const def = planDef(t.plan);
    by_plan[t.plan] ??= { label: def.label, count: 0, mrr_jpy: 0 };
    by_plan[t.plan].count++;
    by_plan[t.plan].mrr_jpy += def.baseJpy;
    committed += def.baseJpy;
  }
  return {
    committed_mrr_jpy: committed,
    arr_jpy: committed * 12,
    active_count: activeCount,
    trial_count: trialCount,
    by_plan,
  };
}

function daysUntil(dateStr: string | null | undefined): number | null {
  if (!dateStr) return null;
  const d = new Date(dateStr + (dateStr.length === 10 ? 'T23:59:59+09:00' : ''));
  if (isNaN(d.getTime())) return null;
  return Math.ceil((d.getTime() - Date.now()) / 86400_000);
}

/** 契約アラート：トライアル終了が近い／期限切れ、入金滞納のテナントを抽出。 */
export async function renewalAlerts(withinDays = 14) {
  const tenants = await listTenants();
  const trial_ending: any[] = [];
  const trial_expired: any[] = [];
  const overdue: any[] = [];
  for (const t of tenants) {
    if (t.status === 'trial') {
      const d = daysUntil(t.trial_ends_at);
      if (d !== null) {
        if (d < 0) trial_expired.push({ id: t.id, company_name: t.company_name, plan: t.plan, days: d, trial_ends_at: t.trial_ends_at });
        else if (d <= withinDays) trial_ending.push({ id: t.id, company_name: t.company_name, plan: t.plan, days: d, trial_ends_at: t.trial_ends_at });
      }
    }
    if (t.payment_status === 'overdue') overdue.push({ id: t.id, company_name: t.company_name, plan: t.plan });
  }
  trial_ending.sort((a, b) => a.days - b.days);
  return { trial_ending, trial_expired, overdue, total: trial_ending.length + trial_expired.length + overdue.length };
}
