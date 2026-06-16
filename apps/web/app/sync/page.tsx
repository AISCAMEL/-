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
  const map: Record<string, { label: string; color: string }> = {
    unpublish: { label: "🚫 自動非公開（欠品）", color: "#dc2626" },
    republish: { label: "✅ 再公開（再入荷）", color: "#16a34a" },
    update_stock: { label: "📦 在庫更新", color: "#0891b2" },
    update_price: { label: "💰 価格更新", color: "#ca8a04" },
    noop: { label: "— 変化なし", color: "#9ca3af" },
  };
  const m = map[a.type] ?? { label: a.type, color: "#777" };
  return (
    <span key={a.type} style={{ background: m.color, color: "#fff", padding: "2px 8px", borderRadius: 999, fontSize: 12, marginRight: 4 }}>
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

export default function SyncPage() {
  const [result, setResult] = useState<SyncResult | null>(null);
  const [status, setStatus] = useState<SyncStatus | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // 起動時に定期同期の状態と前回結果を取得
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
        <a href="/" style={{ color: "#e8612e" }}>← ダッシュボード</a>
      </p>
      <h1>在庫・価格同期</h1>
      <p style={{ color: "#666" }}>
        仕入れ先の在庫・価格を取得し、欠品は自動非公開、価格変動は再計算して BASE に反映するアクションを算出します。
        （実運用では定期ジョブで自動実行）
      </p>

      {status && (
        <div
          style={{
            display: "inline-block", padding: "8px 14px", borderRadius: 8, fontSize: 14,
            background: status.enabled ? "#dcfce7" : "#f3f4f6",
            color: status.enabled ? "#166534" : "#6b7280",
            border: `1px solid ${status.enabled ? "#16a34a" : "#d1d5db"}`,
          }}
        >
          {status.enabled
            ? `🟢 自動同期：有効（${status.intervalMinutes}分ごと）`
            : "⚪ 自動同期：無効（SYNC_INTERVAL_MINUTES で設定）"}
        </div>
      )}

      <br />
      <button
        onClick={run} disabled={loading}
        style={{ padding: "8px 20px", background: "#e8612e", color: "#fff", border: 0, borderRadius: 8, cursor: "pointer", margin: "12px 0" }}
      >
        {loading ? "同期中…" : "今すぐ同期"}
      </button>

      {error && <p style={{ color: "#dc2626" }}>{error}</p>}

      {result && (
        <>
          <p style={{ fontSize: 14, color: "#444" }}>
            実行: {new Date(result.ranAt).toLocaleString("ja-JP")} ／ 非公開 {result.summary.unpublished}・
            再公開 {result.summary.republished}・価格更新 {result.summary.priceUpdates}・
            在庫更新 {result.summary.stockUpdates}・変化なし {result.summary.noChange}
          </p>
          <table style={{ borderCollapse: "collapse", width: "100%", fontSize: 14 }}>
            <thead>
              <tr style={{ textAlign: "left", borderBottom: "2px solid #e5e7eb" }}>
                {["商品", "仕入在庫", "旧価格", "新価格", "アクション"].map((h) => (
                  <th key={h} style={{ padding: "8px 6px" }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {result.results.map((r) => (
                <tr key={r.externalId} style={{ borderBottom: "1px solid #f0f0f0" }}>
                  <td style={{ padding: "8px 6px" }}>{r.title}</td>
                  <td style={{ padding: "8px 6px", color: r.supplierStock === 0 ? "#dc2626" : "#111" }}>
                    {r.supplierStock ?? "-"}
                  </td>
                  <td style={{ padding: "8px 6px" }}>{yen(r.oldPrice)}</td>
                  <td style={{ padding: "8px 6px", fontWeight: r.newPrice !== r.oldPrice ? 700 : 400, color: r.newPrice !== r.oldPrice ? "#ca8a04" : "#111" }}>
                    {yen(r.newPrice)}
                  </td>
                  <td style={{ padding: "8px 6px" }}>
                    {r.actions.length === 0 ? actionBadge({ type: "noop", externalId: r.externalId }) : r.actions.map(actionBadge)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </>
      )}
    </div>
  );
}
