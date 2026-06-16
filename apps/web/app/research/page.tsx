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

const GRADE_COLOR: Record<string, string> = { A: "#16a34a", B: "#ca8a04", C: "#9ca3af" };
const yen = (n: number | null | undefined) => (n == null ? "-" : `¥${n.toLocaleString()}`);
const pct = (n: number | undefined) => (n == null ? "-" : `${(n * 100).toFixed(1)}%`);

export default function ResearchPage() {
  const [keywords, setKeywords] = useState<string[]>([]);
  const [minMarginRate, setMinMarginRate] = useState(0.3);
  const [minGrade, setMinGrade] = useState<"A" | "B" | "C">("B");
  const [items, setItems] = useState<ScreenedItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // 猫グッズのキーワードと推奨設定を読み込む
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
        body: JSON.stringify({ candidates, markets: ["amazon", "rakuten"], minMarginRate, minGrade }),
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

  return (
    <div>
      <p style={{ marginBottom: 16 }}>
        <a href="/" style={{ color: "#2563eb" }}>← ダッシュボード</a>
      </p>
      <h1>猫グッズ スクリーニング</h1>
      <p style={{ color: "#666" }}>
        猫グッズのキーワードを Amazon・楽天で調査し、仕入れ値と突き合わせて利益率で採点・ランキングします。
      </p>

      <div style={{ display: "flex", gap: 16, alignItems: "end", flexWrap: "wrap", margin: "16px 0" }}>
        <label>
          最低利益率<br />
          <input
            type="number" step="0.05" min="0" max="1" value={minMarginRate}
            onChange={(e) => setMinMarginRate(Number(e.target.value))}
            style={{ padding: 6, width: 100 }}
          />
        </label>
        <label>
          最低グレード<br />
          <select value={minGrade} onChange={(e) => setMinGrade(e.target.value as "A" | "B" | "C")} style={{ padding: 6 }}>
            <option value="A">A</option>
            <option value="B">B</option>
            <option value="C">C</option>
          </select>
        </label>
        <button
          onClick={runScreen} disabled={loading || keywords.length === 0}
          style={{ padding: "8px 20px", background: "#2563eb", color: "#fff", border: 0, borderRadius: 8, cursor: "pointer" }}
        >
          {loading ? "調査中…" : `スクリーニング実行（${keywords.length}件）`}
        </button>
      </div>

      {error && <p style={{ color: "#dc2626" }}>{error}</p>}

      {items.length > 0 && (
        <table style={{ borderCollapse: "collapse", width: "100%", fontSize: 14 }}>
          <thead>
            <tr style={{ textAlign: "left", borderBottom: "2px solid #e5e7eb" }}>
              {["#", "商品", "判定", "スコア", "売値(中央)", "原価", "利益", "利益率", "ROI", "操作"].map((h) => (
                <th key={h} style={{ padding: "8px 6px" }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {items.map((it, i) => (
              <tr key={it.key} style={{ borderBottom: "1px solid #f0f0f0" }}>
                <td style={{ padding: "8px 6px" }}>{i + 1}</td>
                <td style={{ padding: "8px 6px" }}>{it.keyword}</td>
                <td style={{ padding: "8px 6px" }}>
                  <span style={{ background: GRADE_COLOR[it.score.grade], color: "#fff", padding: "2px 10px", borderRadius: 12, fontWeight: 700 }}>
                    {it.score.grade}
                  </span>
                </td>
                <td style={{ padding: "8px 6px" }}>{it.score.score}</td>
                <td style={{ padding: "8px 6px" }}>{yen(it.chosen?.sellPrice)}</td>
                <td style={{ padding: "8px 6px" }}>{yen(it.landedCost)}</td>
                <td style={{ padding: "8px 6px", fontWeight: 600 }}>{yen(it.chosen?.profit)}</td>
                <td style={{ padding: "8px 6px" }}>{pct(it.chosen?.marginRate)}</td>
                <td style={{ padding: "8px 6px" }}>{pct(it.chosen?.roi)}</td>
                <td style={{ padding: "8px 6px" }}>
                  <a href={`/marketing?campaign=${encodeURIComponent(it.key)}`} style={{ color: "#2563eb" }}>UTMリンク</a>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
