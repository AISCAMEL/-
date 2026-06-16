"use client";

import { useEffect, useState } from "react";

interface OrderPnl {
  orderId: string;
  orderedAt: string;
  buyerName: string;
  itemTitle: string;
  quantity: number;
  revenue: number;
  supplierCost: number;
  platformFee: number;
  profit: number;
  status: string;
}
interface PnlSummary {
  orderCount: number;
  revenue: number;
  supplierCost: number;
  platformFee: number;
  profit: number;
  avgMarginRate: number;
  byStatus: Record<string, number>;
}

const yen = (n: number) => `¥${n.toLocaleString()}`;
const STATUS_LABEL: Record<string, string> = {
  received: "受注",
  fulfilling: "手配中",
  ordered_to_supplier: "仕入発注済",
  shipped: "発送済",
  completed: "完了",
  cancelled: "キャンセル",
};
const STATUS_COLOR: Record<string, string> = {
  received: "#2563eb",
  fulfilling: "#ca8a04",
  ordered_to_supplier: "#7c3aed",
  shipped: "#0891b2",
  completed: "#16a34a",
  cancelled: "#9ca3af",
};

export default function OrdersPage() {
  const [orders, setOrders] = useState<OrderPnl[]>([]);
  const [pnl, setPnl] = useState<PnlSummary | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([
      fetch("/api/orders").then((r) => r.json()),
      fetch("/api/pnl").then((r) => r.json()),
    ])
      .then(([o, p]) => {
        if (o.orders) setOrders(o.orders);
        if (p.orderCount !== undefined) setPnl(p);
        if (o.error || p.error) setError("Hub API へ接続できません（未起動の可能性）");
      })
      .catch(() => setError("受注データの取得に失敗（Hub API 未起動の可能性）"));
  }, []);

  const cards = pnl
    ? [
        { label: "売上", value: yen(pnl.revenue), color: "#111" },
        { label: "仕入原価", value: yen(pnl.supplierCost), color: "#b45309" },
        { label: "手数料", value: yen(pnl.platformFee), color: "#6b7280" },
        { label: "利益", value: yen(pnl.profit), color: "#16a34a" },
        { label: "平均利益率", value: `${(pnl.avgMarginRate * 100).toFixed(1)}%`, color: "#16a34a" },
        { label: "受注件数", value: `${pnl.orderCount}件`, color: "#111" },
      ]
    : [];

  return (
    <div>
      <p style={{ marginBottom: 16 }}>
        <a href="/" style={{ color: "#2563eb" }}>← ダッシュボード</a>
      </p>
      <h1>受注 / 損益</h1>
      {error && <p style={{ color: "#dc2626" }}>{error}</p>}

      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(150px, 1fr))", gap: 12, margin: "16px 0 28px" }}>
        {cards.map((c) => (
          <div key={c.label} style={{ padding: 16, border: "1px solid #e5e7eb", borderRadius: 12 }}>
            <div style={{ fontSize: 13, color: "#777" }}>{c.label}</div>
            <div style={{ fontSize: 22, fontWeight: 700, color: c.color }}>{c.value}</div>
          </div>
        ))}
      </div>

      {orders.length > 0 && (
        <table style={{ borderCollapse: "collapse", width: "100%", fontSize: 14 }}>
          <thead>
            <tr style={{ textAlign: "left", borderBottom: "2px solid #e5e7eb" }}>
              {["注文ID", "日付", "購入者", "商品", "数量", "売上", "原価", "手数料", "利益", "状態"].map((h) => (
                <th key={h} style={{ padding: "8px 6px" }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {orders.map((o) => (
              <tr key={o.orderId} style={{ borderBottom: "1px solid #f0f0f0", opacity: o.status === "cancelled" ? 0.5 : 1 }}>
                <td style={{ padding: "8px 6px", color: "#888" }}>{o.orderId}</td>
                <td style={{ padding: "8px 6px" }}>{o.orderedAt}</td>
                <td style={{ padding: "8px 6px" }}>{o.buyerName}</td>
                <td style={{ padding: "8px 6px" }}>{o.itemTitle}</td>
                <td style={{ padding: "8px 6px" }}>{o.quantity}</td>
                <td style={{ padding: "8px 6px" }}>{yen(o.revenue)}</td>
                <td style={{ padding: "8px 6px", color: "#b45309" }}>{yen(o.supplierCost)}</td>
                <td style={{ padding: "8px 6px", color: "#6b7280" }}>{yen(o.platformFee)}</td>
                <td style={{ padding: "8px 6px", fontWeight: 600, color: o.status === "cancelled" ? "#9ca3af" : "#16a34a" }}>{yen(o.profit)}</td>
                <td style={{ padding: "8px 6px" }}>
                  <span style={{ background: STATUS_COLOR[o.status] ?? "#999", color: "#fff", padding: "2px 8px", borderRadius: 999, fontSize: 12 }}>
                    {STATUS_LABEL[o.status] ?? o.status}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
