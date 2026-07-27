"use client";

interface OrderPnl {
  orderId: string;
  orderedAt: string;
  revenue: number;
  profit: number;
  status: string;
}

interface Props {
  orders: OrderPnl[];
}

interface DayData {
  date: string;
  revenue: number;
  profit: number;
  count: number;
}

export default function SalesChart({ orders }: Props) {
  const active = orders.filter((o) => o.status !== "cancelled");
  if (active.length === 0) return null;

  const byDate = new Map<string, DayData>();
  for (const o of active) {
    const d = o.orderedAt;
    const cur = byDate.get(d) ?? { date: d, revenue: 0, profit: 0, count: 0 };
    cur.revenue += o.revenue;
    cur.profit += o.profit;
    cur.count += 1;
    byDate.set(d, cur);
  }
  const days = Array.from(byDate.values()).sort((a, b) => a.date.localeCompare(b.date));

  const maxVal = Math.max(...days.map((d) => d.revenue), 1);
  const W = 100;
  const H = 40;
  const pad = { top: 2, bottom: 6, left: 0, right: 0 };
  const chartW = W - pad.left - pad.right;
  const chartH = H - pad.top - pad.bottom;

  const points = days.map((d, i) => ({
    x: pad.left + (days.length === 1 ? chartW / 2 : (i / (days.length - 1)) * chartW),
    yRev: pad.top + chartH - (d.revenue / maxVal) * chartH,
    yProf: pad.top + chartH - (d.profit / maxVal) * chartH,
    ...d,
  }));

  const revLine = points.map((p) => `${p.x},${p.yRev}`).join(" ");
  const profLine = points.map((p) => `${p.x},${p.yProf}`).join(" ");
  const revArea = `${pad.left},${pad.top + chartH} ${revLine} ${pad.left + chartW},${pad.top + chartH}`;
  const profArea = `${pad.left},${pad.top + chartH} ${profLine} ${pad.left + chartW},${pad.top + chartH}`;

  const yen = (n: number) => `¥${n.toLocaleString()}`;

  return (
    <div style={{
      background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
      padding: "16px 20px", marginBottom: 20,
    }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
        <h3 style={{ margin: 0, fontSize: 15 }}>日別 売上・利益</h3>
        <div style={{ display: "flex", gap: 16, fontSize: 12 }}>
          <span><span style={{ display: "inline-block", width: 12, height: 3, background: "#f472b6", borderRadius: 2, marginRight: 4, verticalAlign: "middle" }} />売上</span>
          <span><span style={{ display: "inline-block", width: 12, height: 3, background: "#34d399", borderRadius: 2, marginRight: 4, verticalAlign: "middle" }} />利益</span>
        </div>
      </div>
      <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%", height: 160 }} preserveAspectRatio="none">
        <polygon points={revArea} fill="rgba(244,114,182,0.12)" />
        <polyline points={revLine} fill="none" stroke="#f472b6" strokeWidth="0.6" strokeLinejoin="round" />
        <polygon points={profArea} fill="rgba(52,211,153,0.12)" />
        <polyline points={profLine} fill="none" stroke="#34d399" strokeWidth="0.6" strokeLinejoin="round" />
        {points.map((p) => (
          <g key={p.date}>
            <circle cx={p.x} cy={p.yRev} r="0.8" fill="#f472b6" />
            <circle cx={p.x} cy={p.yProf} r="0.8" fill="#34d399" />
          </g>
        ))}
      </svg>
      <div style={{ display: "flex", justifyContent: "space-between", fontSize: 11, color: "var(--muted)", marginTop: 4 }}>
        {days.length > 0 && <span>{days[0].date}</span>}
        {days.length > 1 && <span>{days[days.length - 1].date}</span>}
      </div>
      <div style={{ display: "flex", gap: 12, marginTop: 12, flexWrap: "wrap" }}>
        {days.map((d) => (
          <div key={d.date} style={{ fontSize: 11, padding: "4px 8px", background: "#fef6f0", borderRadius: 8, lineHeight: 1.5 }}>
            <div style={{ fontWeight: 600 }}>{d.date.slice(5)}</div>
            <div style={{ color: "#f472b6" }}>{yen(d.revenue)}</div>
            <div style={{ color: "#16a34a" }}>{yen(d.profit)}</div>
            <div style={{ color: "var(--muted)" }}>{d.count}件</div>
          </div>
        ))}
      </div>
    </div>
  );
}
