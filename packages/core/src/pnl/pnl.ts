import type { OrderStatus } from "../domain/ids.js";

/** 1注文あたりの損益（売上・原価・手数料・利益）。 */
export interface OrderPnl {
  orderId: string;
  orderedAt: string; // ISO 日付
  buyerName: string;
  itemTitle: string;
  quantity: number;
  /** 売上（JPY）。 */
  revenue: number;
  /** 仕入原価合計（JPY・着地原価ベース）。 */
  supplierCost: number;
  /** プラットフォーム手数料（JPY）。 */
  platformFee: number;
  /** 利益（JPY）。 */
  profit: number;
  status: OrderStatus;
}

/** 損益サマリ。 */
export interface PnlSummary {
  orderCount: number;
  revenue: number;
  supplierCost: number;
  platformFee: number;
  profit: number;
  /** 平均利益率（利益 / 売上）。 */
  avgMarginRate: number;
  /** ステータス別の件数。 */
  byStatus: Record<string, number>;
}

/** 売上・原価・手数料率から、手数料と利益を計算して OrderPnl を組み立てる。 */
export function buildOrderPnl(
  input: Omit<OrderPnl, "platformFee" | "profit">,
  platformFeeRate: number,
): OrderPnl {
  const platformFee = Math.round(input.revenue * platformFeeRate);
  const profit = Math.round(input.revenue - platformFee - input.supplierCost);
  return { ...input, platformFee, profit };
}

/** 注文群から損益サマリを集計する。キャンセルは売上・利益から除外する。 */
export function summarizePnl(orders: OrderPnl[]): PnlSummary {
  const byStatus: Record<string, number> = {};
  for (const o of orders) byStatus[o.status] = (byStatus[o.status] ?? 0) + 1;

  const active = orders.filter((o) => o.status !== "cancelled");
  const revenue = active.reduce((s, o) => s + o.revenue, 0);
  const supplierCost = active.reduce((s, o) => s + o.supplierCost, 0);
  const platformFee = active.reduce((s, o) => s + o.platformFee, 0);
  const profit = active.reduce((s, o) => s + o.profit, 0);

  return {
    orderCount: orders.length,
    revenue,
    supplierCost,
    platformFee,
    profit,
    avgMarginRate: revenue > 0 ? Number((profit / revenue).toFixed(4)) : 0,
    byStatus,
  };
}
