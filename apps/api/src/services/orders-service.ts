import { buildOrderPnl, summarizePnl, type OrderPnl, type PnlSummary } from "@hub/core";
import { DEFAULT_PRICE_RULE } from "./listing-service.js";

/** サンプル受注（BASE が mock のため。live では BASE から取得し原価を突合する）。 */
const SAMPLE_ORDERS: Omit<OrderPnl, "platformFee" | "profit">[] = [
  { orderId: "base_1001", orderedAt: "2026-06-15", buyerName: "佐藤 花子", itemTitle: "猫じゃらし 電動 自動回転", quantity: 1, revenue: 2980, supplierCost: 1100, status: "completed" },
  { orderId: "base_1002", orderedAt: "2026-06-15", buyerName: "鈴木 一郎", itemTitle: "キャットタワー 据え置き 大型", quantity: 1, revenue: 7980, supplierCost: 3600, status: "shipped" },
  { orderId: "base_1003", orderedAt: "2026-06-14", buyerName: "田中 美咲", itemTitle: "猫 爪とぎ ダンボール 2個セット", quantity: 2, revenue: 3600, supplierCost: 1400, status: "ordered_to_supplier" },
  { orderId: "base_1004", orderedAt: "2026-06-14", buyerName: "高橋 健", itemTitle: "自動給餌器 タイマー式", quantity: 1, revenue: 5480, supplierCost: 2500, status: "completed" },
  { orderId: "base_1005", orderedAt: "2026-06-13", buyerName: "伊藤 さくら", itemTitle: "猫 ベッド ふわふわ ドーム型", quantity: 1, revenue: 2680, supplierCost: 980, status: "received" },
  { orderId: "base_1006", orderedAt: "2026-06-13", buyerName: "渡辺 大輔", itemTitle: "猫 首輪 鈴付き 安全バックル", quantity: 3, revenue: 2340, supplierCost: 900, status: "completed" },
  { orderId: "base_1007", orderedAt: "2026-06-12", buyerName: "山本 由美", itemTitle: "猫 トンネル 折りたたみ", quantity: 1, revenue: 1980, supplierCost: 750, status: "cancelled" },
];

/** 全受注を損益付きで返す。 */
export function getOrders(): OrderPnl[] {
  return SAMPLE_ORDERS.map((o) => buildOrderPnl(o, DEFAULT_PRICE_RULE.platformFeeRate));
}

/** 損益サマリを返す。 */
export function getPnl(): PnlSummary {
  return summarizePnl(getOrders());
}
