"use client";

import { useEffect, useState } from "react";
import SalesChart from "./sales-chart";

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
const STATUS_STYLE: Record<string, { bg: string; color: string; emoji: string }> = {
  received: { bg: "linear-gradient(135deg, #f472b6, #ec4899)", color: "#fff", emoji: "🐱" },
  fulfilling: { bg: "linear-gradient(135deg, #fde68a, #fbbf24)", color: "#92400e", emoji: "🐾" },
  ordered_to_supplier: { bg: "linear-gradient(135deg, #c4b5fd, #a78bfa)", color: "#fff", emoji: "📦" },
  shipped: { bg: "linear-gradient(135deg, #67e8f9, #22d3ee)", color: "#164e63", emoji: "🚀" },
  completed: { bg: "linear-gradient(135deg, #6ee7b7, #34d399)", color: "#fff", emoji: "✅" },
  cancelled: { bg: "#e5e7eb", color: "#6b7280", emoji: "✗" },
};

export default function OrdersPage() {
  const [orders, setOrders] = useState<OrderPnl[]>([]);
  const [pnl, setPnl] = useState<PnlSummary | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fulfillState, setFulfillState] = useState<Record<string, { status: string; msg?: string }>>({});
  const [fulfillAllRunning, setFulfillAllRunning] = useState(false);

  function loadData() {
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
  }

  useEffect(() => { loadData(); }, []);

  async function handleFulfill(orderId: string) {
    setFulfillState((s) => ({ ...s, [orderId]: { status: "ordering" } }));
    try {
      const res = await fetch("/api/fulfill", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ orderId }),
      });
      const data = await res.json();
      setFulfillState((s) => ({ ...s, [orderId]: { status: data.status, msg: data.message } }));
      if (data.status === "ordered") loadData();
    } catch (e) {
      setFulfillState((s) => ({ ...s, [orderId]: { status: "error", msg: String(e) } }));
    }
  }

  async function handleFulfillAll() {
    setFulfillAllRunning(true);
    try {
      const res = await fetch("/api/fulfill", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({}),
      });
      const data = await res.json();
      if (data.results) {
        const newState: Record<string, { status: string; msg?: string }> = {};
        for (const r of data.results) {
          newState[r.orderId] = { status: r.status, msg: r.message };
        }
        setFulfillState((s) => ({ ...s, ...newState }));
      }
      loadData();
    } catch {
    } finally {
      setFulfillAllRunning(false);
    }
  }

  const cardDefs = pnl
    ? [
        { label: "売上", value: yen(pnl.revenue), emoji: "💰", gradient: "linear-gradient(135deg, #fce7f3, #fff)" },
        { label: "仕入原価", value: yen(pnl.supplierCost), emoji: "📦", gradient: "linear-gradient(135deg, #ffedd5, #fff)" },
        { label: "手数料", value: yen(pnl.platformFee), emoji: "🏷️", gradient: "linear-gradient(135deg, #f3f4f6, #fff)" },
        { label: "利益", value: yen(pnl.profit), emoji: "🐱", gradient: "linear-gradient(135deg, #d1fae5, #fff)" },
        { label: "平均利益率", value: `${(pnl.avgMarginRate * 100).toFixed(1)}%`, emoji: "📊", gradient: "linear-gradient(135deg, #ede9fe, #fff)" },
        { label: "受注件数", value: `${pnl.orderCount}件`, emoji: "🧾", gradient: "linear-gradient(135deg, #fef3c7, #fff)" },
      ]
    : [];

  return (
    <div>
      <p style={{ marginBottom: 16 }}>
        <a href="/">← ダッシュボード</a>
      </p>
      <h1 style={{ fontSize: 22 }}>🧾 受注 / 損益</h1>
      {error && <p style={{ color: "#dc2626" }}>😿 {error}</p>}

      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(155px, 1fr))", gap: 12, margin: "16px 0 28px" }}>
        {cardDefs.map((c) => (
          <div key={c.label} style={{
            padding: "16px 18px", border: "2px solid var(--card-border)",
            borderRadius: "var(--radius)", background: c.gradient,
            boxShadow: "var(--shadow)",
          }}>
            <div style={{ fontSize: 13, color: "var(--muted)", marginBottom: 4 }}>{c.emoji} {c.label}</div>
            <div style={{ fontSize: 22, fontWeight: 700, color: "var(--ink)" }}>{c.value}</div>
          </div>
        ))}
      </div>

      {orders.length > 0 && <SalesChart orders={orders} />}

      {orders.length > 0 && (
        <div style={{ marginBottom: 16 }}>
          <button
            onClick={() => {
              const header = "注文ID,日付,購入者,商品,数量,売上,原価,手数料,利益,状態\n";
              const rows = orders.map((o) =>
                [o.orderId, o.orderedAt, o.buyerName, o.itemTitle, o.quantity, o.revenue, o.supplierCost, o.platformFee, o.profit, STATUS_LABEL[o.status] ?? o.status].join(",")
              ).join("\n");
              const blob = new Blob(["﻿" + header + rows], { type: "text/csv;charset=utf-8" });
              const a = document.createElement("a");
              a.href = URL.createObjectURL(blob);
              a.download = `orders_${new Date().toISOString().slice(0, 10)}.csv`;
              a.click();
            }}
            style={{
              padding: "6px 16px", borderRadius: 20, cursor: "pointer", fontSize: 12, fontWeight: 600,
              border: "1.5px solid var(--card-border)", background: "#fff", color: "var(--ink)",
            }}
          >
            CSV
          </button>
        </div>
      )}

      {orders.some((o) => o.status === "received") && (
        <div style={{ marginBottom: 16 }}>
          <button
            onClick={handleFulfillAll}
            disabled={fulfillAllRunning}
            style={{
              padding: "8px 20px", borderRadius: 20, cursor: "pointer", fontSize: 13, fontWeight: 700,
              border: 0, color: "#fff",
              background: "linear-gradient(135deg, #f472b6, #a78bfa)",
              boxShadow: "0 2px 12px rgba(244,114,182,0.3)",
            }}
          >
            {fulfillAllRunning ? "発注中…" : "📦 未発注を一括発注"}
          </button>
          <span style={{ marginLeft: 12, fontSize: 12, color: "var(--muted)" }}>
            「受注」ステータスの注文を仕入れ先に自動発注します
          </span>
        </div>
      )}

      {orders.length > 0 && (
        <div style={{ overflowX: "auto", background: "#fff", borderRadius: "var(--radius)", border: "2px solid var(--card-border)", padding: 4 }}>
          <table>
            <thead>
              <tr>
                {["注文ID", "日付", "購入者", "商品", "数量", "売上", "原価", "手数料", "利益", "状態", "発注"].map((h) => (
                  <th key={h}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {orders.map((o) => {
                const ss = STATUS_STYLE[o.status] ?? { bg: "#e5e7eb", color: "#6b7280", emoji: "?" };
                return (
                  <tr key={o.orderId} style={{ opacity: o.status === "cancelled" ? 0.5 : 1 }}>
                    <td style={{ color: "var(--muted)", fontSize: 13 }}>{o.orderId}</td>
                    <td>{o.orderedAt}</td>
                    <td>{o.buyerName}</td>
                    <td style={{ fontWeight: 500 }}>{o.itemTitle}</td>
                    <td>{o.quantity}</td>
                    <td>{yen(o.revenue)}</td>
                    <td style={{ color: "#b45309" }}>{yen(o.supplierCost)}</td>
                    <td style={{ color: "var(--muted)" }}>{yen(o.platformFee)}</td>
                    <td style={{ fontWeight: 700, color: o.status === "cancelled" ? "#9ca3af" : "#16a34a" }}>{yen(o.profit)}</td>
                    <td>
                      <span style={{
                        background: ss.bg, color: ss.color,
                        padding: "3px 10px", borderRadius: 20, fontSize: 12, fontWeight: 600,
                        whiteSpace: "nowrap",
                      }}>
                        {ss.emoji} {STATUS_LABEL[o.status] ?? o.status}
                      </span>
                    </td>
                    <td>
                      {o.status === "received" ? (() => {
                        const fs = fulfillState[o.orderId];
                        if (fs?.status === "ordered") return <span style={{ color: "#16a34a", fontSize: 12, fontWeight: 600 }}>発注済</span>;
                        if (fs?.status === "error") return <span title={fs.msg} style={{ color: "#dc2626", fontSize: 12 }}>失敗</span>;
                        return (
                          <button
                            onClick={() => handleFulfill(o.orderId)}
                            disabled={fs?.status === "ordering"}
                            style={{
                              padding: "3px 10px", borderRadius: 12, cursor: "pointer",
                              fontSize: 11, fontWeight: 600, border: "1px solid #c4b5fd",
                              background: "#f5f3ff", color: "#7c3aed",
                            }}
                          >
                            {fs?.status === "ordering" ? "発注中…" : "仕入発注"}
                          </button>
                        );
                      })() : (
                        <span style={{ color: "#9ca3af", fontSize: 11 }}>-</span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
