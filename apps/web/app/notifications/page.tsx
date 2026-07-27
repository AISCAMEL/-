"use client";

import { useEffect, useState } from "react";

export default function NotificationsPage() {
  const [status, setStatus] = useState<{ slack: boolean; line: boolean } | null>(null);
  const [testing, setTesting] = useState(false);
  const [result, setResult] = useState<{ slack: string; line: string } | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch("/api/notifications")
      .then((r) => r.json())
      .then((d) => {
        if (d.slack !== undefined) setStatus(d);
        else setError("Hub API へ接続できません");
      })
      .catch(() => setError("接続エラー"));
  }, []);

  async function handleTest() {
    setTesting(true);
    setResult(null);
    try {
      const res = await fetch("/api/notifications", { method: "POST" });
      const data = await res.json();
      setResult(data);
    } catch (e) {
      setResult({ slack: "error", line: `error: ${e}` });
    } finally {
      setTesting(false);
    }
  }

  return (
    <div>
      <p style={{ marginBottom: 16 }}>
        <a href="/">← ダッシュボード</a>
      </p>
      <h1 style={{ fontSize: 22 }}>通知設定</h1>
      <p style={{ color: "var(--muted)", fontSize: 14, marginBottom: 20 }}>
        在庫切れ・売れ筋アラートを Slack / LINE に自動送信します。
      </p>

      {error && <p style={{ color: "#dc2626" }}>エラー: {error}</p>}

      <div style={{
        background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
        padding: 20, marginBottom: 20,
      }}>
        <h3 style={{ margin: "0 0 16px", fontSize: 15 }}>接続状態</h3>
        <div style={{ display: "flex", gap: 16, flexWrap: "wrap", marginBottom: 20 }}>
          <div style={{
            padding: "16px 24px", borderRadius: "var(--radius-sm)",
            border: `2px solid ${status?.slack ? "#86efac" : "#e5e7eb"}`,
            background: status?.slack ? "#dcfce7" : "#f9fafb",
            minWidth: 180,
          }}>
            <div style={{ fontSize: 24, marginBottom: 4 }}>Slack</div>
            <div style={{ fontSize: 14, fontWeight: 600, color: status?.slack ? "#166534" : "#9ca3af" }}>
              {status === null ? "確認中..." : status.slack ? "🟢 接続済み" : "⚪ 未設定"}
            </div>
            {!status?.slack && (
              <div style={{ fontSize: 11, color: "var(--muted)", marginTop: 6 }}>
                Render の Environment に<br /><code>SLACK_WEBHOOK_URL</code> を設定
              </div>
            )}
          </div>

          <div style={{
            padding: "16px 24px", borderRadius: "var(--radius-sm)",
            border: `2px solid ${status?.line ? "#86efac" : "#e5e7eb"}`,
            background: status?.line ? "#dcfce7" : "#f9fafb",
            minWidth: 180,
          }}>
            <div style={{ fontSize: 24, marginBottom: 4 }}>LINE Notify</div>
            <div style={{ fontSize: 14, fontWeight: 600, color: status?.line ? "#166534" : "#9ca3af" }}>
              {status === null ? "確認中..." : status.line ? "🟢 接続済み" : "⚪ 未設定"}
            </div>
            {!status?.line && (
              <div style={{ fontSize: 11, color: "var(--muted)", marginTop: 6 }}>
                Render の Environment に<br /><code>LINE_NOTIFY_TOKEN</code> を設定
              </div>
            )}
          </div>
        </div>

        <button
          onClick={handleTest}
          disabled={testing || (!status?.slack && !status?.line)}
          style={{
            padding: "10px 24px", borderRadius: 20, cursor: "pointer", fontSize: 14, fontWeight: 700,
            border: 0, color: "#fff",
            background: (status?.slack || status?.line) ? "linear-gradient(135deg, #f472b6, #a78bfa)" : "#d1d5db",
            boxShadow: (status?.slack || status?.line) ? "0 2px 12px rgba(244,114,182,0.3)" : "none",
          }}
        >
          {testing ? "送信中..." : "テスト通知を送信"}
        </button>

        {result && (
          <div style={{ marginTop: 16, padding: 12, background: "#fef6f0", borderRadius: 8, fontSize: 13 }}>
            <div style={{ marginBottom: 4 }}>
              <strong>Slack:</strong>{" "}
              <span style={{ color: result.slack === "ok" ? "#16a34a" : result.slack === "skip" ? "#9ca3af" : "#dc2626" }}>
                {result.slack === "ok" ? "送信成功" : result.slack === "skip" ? "未設定（スキップ）" : result.slack}
              </span>
            </div>
            <div>
              <strong>LINE:</strong>{" "}
              <span style={{ color: result.line === "ok" ? "#16a34a" : result.line === "skip" ? "#9ca3af" : "#dc2626" }}>
                {result.line === "ok" ? "送信成功" : result.line === "skip" ? "未設定（スキップ）" : result.line}
              </span>
            </div>
          </div>
        )}
      </div>

      <div style={{
        background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
        padding: 20,
      }}>
        <h3 style={{ margin: "0 0 12px", fontSize: 15 }}>通知される内容</h3>
        <div style={{ fontSize: 13, lineHeight: 2 }}>
          <div>😾 <strong>在庫切れ</strong> — 仕入れ先の在庫が 0 になった商品</div>
          <div>😻 <strong>売れ筋発見</strong> — 定期スクリーニングで高利益率の商品を発見</div>
          <div>🙀 <strong>価格変動</strong> — 仕入れ価格が大きく変動した商品</div>
          <div>😻 <strong>発注完了</strong> — 自動発注が正常に完了</div>
        </div>
      </div>
    </div>
  );
}
