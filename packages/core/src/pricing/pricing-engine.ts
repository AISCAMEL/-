import type { Currency } from "../domain/ids.js";

/** 価格計算ルール。商品/カテゴリ/チャネル単位で上書き可能。 */
export interface PriceRule {
  /** 為替レート（1 仕入れ通貨 → JPY）。バッファ込みで保持。 */
  fxRateToJpy: number;
  /** 関税率（例: 0.1 = 10%）。 */
  dutyRate: number;
  /** 1商品あたりの国際送料配賦額（JPY）。 */
  intlShippingPerUnit: number;
  /** 国内送料（JPY）。 */
  domesticShipping: number;
  /** プラットフォーム手数料率（例: BASE の決済手数料 0.036 など）。 */
  platformFeeRate: number;
  /** 目標利益。率（rate）か固定額（fixed）のどちらか。 */
  margin: { type: "rate"; value: number } | { type: "fixed"; value: number };
  /** 端数処理単位（例: 10 = 10円単位で切り上げ）。 */
  roundTo: number;
  /** 最低利益ガード（JPY）。これを下回る場合は出品不可と判定する。 */
  minProfit: number;
}

export interface PriceInput {
  /** 仕入原価（仕入れ通貨建て）。 */
  cost: number;
  costCurrency: Currency;
}

export interface PriceResult {
  /** 推奨販売価格（JPY・端数処理後）。 */
  sellPrice: number;
  /** 原価系コスト合計（JPY）。 */
  landedCost: number;
  /** 想定利益（JPY）。 */
  profit: number;
  /** 最低利益を満たすか。 */
  meetsMinProfit: boolean;
}

const roundUpTo = (value: number, unit: number): number =>
  unit <= 0 ? value : Math.ceil(value / unit) * unit;

/**
 * 販売価格を算出する。外部I/Oを持たない純粋関数。
 *
 *   landedCost = 仕入原価×為替×(1+関税) + 国際送料 + 国内送料
 *   税抜売価   = (landedCost + 利益) / (1 - 手数料率)   ← 手数料で割り戻し
 */
export function calculateSellPrice(input: PriceInput, rule: PriceRule): PriceResult {
  const costJpy = input.cost * rule.fxRateToJpy * (1 + rule.dutyRate);
  const landedCost = costJpy + rule.intlShippingPerUnit + rule.domesticShipping;

  const targetProfit =
    rule.margin.type === "fixed" ? rule.margin.value : landedCost * rule.margin.value;

  const beforeFee = landedCost + targetProfit;
  const feeAdjusted = beforeFee / (1 - rule.platformFeeRate);
  const sellPrice = roundUpTo(feeAdjusted, rule.roundTo);

  // 端数処理後の実利益を逆算
  const platformFee = sellPrice * rule.platformFeeRate;
  const profit = sellPrice - platformFee - landedCost;

  return {
    sellPrice,
    landedCost: Math.round(landedCost),
    profit: Math.round(profit),
    meetsMinProfit: profit >= rule.minProfit,
  };
}
