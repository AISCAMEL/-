import { describe, expect, it } from "vitest";
import { calculateSellPrice, type PriceRule } from "./pricing-engine.js";

const rule: PriceRule = {
  fxRateToJpy: 21, // 1 CNY ≒ 21 JPY（バッファ込み）
  dutyRate: 0.1,
  intlShippingPerUnit: 300,
  domesticShipping: 500,
  platformFeeRate: 0.036,
  margin: { type: "rate", value: 0.3 },
  roundTo: 10,
  minProfit: 300,
};

describe("calculateSellPrice", () => {
  it("原価系コストを正しく積み上げる", () => {
    const r = calculateSellPrice({ cost: 100, costCurrency: "CNY" }, rule);
    // 100×21×1.1 = 2310, +300 +500 = 3110
    expect(r.landedCost).toBe(3110);
  });

  it("手数料を割り戻し、端数を切り上げる", () => {
    const r = calculateSellPrice({ cost: 100, costCurrency: "CNY" }, rule);
    expect(r.sellPrice % 10).toBe(0);
    expect(r.sellPrice).toBeGreaterThan(r.landedCost);
  });

  it("最低利益を満たす場合は meetsMinProfit が true", () => {
    const r = calculateSellPrice({ cost: 100, costCurrency: "CNY" }, rule);
    expect(r.profit).toBeGreaterThanOrEqual(rule.minProfit);
    expect(r.meetsMinProfit).toBe(true);
  });

  it("固定利益ルールでも算出できる", () => {
    const fixed: PriceRule = { ...rule, margin: { type: "fixed", value: 1000 } };
    const r = calculateSellPrice({ cost: 50, costCurrency: "CNY" }, fixed);
    expect(r.sellPrice).toBeGreaterThan(0);
  });
});
