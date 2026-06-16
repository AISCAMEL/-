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
        <a href="/research" style={{ color: "#e8612e" }}>← スクリーニング</a>
        {"  |  "}
        <a href="/" style={{ color: "#e8612e" }}>ダッシュボード</a>
      </p>
      <h1>SNS集客リンク（UTM）発行</h1>
      <p style={{ color: "#666" }}>投稿ごとに計測リンクを発行し、どの投稿・プラットフォームから売れたかを把握します。</p>

      <div style={{ display: "grid", gap: 12, margin: "16px 0" }}>
        <label>商品URL（BASE）
          <input value={url} onChange={(e) => setUrl(e.target.value)} style={{ width: "100%", padding: 8 }} />
        </label>
        <label>プラットフォーム
          <select value={platform} onChange={(e) => setPlatform(e.target.value as typeof platform)} style={{ display: "block", padding: 8 }}>
            <option value="tiktok">TikTok</option>
            <option value="instagram">Instagram</option>
            <option value="x">X</option>
            <option value="youtube">YouTube</option>
          </select>
        </label>
        <label>キャンペーン名（商品/施策）
          <input value={campaign} onChange={(e) => setCampaign(e.target.value)} style={{ width: "100%", padding: 8 }} />
        </label>
        <label>投稿識別子（A/Bテスト用）
          <input value={content} onChange={(e) => setContent(e.target.value)} style={{ width: "100%", padding: 8 }} />
        </label>
        <button onClick={generate} style={{ padding: "8px 20px", background: "#e8612e", color: "#fff", border: 0, borderRadius: 8, cursor: "pointer", width: "fit-content" }}>
          リンク生成
        </button>
      </div>

      {error && <p style={{ color: "#dc2626" }}>{error}</p>}
      {link && (
        <div style={{ padding: 16, background: "#f8fafc", border: "1px solid #e5e7eb", borderRadius: 8 }}>
          <code style={{ wordBreak: "break-all" }}>{link}</code>
          <div style={{ marginTop: 8 }}>
            <button
              onClick={() => { navigator.clipboard?.writeText(link); setCopied(true); }}
              style={{ padding: "4px 12px", cursor: "pointer" }}
            >
              {copied ? "コピーしました" : "コピー"}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export default function MarketingPage() {
  return (
    <Suspense fallback={<p>読み込み中…</p>}>
      <MarketingInner />
    </Suspense>
  );
}
