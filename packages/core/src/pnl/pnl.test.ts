import { describe, expect, it } from "vitest";
import { buildOrderPnl, summarizePnl, type OrderPnl } from "./pnl.js";

describe("buildOrderPnl", () => {
  it("手数料と利益を計算する", () => {
    const o = buildOrderPnl(
      {
        orderId: "1",
        orderedAt: "2026-06-16",
        buyerName: "猫太郎",
        itemTitle: "猫じゃらし",
        quantity: 1,
        revenue: 3000,
        supplierCost: 1000,
        status: "completed",
      },
      0.036,
    );
    expect(o.platformFee).toBe(108);
    expect(o.profit).toBe(1892);
  });
});

describe("summarizePnl", () => {
  const orders: OrderPnl[] = [
    { orderId: "1", orderedAt: "2026-06-16", buyerName: "A", itemTitle: "x", quantity: 1, revenue: 3000, supplierCost: 1000, platformFee: 108, profit: 1892, status: "completed" },
    { orderId: "2", orderedAt: "2026-06-16", buyerName: "B", itemTitle: "y", quantity: 1, revenue: 2000, supplierCost: 800, platformFee: 72, profit: 1128, status: "shipped" },
    { orderId: "3", orderedAt: "2026-06-16", buyerName: "C", itemTitle: "z", quantity: 1, revenue: 5000, supplierCost: 2000, platformFee: 180, profit: 2820, status: "cancelled" },
  ];

  it("キャンセルを除外して集計する", () => {
    const s = summarizePnl(orders);
    expect(s.orderCount).toBe(3);
    expect(s.revenue).toBe(5000); // 3000+2000（cancelled除外）
    expect(s.profit).toBe(3020); // 1892+1128
    expect(s.byStatus.cancelled).toBe(1);
  });

  it("平均利益率を算出する", () => {
    const s = summarizePnl(orders);
    expect(s.avgMarginRate).toBeCloseTo(0.604, 2);
  });
});
