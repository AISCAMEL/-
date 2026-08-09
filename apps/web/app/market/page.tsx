"use client";

import { useState } from "react";

interface MarketListing {
  marketId: string;
  title: string;
  price: number;
  url: string;
  reviewCount?: number;
  rating?: number;
}
interface MarketStats {
  sampleCount: number;
  median: number;
  avg: number;
  min: number;
  max: number;
}
interface ResearchResult {
  keyword: string;
  market: {
    byMarket: Record<string, MarketStats>;
    overall: MarketStats;
    listings: MarketListing[];
  };
}

const yen = (n: number) => `¥${Math.round(n).toLocaleString()}`;

const MARKET_LABEL: Record<string, string> = {
  rakuten: "楽天",
  yahoo: "Yahoo!",
  amazon: "Amazon",
  ebay: "eBay",
};

const SUGGESTED_KEYWORDS = [
  "猫 キャットタワー",
  "猫 爪とぎ",
  "猫 トンネル おもちゃ",
  "猫 ベッド ドーム",
  "猫 自動給餌器",
  "猫 ハンモック 窓",
  "猫 キャリーバッグ",
  "猫 トイレ 大型",
  "猫 首輪 GPS",
  "猫 水飲み 自動",
];

export default function MarketResearchPage() {
  const [keyword, setKeyword] = useState("");
  const [results, setResults] = useState<ResearchResult | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [history, setHistory] = useState<ResearchResult[]>([]);

  async function search(kw?: string) {
    const q = kw ?? keyword;
    if (!q.trim()) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetch("/api/research", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({
          keyword: q.trim(),
          markets: ["rakuten", "yahoo"],
          limit: 20,
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error ?? "調査失敗");
      setResults(data);
      setHistory((h) => {
        const filtered = h.filter((r) => r.keyword !== data.keyword);
        return [data, ...filtered].slice(0, 10);
      });
    } catch (e) {
      setError(String(e));
      setResults(null);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div style={{ maxWidth: 900 }}>
      <p style={{ marginBottom: 16 }}>
        <a href="/">← ダッシュボード</a>
      </p>
      <h1 style={{ fontSize: 22 }}>📊 市場調査（無料）</h1>
      <p style={{ color: "var(--muted)", fontSize: 14, marginBottom: 16 }}>
        キーワードで楽天・Yahoo! の商品を検索し、価格帯とレビュー数を確認できます。
        仕入れ先APIは不要です。
      </p>

      <div style={{
        display: "flex", gap: 8, alignItems: "center", marginBottom: 16,
      }}>
        <input
          type="text"
          value={keyword}
          onChange={(e) => setKeyword(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && search()}
          placeholder="例: 猫 キャットタワー"
          style={{ flex: 1, padding: "10px 14px", fontSize: 15 }}
        />
        <button
          onClick={() => search()}
          disabled={loading || !keyword.trim()}
          style={{
            padding: "10px 24px", borderRadius: 20, border: 0, cursor: "pointer",
            fontWeight: 700, fontSize: 14, color: "#fff",
            background: "linear-gradient(135deg, #60a5fa, #3b82f6)",
            boxShadow: "0 2px 8px rgba(59,130,246,0.3)",
          }}
        >
          {loading ? "🔍 調査中…" : "調査する"}
        </button>
      </div>

      <div style={{ marginBottom: 20 }}>
        <span style={{ fontSize: 12, color: "var(--muted)", marginRight: 8 }}>おすすめ:</span>
        <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
          {SUGGESTED_KEYWORDS.map((kw) => (
            <button
              key={kw}
              onClick={() => { setKeyword(kw); search(kw); }}
              disabled={loading}
              style={{
                padding: "4px 12px", borderRadius: 16, border: "1.5px solid #e5e7eb",
                background: "#fff", cursor: "pointer", fontSize: 12, color: "#374151",
              }}
            >
              {kw}
            </button>
          ))}
        </div>
      </div>

      {error && <p style={{ color: "#dc2626" }}>😿 {error}</p>}

      {results && results.market.overall.sampleCount > 0 && (
        <>
          <div style={{
            display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(140px, 1fr))",
            gap: 10, marginBottom: 20,
          }}>
            <StatCard label="検索結果" value={`${results.market.overall.sampleCount}件`} />
            <StatCard label="中央値" value={yen(results.market.overall.median)} highlight />
            <StatCard label="平均価格" value={yen(results.market.overall.avg)} />
            <StatCard label="最安値" value={yen(results.market.overall.min)} />
            <StatCard label="最高値" value={yen(results.market.overall.max)} />
          </div>

          {Object.entries(results.market.byMarket).length > 1 && (
            <div style={{
              display: "flex", gap: 10, marginBottom: 20, flexWrap: "wrap",
            }}>
              {Object.entries(results.market.byMarket).map(([mId, stats]) => (
                <div key={mId} style={{
                  flex: 1, minWidth: 200, padding: "12px 16px",
                  background: "#fff", border: "2px solid var(--card-border)",
                  borderRadius: "var(--radius)",
                }}>
                  <div style={{ fontWeight: 700, fontSize: 14, marginBottom: 6 }}>
                    {MARKET_LABEL[mId] ?? mId}
                  </div>
                  <div style={{ fontSize: 13, color: "var(--muted)", lineHeight: 1.8 }}>
                    件数: {stats.sampleCount} / 中央値: {yen(stats.median)} / 平均: {yen(stats.avg)}
                  </div>
                </div>
              ))}
            </div>
          )}

          <h3 style={{ fontSize: 15, marginBottom: 8 }}>商品一覧（価格順）</h3>
          <div style={{ overflowX: "auto" }}>
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>モール</th>
                  <th>商品名</th>
                  <th>価格</th>
                  <th>レビュー</th>
                  <th>評価</th>
                </tr>
              </thead>
              <tbody>
                {[...results.market.listings]
                  .sort((a, b) => a.price - b.price)
                  .map((l, i) => (
                    <tr key={i}>
                      <td>{i + 1}</td>
                      <td>
                        <span style={{
                          padding: "2px 8px", borderRadius: 10, fontSize: 11, fontWeight: 600,
                          background: l.marketId === "rakuten" ? "#bf000014" : "#ff008014",
                          color: l.marketId === "rakuten" ? "#bf0000" : "#ff0080",
                        }}>
                          {MARKET_LABEL[l.marketId] ?? l.marketId}
                        </span>
                      </td>
                      <td style={{ maxWidth: 350, fontSize: 13 }}>
                        {l.url ? (
                          <a href={l.url} target="_blank" rel="noopener noreferrer">
                            {l.title.length > 60 ? l.title.slice(0, 60) + "…" : l.title}
                          </a>
                        ) : (
                          l.title.length > 60 ? l.title.slice(0, 60) + "…" : l.title
                        )}
                      </td>
                      <td style={{ fontWeight: 600, fontVariantNumeric: "tabular-nums" }}>
                        {yen(l.price)}
                      </td>
                      <td style={{ fontVariantNumeric: "tabular-nums" }}>
                        {l.reviewCount ?? "-"}
                      </td>
                      <td>
                        {l.rating ? `★${l.rating.toFixed(1)}` : "-"}
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>

          <div style={{
            marginTop: 16, padding: "14px 16px",
            background: "#eff6ff", border: "1.5px solid #bfdbfe",
            borderRadius: "var(--radius-sm)", fontSize: 13, lineHeight: 1.7,
          }}>
            💡 <strong>仕入れの目安:</strong> 中央値 {yen(results.market.overall.median)} の
            40〜50% が理想的な仕入れ価格です（{yen(results.market.overall.median * 0.4)}
            〜{yen(results.market.overall.median * 0.5)}）。
            AliExpress や 1688.com でこの価格帯の商品を探してみてください。
          </div>
        </>
      )}

      {results && results.market.overall.sampleCount === 0 && (
        <p style={{ color: "#f59e0b" }}>
          ⚠️ 検索結果が0件でした。キーワードを変えて再試行してください。
        </p>
      )}

      {history.length > 1 && (
        <div style={{ marginTop: 24 }}>
          <h3 style={{ fontSize: 15, marginBottom: 8 }}>📋 調査履歴</h3>
          <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
            {history.map((r) => (
              <button
                key={r.keyword}
                onClick={() => { setKeyword(r.keyword); setResults(r); }}
                style={{
                  padding: "6px 14px", borderRadius: 16,
                  border: r.keyword === results?.keyword ? "2px solid #3b82f6" : "1.5px solid #e5e7eb",
                  background: r.keyword === results?.keyword ? "#eff6ff" : "#fff",
                  cursor: "pointer", fontSize: 12,
                }}
              >
                {r.keyword}
                <span style={{ color: "var(--muted)", marginLeft: 6 }}>
                  {yen(r.market.overall.median)}
                </span>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function StatCard({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
  return (
    <div style={{
      padding: "14px 16px", textAlign: "center",
      background: highlight ? "#eff6ff" : "#fff",
      border: `2px solid ${highlight ? "#93c5fd" : "var(--card-border)"}`,
      borderRadius: "var(--radius)",
    }}>
      <div style={{
        fontSize: highlight ? 22 : 18, fontWeight: 700,
        fontVariantNumeric: "tabular-nums",
        color: highlight ? "#2563eb" : "inherit",
      }}>
        {value}
      </div>
      <div style={{ fontSize: 11, color: "var(--muted)", marginTop: 2 }}>{label}</div>
    </div>
  );
}
