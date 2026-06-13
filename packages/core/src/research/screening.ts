import type { Profitability } from "./market-analysis.js";

/** スコアリングの入力（1商品分）。 */
export interface ScoreInput {
  /** 採用売値での利益率（profit / sellPrice）。 */
  marginRate: number;
  /** 採用売値での利益額（JPY）。 */
  profit: number;
  /** 市場の出品数（流動性・競合の代理指標）。 */
  sampleCount: number;
  /** 平均レビュー数（需要の代理指標・任意）。 */
  avgReviewCount?: number;
  /** 規約・法令で出品ブロックがあるか。 */
  hasComplianceBlock?: boolean;
  /** 仕入れ在庫が安定しているか。 */
  stockStable?: boolean;
}

export interface ScoreResult {
  /** 0〜100 の総合スコア。 */
  score: number;
  grade: "A" | "B" | "C";
  reasons: string[];
}

const clamp = (v: number, min: number, max: number): number => Math.max(min, Math.min(max, v));

/**
 * 商品を 0〜100 で採点する。利益率・需要・競合・在庫・規約を加味。
 * 規約ブロックがあれば問答無用で C（出品不可）。
 */
export function scoreProduct(input: ScoreInput): ScoreResult {
  const reasons: string[] = [];

  if (input.hasComplianceBlock) {
    return { score: 0, grade: "C", reasons: ["規約/法令ブロックあり（出品不可）"] };
  }

  // 利益率（最大60点）: 0% → 0点, 40%以上 → 満点
  const marginScore = clamp(input.marginRate / 0.4, 0, 1) * 60;
  reasons.push(`利益率 ${(input.marginRate * 100).toFixed(1)}% → ${marginScore.toFixed(0)}/60`);

  // 需要（最大25点）: 平均レビュー数 0 → 0点, 100件以上 → 満点
  const review = input.avgReviewCount ?? 0;
  const demandScore = clamp(review / 100, 0, 1) * 25;
  reasons.push(`平均レビュー ${review.toFixed(0)}件 → ${demandScore.toFixed(0)}/25`);

  // 競合（最大15点）: 1〜8件が理想。0件=データ不足、多すぎ=飽和で減点
  let compScore: number;
  if (input.sampleCount === 0) {
    compScore = 0;
    reasons.push("競合データなし → 0/15");
  } else if (input.sampleCount <= 8) {
    compScore = 15;
    reasons.push(`競合 ${input.sampleCount}件（適正）→ 15/15`);
  } else {
    compScore = clamp(1 - (input.sampleCount - 8) / 40, 0, 1) * 15;
    reasons.push(`競合 ${input.sampleCount}件（飽和気味）→ ${compScore.toFixed(0)}/15`);
  }

  // 在庫安定ボーナス/減点
  let stockAdj = 0;
  if (input.stockStable === false) {
    stockAdj = -10;
    reasons.push("在庫不安定 → -10");
  }

  const score = clamp(Math.round(marginScore + demandScore + compScore + stockAdj), 0, 100);
  const grade: ScoreResult["grade"] = score >= 70 ? "A" : score >= 45 ? "B" : "C";
  return { score, grade, reasons };
}

/** スクリーニング1件の結果。 */
export interface ScreenedItem {
  key: string;
  keyword: string;
  landedCost: number | null;
  /** 採用した代表シナリオ（通常は市場中央値）。 */
  chosen: Profitability | null;
  marketSampleCount: number;
  score: ScoreResult;
}

/** 最低利益率で足切りし、スコア降順に並べる。 */
export function screen(
  items: ScreenedItem[],
  opts: { minMarginRate?: number; minGrade?: ScoreResult["grade"] } = {},
): ScreenedItem[] {
  const gradeRank = { A: 3, B: 2, C: 1 } as const;
  const minMargin = opts.minMarginRate ?? 0;
  const minGradeRank = opts.minGrade ? gradeRank[opts.minGrade] : 0;

  return items
    .filter((it) => (it.chosen?.marginRate ?? -1) >= minMargin)
    .filter((it) => gradeRank[it.score.grade] >= minGradeRank)
    .sort((a, b) => b.score.score - a.score.score);
}
