// 課金・原価レートの定義。docs/cost-analysis.md と一致させること。
// 数値は環境変数で上書き可能（為替・レート改定に対応）。

export const USD_JPY = Number(process.env.USD_JPY ?? 155);

// 原価（USD/分）
export const COST_USD_PER_MIN = {
  relay: Number(process.env.RATE_RELAY ?? 0.07),        // Twilio Conversation Relay
  inbound: Number(process.env.RATE_INBOUND ?? 0.0085),  // 着信(日本ローカル)
  llm: Number(process.env.RATE_LLM ?? 0.003),           // gpt-4o-mini テキスト
  transferMobile: Number(process.env.RATE_TRANSFER ?? 0.08), // 転送先=携帯への発信
  recording: Number(process.env.RATE_RECORDING ?? 0.0025),
};

// AI完結通話の1分あたり原価(USD) = relay + inbound + llm
export const AI_COST_USD_PER_MIN =
  COST_USD_PER_MIN.relay + COST_USD_PER_MIN.inbound + COST_USD_PER_MIN.llm;

export interface PlanDef {
  label: string;
  allowanceMin: number;     // 月間上限分数
  baseJpy: number;          // 月額
  overageJpyPerMin: number; // 超過単価
}

// 低額の月額基本料＋通話分の従量課金モデル。基本料に含まれる分を超えたら1分ごとに加算。
// 原価は約¥12.6/分。従量単価はいずれも粗利60%以上を確保。
export const PLANS: Record<string, PlanDef> = {
  starter:    { label: 'ライト',        allowanceMin: 30,   baseJpy: 2980,  overageJpyPerMin: 60 },
  business:   { label: 'スタンダード',  allowanceMin: 150,  baseJpy: 6980,  overageJpyPerMin: 45 },
  pro:        { label: 'プロ',          allowanceMin: 500,  baseJpy: 14800, overageJpyPerMin: 35 },
  enterprise: { label: 'エンタープライズ', allowanceMin: 2000, baseJpy: 0,   overageJpyPerMin: 30 },
};

export function planDef(plan?: string | null): PlanDef {
  return PLANS[plan ?? 'starter'] ?? PLANS.starter;
}

/** 秒→課金対象分（Twilio同様、切り上げ・最低1分）。 */
export function billableMinutes(durationSec?: number | null): number {
  if (!durationSec || durationSec <= 0) return 0;
  return Math.ceil(durationSec / 60);
}

/** AI完結通話の原価(円)。転送・録音は別途加算する。 */
export function aiCostJpy(billableMin: number): number {
  return round2(billableMin * AI_COST_USD_PER_MIN * USD_JPY);
}

/** 転送通話で発生する追加発信レッグの原価(円)。携帯転送を保守的に想定。 */
export function transferAddCostJpy(billableMin: number): number {
  return round2(billableMin * COST_USD_PER_MIN.transferMobile * USD_JPY);
}

/** 月間の見込み売上(円) = 月額 + 超過分 × 超過単価。 */
export function monthlyRevenueJpy(plan: PlanDef, totalMinutes: number): number {
  const overage = Math.max(0, totalMinutes - plan.allowanceMin);
  return plan.baseJpy + overage * plan.overageJpyPerMin;
}

function round2(n: number): number {
  return Math.round(n * 100) / 100;
}
