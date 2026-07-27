"use client";

import { useEffect, useState } from "react";

type SyncAction =
  | { type: "unpublish"; externalId: string; reason: string }
  | { type: "republish"; externalId: string }
  | { type: "update_stock"; externalId: string; stock: number }
  | { type: "update_price"; externalId: string; price: number }
  | { type: "noop"; externalId: string };

interface SyncRow {
  externalId: string;
  title: string;
  supplierStock: number | null;
  oldPrice: number;
  newPrice: number;
  actions: SyncAction[];
}
interface SyncResult {
  ranAt: string;
  results: SyncRow[];
  summary: { unpublished: number; republished: number; priceUpdates: number; stockUpdates: number; noChange: number };
}

const yen = (n: number) => `¥${n.toLocaleString()}`;

function actionBadge(a: SyncAction) {
  const map: Record<string, { label: string; bg: string; color: string }> = {
    unpublish: { label: "🚫 自動非公開", bg: "linear-gradient(135deg, #fca5a5, #ef4444)", color: "#fff" },
    republish: { label: "✅ 再公開", bg: "linear-gradient(135deg, #6ee7b7, #34d399)", color: "#fff" },
    update_stock: { label: "📦 在庫更新", bg: "linear-gradient(135deg, #67e8f9, #22d3ee)", color: "#164e63" },
    update_price: { label: "💰 価格更新", bg: "linear-gradient(135deg, #fde68a, #fbbf24)", color: "#92400e" },
    noop: { label: "— 変化なし", bg: "#f3f4f6", color: "#9ca3af" },
  };
  const m = map[a.type] ?? { label: a.type, bg: "#e5e7eb", color: "#777" };
  return (
    <span key={a.type} style={{
      background: m.bg, color: m.color,
      padding: "3px 10px", borderRadius: 20, fontSize: 12, marginRight: 4,
      fontWeight: 600, whiteSpace: "nowrap",
    }}>
      {m.label}
    </span>
  );
}

interface SyncStatus {
  enabled: boolean;
  intervalMinutes: number;
  schedulerRunning: boolean;
  lastRun: SyncResult | null;
}

interface RepriceDetail {
  id: string;
  title: string;
  oldPrice: number;
  newPrice: number;
}

