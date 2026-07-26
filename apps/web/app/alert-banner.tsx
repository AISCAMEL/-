"use client";

import { useEffect, useState } from "react";

interface Alert {
  id: string;
  type: string;
  title: string;
  message: string;
  severity: string;
  createdAt: string;
  read: boolean;
}

const SEVERITY_STYLE: Record<string, { bg: string; border: string; icon: string }> = {
  info: { bg: "#eff6ff", border: "#93c5fd", icon: "😻" },
  warning: { bg: "#fefce8", border: "#fde047", icon: "😾" },
  critical: { bg: "#fef2f2", border: "#fca5a5", icon: "🙀" },
};

export default function AlertBanner() {
  const [alerts, setAlerts] = useState<Alert[]>([]);
  const [fxRate, setFxRate] = useState<number | null>(null);

  useEffect(() => {
    fetch("/api/alerts")
      .then((r) => r.json())
      .then((d) => {
        if (d.alerts) setAlerts(d.alerts.filter((a: Alert) => !a.read).slice(0, 5));
      })
      .catch(() => {});

    fetch("/api/fx")
      .then((r) => r.json())
      .then((d) => {
        if (d.cnyToJpy) setFxRate(d.cnyToJpy);
      })
      .catch(() => {});
  }, []);

  return (
    <>
      {fxRate && (
        <div style={{
          display: "flex", alignItems: "center", gap: 8,
          padding: "8px 14px", marginBottom: 12, borderRadius: "var(--radius-sm)",
          background: "#f0fdf4", border: "1.5px solid #86efac", fontSize: 13,
        }}>
          <span>💱</span>
          <span style={{ fontWeight: 600 }}>為替: 1 CNY = ¥{fxRate.toFixed(2)}</span>
          <span style={{ color: "var(--muted)", fontSize: 12 }}>（自動取得・同期時に更新）</span>
        </div>
      )}

      {alerts.map((a) => {
        const style = SEVERITY_STYLE[a.severity] ?? SEVERITY_STYLE.info;
        return (
          <div
            key={a.id}
            style={{
              display: "flex", alignItems: "center", gap: 10,
              padding: "10px 14px", marginBottom: 8, borderRadius: "var(--radius-sm)",
              background: style.bg, border: `1.5px solid ${style.border}`, fontSize: 13,
            }}
          >
            <span style={{ fontSize: 18 }}>{style.icon}</span>
            <div>
              <span style={{ fontWeight: 700 }}>{a.title}</span>
              <span style={{ color: "var(--muted)", marginLeft: 8 }}>{a.message}</span>
            </div>
            <span style={{ marginLeft: "auto", color: "var(--muted)", fontSize: 11, whiteSpace: "nowrap" }}>
              {new Date(a.createdAt).toLocaleString("ja-JP", { month: "numeric", day: "numeric", hour: "2-digit", minute: "2-digit" })}
            </span>
          </div>
        );
      })}
    </>
  );
}
