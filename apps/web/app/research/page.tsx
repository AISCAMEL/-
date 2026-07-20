"use client";

import { useEffect, useState } from "react";

interface Profitability {
  sellPrice: number;
  profit: number;
  marginRate: number;
  roi: number;
}
interface ScoreResult {
  score: number;
  grade: "A" | "B" | "C";
  reasons: string[];
}
interface ScreenedItem {
  key: string;
  keyword: string;
  landedCost: number | null;
  chosen: Profitability | null;
  marketSampleCount: number;
  score: ScoreResult;
}

const GRADE_STYLE: Record<string, { bg: string; color: string; emoji: string }> = {
  A: { bg: "linear-gradient(135deg, #6ee7b7, #34d399)", color: "#fff", emoji: "😻" },
  B: { bg: "linear-gradient(135deg, #fde68a, #fbbf24)", color: "#92400e", emoji: "😺" },
  C: { bg: "linear-gradient(135deg, #e5e7eb, #d1d5db)", color: "#6b7280", emoji: "😿" },
};
const yen = (n: number | null | undefined) => (n == null ? "-" : `¥${n.toLocaleString()}`);
const pct = (n: number | undefined) => (n == null ? "-" : `${(n * 100).toFixed(1)}%`);

export default function ResearchPage() {
  const [keywords, setKeywords] = useState<string[]>([]);
  const [minMarginRate, setMinMarginRate] = useState(0.3);
  const [minGrade, setMinGrade] = useState<"A" | "B" | "C">("B");
  const [items, setItems] = useState<ScreenedItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pubState, setPubState] = useState<Record<string, { status: string; msg?: string }>>({});
  const [liveMarkets, setLiveMarkets] = useState<string[]>([]);

  const ALL_MARKETS = ["amazon", "rakuten", "yahoo", "ebay"];

  useEffect(() => {
    fetch("/api/cat-goods")
      .then((r) => r.json())
      .then((d) => {
        if (d.searchKeywords) setKeywords(d.searchKeywords);
        if (d.screening) {
          setMinMarginRate(d.screening.minMarginRate ?? 0.3);
          setMinGrade(d.screening.minGrade ?? "B");
        }
      })
      .catch(() => setError("猫グッズプリセットの取得に失敗（Hub API 未起動の可能性）"));
  }, []);

  useEffect(() => {
    fetch("/api/connectors")
      .then((r) => r.json())
      .then((d) => {
        if (d.modes) {
          setLiveMarkets(ALL_MARKETS.filter((m) => d.modes[m] === "live"));
        }
      })
      .catch(() => {});
  }, []);

  const marketsToUse = liveMarkets.length > 0 ? liveMarkets : ["amazon", "rakuten", "yahoo"];

  async function runScreen() {
    setLoading(true);
    setError(null);
    try {
      const candidates = keywords.map((keyword, i) => ({
        supplierId: "theckb",
        externalId: `CKB-${String(i + 1).padStart(4, "0")}`,
        keyword,
      }));
      const res = await fetch("/api/screen", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ candidates, markets: marketsToUse, minMarginRate, minGrade }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error ? JSON.stringify(data.error) : "スクリーニング失敗");
      setItems(data.items ?? []);
    } catch (e) {
      setError(String(e));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }

  async function publish(it: ScreenedItem) {
    const [supplierId, externalId] = it.key.split(":");
    setPubState((s) => ({ ...s, [it.key]: { status: "publishing" } }));
    try {
      const res = await fetch("/api/publish", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ supplierId, externalId, channelId: "base" }),
      });
      const data = await res.json();
      if (!res.ok) {
        const reason = data.issues
          ? data.issues.map((i: { code: string }) => i.code).join(", ")
          : data.error
            ? JSON.stringify(data.error)
            : "出品不可";
        setPubState((s) => ({ ...s, [it.key]: { status: "error", msg: reason } }));
        return;
      }
      setPubState((s) => ({
        ...s,
        [it.key]: { status: "done", msg: data.listing?.externalListingId ?? "出品済み" },
      }));
    } catch (e) {
      setPubState((s) => ({ ...s, [it.key]: { status: "error", msg: String(e) } }));
    }
  }

  function renderPublish(it: ScreenedItem) {
    const st = pubState[it.key];
    if (st?.status === "done") {
      return <span style={{ color: "#16a34a", fontSize: 13, fontWeight: 600 }}>✅ 出品済</span>;
    }
    if (st?.status === "error") {
      return (
        <span title={st.msg}>
          <span style={{ color: "#dc2626", fontSize: 13 }}>😿 不可</span>{" "}
          <button onClick={() => publish(it)} style={{ fontSize: 12, cursor: "pointer", borderRadius: 8, border: "1px solid #fecaca", background: "#fff", padding: "2px 8px" }}>再試行</button>
        </span>
      );
    }
    const publishing = st?.status === "publishing";
    const isA = it.score.grade === "A";
    return (
      <button
        onClick={() => publish(it)}
        disabled={publishing}
        style={{
          padding: "5px 14px", borderRadius: 20, cursor: "pointer", fontSize: 13, fontWeight: 600,
          border: 0, color: "#fff",
          background: isA ? "linear-gradient(135deg, #6ee7b7, #34d399)" : "linear-gradient(135deg, #94a3b8, #64748b)",
          boxShadow: isA ? "0 2px 8px rgba(52,211,153,0.3)" : "none",
        }}
      >
        {publishing ? "🐾 出品中…" : "BASE出品"}
      </button>
    );
  }

  return (
    <div>
      <p style={{ marginBottom: 16 }}>
        <a href="/">← ダッシュボード</a>
      </p>
      <h1 style={{ fontSize: 22 }}>🔍 猫グッズ スクリーニング</h1>
      <p style={{ color: "var(--muted)", fontSize: 14 }}>
        猫グッズのキーワードを実データのモール（楽天・Yahoo!等）で調査し、仕入れ値と突き合わせて利益率で採点・ランキングします。
      </p>

      <div style={{
        display: "flex", gap: 8, flexWrap: "wrap", margin: "8px 0 16px",
        padding: "10px 14px", borderRadius: "var(--radius-sm)",
        background: "#fff", border: "2px solid var(--card-border)",
        fontSize: 13,
      }}>
        <span style={{ color: "var(--muted)", alignSelf: "center" }}>調査先:</span>
        {marketsToUse.map((m) => {
          const isLive = liveMarkets.includes(m);
          return (
            <span key={m} style={{
              padding: "3px 10px", borderRadius: 20,
              background: isLive ? "#dcfce7" : "#f9fafb",
              color: isLive ? "#166534" : "#9ca3af",
              border: `1.5px solid ${isLive ? "#86efac" : "#e5e7eb"}`,
              fontWeight: 600, fontSize: 12,
            }}>
              {isLive ? "🟢" : "⚪"} {m}
            </span>
          );
        })}
        {liveMarkets.length > 0 ? (
          <span style={{ color: "#16a34a", alignSelf: "center" }}>（実データのみで集計）</span>
        ) : (
          <span style={{ color: "#9ca3af", alignSelf: "center" }}>（全て mock）</span>
        )}
      </div>

      <div style={{ display: "flex", gap: 16, alignItems: "end", flexWrap: "wrap", margin: "16px 0" }}>
        <label style={{ fontSize: 14, fontWeight: 600 }}>
          最低利益率<br />
          <input
            type="number" step="0.05" min="0" max="1" value={minMarginRate}
            onChange={(e) => setMinMarginRate(Number(e.target.value))}
            style={{ width: 100, marginTop: 4 }}
          />
        </label>
        <label style={{ fontSize: 14, fontWeight: 600 }}>
          最低グレード<br />
          <select value={minGrade} onChange={(e) => setMinGrade(e.target.value as "A" | "B" | "C")} style={{ marginTop: 4 }}>
            <option value="A">A</option>
            <option value="B">B</option>
            <option value="C">C</option>
          </select>
        </label>
        <button
          onClick={runScreen} disabled={loading || keywords.length === 0}
          style={{
            padding: "10px 24px",
            background: "linear-gradient(135deg, #f472b6, #a78bfa)",
            color: "#fff", border: 0, borderRadius: 20, cursor: "pointer",
            fontWeight: 700, fontSize: 14,
            boxShadow: "0 2px 12px rgba(244,114,182,0.3)",
          }}
        >
          {loading ? "🐾 調査中…" : `スクリーニング実行（${keywords.length}件）`}
        </button>
      </div>

      {error && <p style={{ color: "#dc2626" }}>😿 {error}</p>}

      {items.length > 0 && (
        <div style={{ overflowX: "auto", background: "#fff", borderRadius: "var(--radius)", border: "2px solid var(--card-border)", padding: 4 }}>
          <table>
            <thead>
              <tr>
                {["#", "商品", "判定", "スコア", "売値(中央)", "原価", "利益", "利益率", "ROI", "BASE出品", "計測"].map((h) => (
                  <th key={h}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {items.map((it, i) => {
                const gs = GRADE_STYLE[it.score.grade] ?? GRADE_STYLE.C;
                return (
                  <tr key={it.key}>
                    <td>{i + 1}</td>
                    <td style={{ fontWeight: 500 }}>{it.keyword}</td>
                    <td>
                      <span style={{
                        background: gs.bg, color: gs.color,
                        padding: "3px 12px", borderRadius: 20, fontWeight: 700, fontSize: 13,
                        display: "inline-flex", alignItems: "center", gap: 4,
                      }}>
                        {gs.emoji} {it.score.grade}
                      </span>
                    </td>
                    <td style={{ fontWeight: 600 }}>{it.score.score}</td>
                    <td>{yen(it.chosen?.sellPrice)}</td>
                    <td>{yen(it.landedCost)}</td>
                    <td style={{ fontWeight: 700, color: "#16a34a" }}>{yen(it.chosen?.profit)}</td>
                    <td>{pct(it.chosen?.marginRate)}</td>
                    <td>{pct(it.chosen?.roi)}</td>
                    <td>{renderPublish(it)}</td>
                    <td>
                      <a href={`/marketing?campaign=${encodeURIComponent(it.key)}`}
                        style={{ fontSize: 13, fontWeight: 600 }}>
                        UTMリンク
                      </a>
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
