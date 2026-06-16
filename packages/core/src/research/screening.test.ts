import { describe, expect, it } from "vitest";
import { scoreProduct, screen, type ScreenedItem } from "./screening.js";

describe("scoreProduct", () => {
  it("高利益率・高需要・適正競合は A 判定", () => {
    const r = scoreProduct({
      marginRate: 0.45,
      profit: 2000,
      sampleCount: 6,
      avgReviewCount: 120,
      stockStable: true,
    });
    expect(r.grade).toBe("A");
    expect(r.score).toBeGreaterThanOrEqual(70);
  });

  it("規約ブロックは問答無用で C / 0点", () => {
    const r = scoreProduct({ marginRate: 0.9, profit: 9999, sampleCount: 3, hasComplianceBlock: true });
    expect(r.grade).toBe("C");
    expect(r.score).toBe(0);
  });

  it("低利益率・需要なしは低スコア", () => {
    const r = scoreProduct({ marginRate: 0.05, profit: 100, sampleCount: 0 });
    expect(r.score).toBeLessThan(45);
    expect(r.grade).toBe("C");
  });
});

describe("screen", () => {
  const items: ScreenedItem[] = [
    {
      key: "a",
      keyword: "A",
      landedCost: 1000,
      chosen: { label: "中央値", sellPrice: 3000, landedCost: 1000, platformFee: 100, profit: 1900, marginRate: 0.63, roi: 1.9 },
      marketSampleCount: 5,
      score: { score: 85, grade: "A", reasons: [] },
    },
    {
      key: "b",
      keyword: "B",
      landedCost: 2000,
      chosen: { label: "中央値", sellPrice: 2500, landedCost: 2000, platformFee: 90, profit: 410, marginRate: 0.16, roi: 0.2 },
      marketSampleCount: 5,
      score: { score: 40, grade: "C", reasons: [] },
    },
  ];

  it("最低利益率で足切りする", () => {
    const r = screen(items, { minMarginRate: 0.3 });
    expect(r.map((i) => i.key)).toEqual(["a"]);
  });

  it("スコア降順に並ぶ", () => {
    const r = screen(items);
    expect(r[0]!.key).toBe("a");
  });

  it("最低グレードでも絞れる", () => {
    expect(screen(items, { minGrade: "B" }).map((i) => i.key)).toEqual(["a"]);
  });
});
