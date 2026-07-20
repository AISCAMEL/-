"use client";

import { Suspense, useState } from "react";
import { useSearchParams } from "next/navigation";

function MarketingInner() {
  const params = useSearchParams();
  const [url, setUrl] = useState("https://shop.example.com/items/123");
  const [platform, setPlatform] = useState<"instagram" | "tiktok" | "x" | "youtube">("tiktok");
  const [campaign, setCampaign] = useState(params.get("campaign") ?? "cat_goods_0616");
  const [content, setContent] = useState("reel_a");
  const [link, setLink] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  async function generate() {
    setError(null);
    setCopied(false);
    const q = new URLSearchParams({ url, platform, campaign, ...(content ? { content } : {}) });
    const res = await fetch(`/api/link?${q.toString()}`);
    const data = await res.json();
    if (!res.ok) return setError(data.error ? JSON.stringify(data.error) : "リンク生成失敗");
    setLink(data.link);
  }

  return (
    <div style={{ maxWidth: 720 }}>
      <p style={{ marginBottom: 16 }}>
        <a href="/research">← スクリーニング</a>
        {"  |  "}
        <a href="/">ダッシュボード</a>
      </p>
      <h1 style={{ fontSize: 22 }}>📣 SNS集客リンク（UTM）発行</h1>
      <p style={{ color: "var(--muted)", fontSize: 14 }}>投稿ごとに計測リンクを発行し、どの投稿・プラットフォームから売れたかを把握します。</p>

      <div style={{
        display: "grid", gap: 14, margin: "16px 0",
        background: "#fff", padding: 24, borderRadius: "var(--radius)",
        border: "2px solid var(--card-border)", boxShadow: "var(--shadow)",
      }}>
        <label style={{ fontSize: 14, fontWeight: 600 }}>商品URL（BASE）
          <input value={url} onChange={(e) => setUrl(e.target.value)} style={{ width: "100%", marginTop: 4 }} />
        </label>
        <label style={{ fontSize: 14, fontWeight: 600 }}>プラットフォーム
          <select value={platform} onChange={(e) => setPlatform(e.target.value as typeof platform)} style={{ display: "block", marginTop: 4 }}>
            <option value="tiktok">🎵 TikTok</option>
            <option value="instagram">📸 Instagram</option>
            <option value="x">✖️ X</option>
            <option value="youtube">▶️ YouTube</option>
          </select>
        </label>
        <label style={{ fontSize: 14, fontWeight: 600 }}>キャンペーン名（商品/施策）
          <input value={campaign} onChange={(e) => setCampaign(e.target.value)} style={{ width: "100%", marginTop: 4 }} />
        </label>
        <label style={{ fontSize: 14, fontWeight: 600 }}>投稿識別子（A/Bテスト用）
          <input value={content} onChange={(e) => setContent(e.target.value)} style={{ width: "100%", marginTop: 4 }} />
        </label>
        <button onClick={generate} style={{
          padding: "10px 24px",
          background: "linear-gradient(135deg, #f472b6, #a78bfa)",
          color: "#fff", border: 0, borderRadius: 20, cursor: "pointer",
          fontWeight: 700, fontSize: 14, width: "fit-content",
          boxShadow: "0 2px 12px rgba(244,114,182,0.3)",
        }}>
          🐾 リンク生成
        </button>
      </div>

      {error && <p style={{ color: "#dc2626" }}>😿 {error}</p>}
      {link && (
        <div style={{
          padding: 20, background: "#fff",
          border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
          boxShadow: "var(--shadow)",
        }}>
          <div style={{ fontSize: 13, color: "var(--muted)", marginBottom: 8 }}>🔗 生成されたリンク</div>
          <code style={{ wordBreak: "break-all", fontSize: 14, color: "var(--ink)", lineHeight: 1.6 }}>{link}</code>
          <div style={{ marginTop: 12 }}>
            <button
              onClick={() => { navigator.clipboard?.writeText(link); setCopied(true); }}
              style={{
                padding: "6px 16px", cursor: "pointer", borderRadius: 20,
                border: copied ? "2px solid #86efac" : "2px solid var(--card-border)",
                background: copied ? "#dcfce7" : "#fff",
                color: copied ? "#166534" : "var(--ink)",
                fontWeight: 600, fontSize: 13,
              }}
            >
              {copied ? "✅ コピーしました" : "📋 コピー"}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export default function MarketingPage() {
  return (
    <Suspense fallback={<p style={{ color: "var(--muted)" }}>🐾 読み込み中…</p>}>
      <MarketingInner />
    </Suspense>
  );
}
