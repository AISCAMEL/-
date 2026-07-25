import { describe, expect, it } from "vitest";
import {
  analyzeMarkets,
  buildProfitScenarios,
  profitabilityAt,
  summarize,
  type MarketListing,
} from "./market-analysis.js";

const listings: MarketListing[] = [
  { marketId: "amazon", title: "A1", price: 3000 },
  { marketId: "amazon", title: "A2", price: 3500 },
  { marketId: "rakuten", title: "R1", price: 2800 },
  { marketId: "rakuten", title: "R2", price: 3200 },
  { marketId: "rakuten", title: "R3", price: 4000 },
];

describe("summarize", () => {
  it("最小・最大・中央値・平均を算出する", () => {
    const s = summarize(listings, "all");
    expect(s.sampleCount).toBe(5);
    expect(s.min).toBe(2800);
    expect(s.max).toBe(4000);
    expect(s.median).toBe(3200);
    expect(s.average).toBe(3300);
  });

  it("空配列では0を返す", () => {
    expect(summarize([], "amazon").sampleCount).toBe(0);
  });
});

describe("analyzeMarkets", () => {
  it("市場別に分けて集計する", () => {
    const r = analyzeMarkets(listings);
    expect(r.byMarket.amazon.sampleCount).toBe(2);
    expect(r.byMarket.rakuten.sampleCount).toBe(3);
    expect(r.overall.sampleCount).toBe(5);
  });
});

describe("profitabilityAt", () => {
  it("利益率とROIを計算する", () => {
    // 売値3000, 原価2000, 手数料率0.036 → 手数料108, 利益892
    const p = profitabilityAt("test", 3000, 2000, 0.036);
    expect(p.platformFee).toBe(108);
    expect(p.profit).toBe(892);
    expect(p.marginRate).toBeCloseTo(0.2973, 3);
    expect(p.roi).toBeCloseTo(0.446, 2);
  });
});

describe("buildProfitScenarios", () => {
  it("中央値・最安値・undercut の3シナリオを返す", () => {
    const { overall } = analyzeMarkets(listings);
    const scenarios = buildProfitScenarios(overall, 2000, 0.036);
    expect(scenarios.map((s) => s.label)).toEqual(["市場中央値", "市場最安値", "最安値-5%"]);
    expect(scenarios[0]!.sellPrice).toBe(3200);
  });
});