export default function SyncPage() {
  const [result, setResult] = useState<SyncResult | null>(null);
  const [status, setStatus] = useState<SyncStatus | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [repricing, setRepricing] = useState(false);
  const [repriceResult, setRepriceResult] = useState<{ updated: number; fxRate: number; details: RepriceDetail[] } | null>(null);

  useEffect(() => {
    fetch("/api/sync/status")
      .then((r) => r.json())
      .then((d) => {
        if (d.enabled !== undefined) {
          setStatus(d);
          if (d.lastRun) setResult(d.lastRun);
        }
      })
      .catch(() => {});
  }, []);

  async function run() {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch("/api/sync", { method: "POST" });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error ? JSON.stringify(data.error) : "同期失敗");
      setResult(data);
    } catch (e) {
      setError(String(e));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div>
      <p style={{ marginBottom: 16 }}>
        <a href="/">← ダッシュボード</a>
      </p>
      <h1 style={{ fontSize: 22 }}>🔄 在庫・価格同期</h1>
      <p style={{ color: "var(--muted)", fontSize: 14 }}>
        仕入れ先の在庫・価格を取得し、欠品は自動非公開、価格変動は再計算して BASE に反映するアクションを算出します。
      </p>

      {status && (
        <div style={{
          display: "inline-flex", alignItems: "center", gap: 8,
          padding: "10px 18px", borderRadius: 20, fontSize: 14, fontWeight: 600,
          background: status.enabled
            ? "linear-gradient(135deg, #d1fae5, #a7f3d0)"
            : "#f3f4f6",
          color: status.enabled ? "#166534" : "#6b7280",
          border: `2px solid ${status.enabled ? "#86efac" : "#e5e7eb"}`,
        }}>
          {status.enabled
            ? `🟢 自動同期：有効（${status.intervalMinutes}分ごと）`
            : "⚪ 自動同期：無効（SYNC_INTERVAL_MINUTES で設定）"}
        </div>
      )}

      <div style={{ display: "flex", gap: 12, margin: "14px 0", flexWrap: "wrap" }}>
        <button
          onClick={run} disabled={loading}
          style={{
            padding: "10px 24px",
            background: "linear-gradient(135deg, #f472b6, #a78bfa)",
            color: "#fff", border: 0, borderRadius: 20, cursor: "pointer",
            fontWeight: 700, fontSize: 14,
            boxShadow: "0 2px 12px rgba(244,114,182,0.3)",
          }}
        >
          {loading ? "🐾 同期中…" : "今すぐ同期"}
        </button>

        <button
          onClick={async () => {
            setRepricing(true);
            setRepriceResult(null);
            try {
              const res = await fetch("/api/reprice", { method: "POST" });
              const data = await res.json();
              setRepriceResult(data);
            } catch {} finally { setRepricing(false); }
          }}
          disabled={repricing}
          style={{
            padding: "10px 24px",
            background: "linear-gradient(135deg, #fde68a, #f59e0b)",
            color: "#92400e", border: 0, borderRadius: 20, cursor: "pointer",
            fontWeight: 700, fontSize: 14,
            boxShadow: "0 2px 12px rgba(245,158,11,0.2)",
          }}
        >
          {repricing ? "計算中…" : "💱 為替で自動価格改定"}
        </button>
      </div>

      {repriceResult && (
        <div style={{
          background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
          padding: 16, marginBottom: 16,
        }}>
          <div style={{ fontSize: 14, marginBottom: 8 }}>
            為替レート: <strong>¥{repriceResult.fxRate.toFixed(2)}/CNY</strong>
            {" — "}
            <span style={{ color: repriceResult.updated > 0 ? "#16a34a" : "var(--muted)" }}>
              {repriceResult.updated}件更新
            </span>
          </div>
          {repriceResult.details.length > 0 && (
            <div style={{ fontSize: 13, maxHeight: 200, overflowY: "auto" }}>
              {repriceResult.details.map((d) => (
                <div key={d.id} style={{ padding: "4px 0", borderBottom: "1px solid #f3f4f6" }}>
                  {d.title}: {yen(d.oldPrice)} → <strong style={{ color: "#ca8a04" }}>{yen(d.newPrice)}</strong>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {error && <p style={{ color: "#dc2626" }}>😿 {error}</p>}

      {result && (
        <>
          <div style={{
            display: "flex", gap: 8, flexWrap: "wrap", margin: "8px 0 16px",
            fontSize: 13, color: "var(--muted)",
          }}>
            <span>🕐 {new Date(result.ranAt).toLocaleString("ja-JP")}</span>
            <span style={{ padding: "2px 8px", borderRadius: 12, background: "#fef2f2", color: "#dc2626" }}>非公開 {result.summary.unpublished}</span>
            <span style={{ padding: "2px 8px", borderRadius: 12, background: "#dcfce7", color: "#166534" }}>再公開 {result.summary.republished}</span>
            <span style={{ padding: "2px 8px", borderRadius: 12, background: "#fef3c7", color: "#92400e" }}>価格更新 {result.summary.priceUpdates}</span>
            <span style={{ padding: "2px 8px", borderRadius: 12, background: "#e0f2fe", color: "#0c4a6e" }}>在庫更新 {result.summary.stockUpdates}</span>
            <span style={{ padding: "2px 8px", borderRadius: 12, background: "#f3f4f6", color: "#6b7280" }}>変化なし {result.summary.noChange}</span>
          </div>
          <div style={{ overflowX: "auto", background: "#fff", borderRadius: "var(--radius)", border: "2px solid var(--card-border)", padding: 4 }}>
            <table>
              <thead>
                <tr>
                  {["商品", "仕入在庫", "旧価格", "新価格", "アクション"].map((h) => (
                    <th key={h}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {result.results.map((r) => (
                  <tr key={r.externalId}>
                    <td style={{ fontWeight: 500 }}>{r.title}</td>
                    <td style={{ color: r.supplierStock === 0 ? "#dc2626" : "var(--ink)", fontWeight: r.supplierStock === 0 ? 700 : 400 }}>
                      {r.supplierStock ?? "-"}
                    </td>
                    <td>{yen(r.oldPrice)}</td>
                    <td style={{ fontWeight: r.newPrice !== r.oldPrice ? 700 : 400, color: r.newPrice !== r.oldPrice ? "#ca8a04" : "var(--ink)" }}>
                      {yen(r.newPrice)}
                    </td>
                    <td>
                      {r.actions.length === 0 ? actionBadge({ type: "noop", externalId: r.externalId }) : r.actions.map(actionBadge)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}
