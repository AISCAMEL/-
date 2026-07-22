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
  const [baseOAuthConfigured, setBaseOAuthConfigured] = useState(false);
  const [error, setError] = useState(false);
  const [toast, setToast] = useState<{ type: "ok" | "err"; msg: string } | null>(null);

  useEffect(() => {
    fetch("/api/connectors")
      .then((r) => r.json())
      .then((d) => {
        if (d.modes) {
          setModes(d.modes);
          setBaseOAuthConfigured(!!d.baseOAuthConfigured);
        } else {
          setError(true);
        }
      })
      .catch(() => setError(true));

    const params = new URLSearchParams(window.location.search);
    if (params.get("base") === "connected") {
      setToast({ type: "ok", msg: "BASE と連携しました！" });
      window.history.replaceState({}, "", window.location.pathname);
    }
    const baseErr = params.get("base_error");
    if (baseErr) {
      setToast({ type: "err", msg: `BASE 連携エラー: ${baseErr}` });
      window.history.replaceState({}, "", window.location.pathname);
    }
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

  const baseMock = modes.base === "mock";

  return (
    <>
      {toast && (
        <div style={{
          padding: "12px 18px", borderRadius: "var(--radius-sm)", marginBottom: 12, fontSize: 14,
          background: toast.type === "ok" ? "#dcfce7" : "#fef2f2",
          border: `1px solid ${toast.type === "ok" ? "#86efac" : "#fecaca"}`,
          color: toast.type === "ok" ? "#166534" : "#dc2626",
          display: "flex", justifyContent: "space-between", alignItems: "center",
        }}>
          <span>{toast.type === "ok" ? "🎉" : "😿"} {toast.msg}</span>
          <button onClick={() => setToast(null)} style={{
            background: "none", border: "none", cursor: "pointer", fontSize: 16, color: "inherit",
          }}>×</button>
        </div>
      )}

      <div style={{
        display: "flex", gap: 8, flexWrap: "wrap", margin: "0 0 20px",
        padding: "14px 18px", borderRadius: "var(--radius-sm)",
        background: "#fff", border: "2px solid var(--card-border)",
        alignItems: "center",
      }}>
        <span style={{ fontSize: 13, color: "var(--muted)", marginRight: 4 }}>接続:</span>
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

        {baseMock && baseOAuthConfigured && (
          <a
            href="/api/base/authorize"
            style={{
              marginLeft: "auto",
              padding: "6px 16px",
              borderRadius: 20,
              fontSize: 12,
              fontWeight: 700,
              background: "linear-gradient(135deg, #a78bfa 0%, #f472b6 100%)",
              color: "#fff",
              textDecoration: "none",
              border: "none",
              cursor: "pointer",
              boxShadow: "0 2px 8px rgba(167,139,250,0.3)",
              transition: "transform 0.15s, box-shadow 0.15s",
            }}
          >
            🔗 BASE と連携する
          </a>
        )}
      </div>
    </>
  );
}
