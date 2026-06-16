"use client";

import { useEffect, useState } from "react";

const LABEL: Record<string, string> = {
  rakuten: "楽天(調査)",
  yahoo: "Yahoo(調査)",
  amazon: "Amazon(調査)",
  ebay: "eBay(調査)",
  base: "BASE(販売)",
  theckb: "THE CKB(仕入)",
  alibaba: "Alibaba(仕入)",
  aliexpress: "AliExpress(仕入)",
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

  if (error) return <p style={{ color: "#dc2626" }}>接続状態を取得できません（Hub API 未起動）</p>;
  if (!modes) return <p style={{ color: "#888" }}>接続状態を確認中…</p>;

  return (
    <div style={{ display: "flex", gap: 8, flexWrap: "wrap", margin: "8px 0 20px" }}>
      {Object.entries(modes).map(([k, mode]) => {
        const live = mode === "live";
        return (
          <span
            key={k}
            style={{
              padding: "3px 10px", borderRadius: 999, fontSize: 13,
              border: `1px solid ${live ? "#16a34a" : "#d1d5db"}`,
              background: live ? "#dcfce7" : "#f3f4f6",
              color: live ? "#166534" : "#6b7280",
            }}
          >
            {live ? "🟢" : "⚪"} {LABEL[k] ?? k}: {live ? "実データ" : "mock"}
          </span>
        );
      })}
    </div>
  );
}
