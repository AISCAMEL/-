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
  allowanceMin: number;     // 込み分数（本モデルは0＝無料通話分なし）
  baseJpy: number;          // 月額基本料（システム利用料・安く）
  perCallJpy: number;       // 着信対応料（AIが応対した着信1件ごと）
  overageJpyPerMin: number; // 通話単価（1分目から従量）
  features: string[];       // 利用可能な機能キー（下位プランを内包）
}

// 低額の基本料 ＋「着信1件いくら」＋「通話1分いくら」の細かい従量。無料分なし＝赤字にならない。
// 原価は約¥12.6/分（着信込み）。上位プランほど基本料は上げ、従量単価は下げる（大量利用向け）。
const RECEPTION_FEATURES = ['reception', 'faq', 'summary', 'email_notify', 'appointment', 'transfer'];
const SALES_FEATURES = [...RECEPTION_FEATURES, 'contacts', 'bulk_email', 'outbound', 'caller_rules', 'slack', 'csv_export'];
const PRO_FEATURES = [...SALES_FEATURES, 'calendar', 'multi_number', 'analytics', 'routing', 'priority_support'];

export const PLANS: Record<string, PlanDef> = {
  starter:    { label: '受付プラン',   tagline: '電話番の代わり（インバウンド）', allowanceMin: 0, baseJpy: 3980,  perCallJpy: 30, overageJpyPerMin: 30, features: RECEPTION_FEATURES },
  business:   { label: '営業プラン',   tagline: '受付＋集客・追客まで',           allowanceMin: 0, baseJpy: 6980,  perCallJpy: 30, overageJpyPerMin: 25, features: SALES_FEATURES },
  pro:        { label: '統合プラン',   tagline: '全機能・多拠点・高度分析',       allowanceMin: 0, baseJpy: 12800, perCallJpy: 20, overageJpyPerMin: 20, features: PRO_FEATURES },
  enterprise: { label: 'エンタープライズ', tagline: '大規模・個別要件',            allowanceMin: 0, baseJpy: 0,     perCallJpy: 20, overageJpyPerMin: 20, features: PRO_FEATURES },
};

// 電話番号オプション（種類で月額が変わる。取得は運営が代行）。
export interface NumberOption { key: string; label: string; monthlyJpy: number; note: string; }
export const NUMBER_OPTIONS: NumberOption[] = [
  { key: 'standard',   label: '標準番号（050 IP番号）',        monthlyJpy: 0,    note: '基本料に1つ込み' },
  { key: 'local_area', label: '市外局番（03・06・022等の地域番号）', monthlyJpy: 1000, note: '地域番号は取得・維持コストが高いため加算' },
  { key: 'toll_free',  label: 'フリーダイヤル（0120／0800）',   monthlyJpy: 2000, note: '着信側課金。企業の信頼感アップ' },
  { key: 'extra',      label: '追加番号（1本ごと）',            monthlyJpy: 1000, note: '拠点・用途別に増やせる' },
];

export function planDef(plan?: string | null): PlanDef {
  return PLANS[plan ?? 'starter'] ?? PLANS.starter;
}

/** 課金額(円) = 基本料 + 着信対応料×件数 + 通話料×分。 */
export function revenueJpy(plan: PlanDef, calls: number, minutes: number): number {
  return plan.baseJpy + Math.max(0, calls) * plan.perCallJpy + Math.max(0, minutes) * plan.overageJpyPerMin;
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
