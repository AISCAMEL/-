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

// 機能キー → 表示名（プランで使える機能の範囲を機能別に管理）。
export const FEATURE_CATALOG: Record<string, string> = {
  reception:       'AI電話受付（24時間）',
  faq:             'FAQ自動回答',
  summary:         '通話要約・文字起こし',
  email_notify:    'メール通知',
  appointment:     '予約受付（査定・来店）',
  transfer:        '人間へ転送',
  contacts:        '連絡先CRM（カテゴリ・メモ）',
  bulk_email:      '一斉メール',
  outbound:        'AI営業・自動架電',
  caller_rules:    '発信者ルール（ブロック/個別案内）',
  slack:           'Slack通知',
  csv_export:      'CSV出力',
  calendar:        'Googleカレンダー連携（重複防止）',
  multi_number:    '複数電話番号',
  analytics:       '高度な分析ダッシュボード',
  routing:         '担当者振り分け',
  priority_support:'優先サポート',
};

export interface PlanDef {
  label: string;
  tagline: string;          // 誰向けか一言
  allowanceMin: number;     // 月間の込み分数
  baseJpy: number;          // 月額基本料
  overageJpyPerMin: number; // 超過（従量）単価
  features: string[];       // 利用可能な機能キー（下位プランを内包）
}

// 低額の月額基本料＋通話分の従量課金。プランは「使える機能の範囲」で分ける（機能別）。
// 原価は約¥12.6/分。従量単価はいずれも粗利60%以上を確保。
const RECEPTION_FEATURES = ['reception', 'faq', 'summary', 'email_notify', 'appointment', 'transfer'];
const SALES_FEATURES = [...RECEPTION_FEATURES, 'contacts', 'bulk_email', 'outbound', 'caller_rules', 'slack', 'csv_export'];
const PRO_FEATURES = [...SALES_FEATURES, 'calendar', 'multi_number', 'analytics', 'routing', 'priority_support'];

export const PLANS: Record<string, PlanDef> = {
  starter:    { label: '受付プラン',   tagline: '電話番の代わり（インバウンド）', allowanceMin: 30,   baseJpy: 2980,  overageJpyPerMin: 60, features: RECEPTION_FEATURES },
  business:   { label: '営業プラン',   tagline: '受付＋集客・追客まで',           allowanceMin: 150,  baseJpy: 7980,  overageJpyPerMin: 45, features: SALES_FEATURES },
  pro:        { label: '統合プラン',   tagline: '全機能・多拠点・高度分析',       allowanceMin: 500,  baseJpy: 16800, overageJpyPerMin: 35, features: PRO_FEATURES },
  enterprise: { label: 'エンタープライズ', tagline: '大規模・個別要件',            allowanceMin: 2000, baseJpy: 0,    overageJpyPerMin: 30, features: PRO_FEATURES },
};

export function planDef(plan?: string | null): PlanDef {
  return PLANS[plan ?? 'starter'] ?? PLANS.starter;
}

/** プランがその機能を使えるか。 */
export function planHasFeature(plan: string | null | undefined, feature: string): boolean {
  return planDef(plan).features.includes(feature);
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
