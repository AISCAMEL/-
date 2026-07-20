"use client";

import { useEffect, useState } from "react";

const LABEL: Record<string, string> = {
  rakuten: "楽天",
  yahoo: "Yahoo!",
  amazon: "Amazon",
  ebay: "eBay",
  base: "BASE",
  theckb: "THE CKB",
  alibaba: "Alibaba",
  aliexpress: "AliExpress",
};
const TYPE_LABEL: Record<string, string> = {
  rakuten: "調査",
  yahoo: "調査",
  amazon: "調査",
  ebay: "調査",
  base: "販売",
  theckb: "仕入",
  alibaba: "仕入",
  aliexpress: "仕入",
};

export default function ConnectorStatus() {
  const [modes, setModes] = useState<Record<string, string> | null>(null);
  const [error, setError] = useState(false);

  useEffect(() => {
    fetch("/api/connectors")
      .then((r) => r.json())
      .then((d) => (d.modes ? setModes(d.modes) : setError(true)))
      .catch(() => setError(true));
  }, []);

  if (error) return (
    <div style={{
      padding: "14px 18px", borderRadius: "var(--radius-sm)",
      background: "#fef2f2", border: "1px solid #fecaca", color: "#dc2626",
      marginBottom: 20, fontSize: 14,
    }}>
      😿 Hub API に接続できません（未起動の可能性があります）
    </div>
  );
  if (!modes) return (
    <div style={{
      padding: "14px 18px", borderRadius: "var(--radius-sm)",
      background: "var(--primary-weak)", color: "var(--muted)",
      marginBottom: 20, fontSize: 14,
    }}>
      🐾 接続状態を確認中…
    </div>
  );

  return (
    <div style={{
      display: "flex", gap: 8, flexWrap: "wrap", margin: "0 0 20px",
      padding: "14px 18px", borderRadius: "var(--radius-sm)",
      background: "#fff", border: "2px solid var(--card-border)",
    }}>
      <span style={{ fontSize: 13, color: "var(--muted)", marginRight: 4, alignSelf: "center" }}>接続:</span>
      {Object.entries(modes).map(([k, mode]) => {
        const live = mode === "live";
        return (
          <span
            key={k}
            style={{
              padding: "4px 12px", borderRadius: 20, fontSize: 12, fontWeight: 600,
              border: `1.5px solid ${live ? "#86efac" : "#e5e7eb"}`,
              background: live ? "#dcfce7" : "#f9fafb",
              color: live ? "#166534" : "#9ca3af",
            }}
          >
            {live ? "🟢" : "⚪"} {LABEL[k] ?? k}
            <span style={{ opacity: 0.7, marginLeft: 3 }}>({TYPE_LABEL[k] ?? ""})</span>
          </span>
        );
      })}
    </div>
  );
}
