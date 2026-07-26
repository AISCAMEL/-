"use client";

import { useEffect, useState } from "react";

interface Kpi {
  revenue: number;
  profit: number;
  avgMarginRate: number;
  orderCount: number;
  productCount: number;
  publishedCount: number;
}

const yen = (n: number) => `¥${n.toLocaleString()}`;

export default function DashboardKpi() {
  const [kpi, setKpi] = useState<Kpi | null>(null);

  useEffect(() => {
    Promise.all([
      fetch("/api/pnl").then((r) => r.json()).catch(() => null),
      fetch("/api/products").then((r) => r.json()).catch(() => null),
    ]).then(([pnl, products]) => {
      setKpi({
        revenue: pnl?.revenue ?? 0,
        profit: pnl?.profit ?? 0,
        avgMarginRate: pnl?.avgMarginRate ?? 0,
        orderCount: pnl?.orderCount ?? 0,
        productCount: products?.total ?? products?.items?.length ?? 0,
        publishedCount: (products?.items ?? []).filter(
          (p: { listings?: { status: string }[] }) => p.listings?.some((l: { status: string }) => l.status === "published")
        ).length,
      });
    });
  }, []);

  if (!kpi) return null;

  const cards = [
    { label: "売上合計", value: yen(kpi.revenue), emoji: "💰", bg: "linear-gradient(135deg, #fce7f3, #fff1f2)" },
    { label: "利益合計", value: yen(kpi.profit), emoji: "🐱", bg: "linear-gradient(135deg, #d1fae5, #ecfdf5)" },
    { label: "利益率", value: `${(kpi.avgMarginRate * 100).toFixed(1)}%`, emoji: "📊", bg: "linear-gradient(135deg, #ede9fe, #f5f3ff)" },
    { label: "受注数", value: `${kpi.orderCount}`, emoji: "🧾", bg: "linear-gradient(135deg, #fef3c7, #fffbeb)" },
    { label: "商品数", value: `${kpi.productCount}`, emoji: "📦", bg: "linear-gradient(135deg, #dbeafe, #eff6ff)" },
    { label: "出品中", value: `${kpi.publishedCount}`, emoji: "🛒", bg: "linear-gradient(135deg, #ccfbf1, #f0fdfa)" },
  ];

  return (
    <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(140px, 1fr))", gap: 10, marginBottom: 20 }}>
      {cards.map((c) => (
        <div key={c.label} style={{
          padding: "14px 16px", borderRadius: "var(--radius)",
          background: c.bg, border: "2px solid var(--card-border)",
          boxShadow: "var(--shadow)",
        }}>
          <div style={{ fontSize: 12, color: "var(--muted)", marginBottom: 2 }}>{c.emoji} {c.label}</div>
          <div style={{ fontSize: 20, fontWeight: 700, color: "var(--ink)" }}>{c.value}</div>
        </div>
      ))}
    </div>
  );
}
