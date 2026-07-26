"use client";

import { useEffect, useState } from "react";

interface ConnectorGuide {
  key: string;
  name: string;
  type: string;
  typeLabel: string;
  free: boolean;
  envVars: string[];
  registrationUrl: string;
  steps: string[];
}

const GUIDES: ConnectorGuide[] = [
  {
    key: "yahoo",
    name: "Yahoo!ショッピング",
    type: "調査",
    typeLabel: "市場調査",
    free: true,
    envVars: ["YAHOO_APP_ID"],
    registrationUrl: "https://e.developer.yahoo.co.jp/register",
    steps: [
      "Yahoo! JAPAN ID でログイン（なければ作成）",
      "上のリンクから「アプリケーションの管理」ページへ",
      "「新しいアプリケーションの登録」をクリック",
      "アプリ名: necorope、種別: サーバーサイド、コールバックURL: 空欄でOK",
      "登録すると「Client ID（アプリケーションID）」が表示される",
      "RenderのEnvironment Variables に YAHOO_APP_ID として設定",
    ],
  },
  {
    key: "ebay",
    name: "eBay",
    type: "調査",
    typeLabel: "海外市場調査",
    free: true,
    envVars: ["EBAY_OAUTH_TOKEN"],
    registrationUrl: "https://developer.ebay.com/signin",
    steps: [
      "eBay Developer Program に登録（無料）",
      "Application Keys ページで「Create a keyset」",
      "Production 用の App ID / Cert ID / Dev ID を取得",
      "OAuth Token Tool で Application Token（client_credentials）を生成",
      "RenderのEnvironment Variables に EBAY_OAUTH_TOKEN として設定",
    ],
  },
  {
    key: "amazon",
    name: "Amazon（Keepa経由）",
    type: "調査",
    typeLabel: "市場調査",
    free: false,
    envVars: ["KEEPA_API_KEY"],
    registrationUrl: "https://keepa.com/#!api",
    steps: [
      "Keepa.com にアカウント登録",
      "API Access ページでプランを購入（月額 $19〜）",
      "API Key をコピー",
      "RenderのEnvironment Variables に KEEPA_API_KEY として設定",
    ],
  },
  {
    key: "base",
    name: "BASE",
    type: "販売",
    typeLabel: "販売チャネル",
    free: true,
    envVars: ["BASE_CLIENT_ID", "BASE_CLIENT_SECRET"],
    registrationUrl: "https://developers.thebase.in/",
    steps: [
      "BASE Developers にログイン（BASEショップアカウントが必要）",
      "「アプリを作成」→ アプリ名: necorope",
      "リダイレクトURI: Render URL + /auth/base/callback を入力",
      "Client ID と Client Secret をコピー",
      "Renderに BASE_CLIENT_ID と BASE_CLIENT_SECRET を設定",
      "ダッシュボードの「BASE と連携する」ボタンでOAuth認証",
    ],
  },
  {
    key: "theckb",
    name: "THE CKB",
    type: "仕入",
    typeLabel: "仕入れ先",
    free: false,
    envVars: ["THECKB_API_KEY"],
    registrationUrl: "https://www.theckb.com/",
    steps: [
      "THE CKB にパートナー登録（法人/個人事業主）",
      "API連携の申請を行う",
      "審査通過後、API Key が発行される",
      "RenderのEnvironment Variables に THECKB_API_KEY として設定",
    ],
  },
  {
    key: "alibaba",
    name: "Alibaba.com",
    type: "仕入",
    typeLabel: "仕入れ先",
    free: false,
    envVars: ["ALIBABA_ACCESS_TOKEN"],
    registrationUrl: "https://open.alibaba.com/",
    steps: [
      "Alibaba.com Open Platform にアプリ登録",
      "アプリ審査を通過させる",
      "Access Token を取得（OAuth2.0フロー）",
      "RenderのEnvironment Variables に ALIBABA_ACCESS_TOKEN として設定",
    ],
  },
  {
    key: "aliexpress",
    name: "AliExpress",
    type: "仕入",
    typeLabel: "仕入れ先",
    free: false,
    envVars: ["ALIEXPRESS_APP_KEY", "ALIEXPRESS_APP_SECRET"],
    registrationUrl: "https://openservice.aliexpress.com/",
    steps: [
      "AliExpress Open Platform でDropshipping APIを申請",
      "App Key と App Secret を取得",
      "RenderのEnvironment Variables に設定",
    ],
  },
];

const TYPE_COLOR: Record<string, string> = {
  "調査": "#3b82f6",
  "販売": "#10b981",
  "仕入": "#f59e0b",
};

export default function SetupPage() {
  const [modes, setModes] = useState<Record<string, string> | null>(null);

  useEffect(() => {
    fetch("/api/connectors")
      .then((r) => r.json())
      .then((d) => { if (d.modes) setModes(d.modes); })
      .catch(() => {});
  }, []);

  const connected = modes ? Object.values(modes).filter((m) => m === "live").length : 0;
  const total = modes ? Object.keys(modes).length : 8;

  return (
    <div style={{ maxWidth: 800 }}>
      <p style={{ marginBottom: 16 }}>
        <a href="/">← ダッシュボード</a>
      </p>
      <h1 style={{ fontSize: 22 }}>🔌 接続セットアップ</h1>
      <p style={{ color: "var(--muted)", fontSize: 14, marginBottom: 8 }}>
        各コネクターのAPIキーを取得して、Renderの環境変数に設定するとランプが緑になります。
      </p>

      {modes && (
        <div style={{
          display: "flex", alignItems: "center", gap: 12,
          padding: "14px 18px", marginBottom: 20, borderRadius: "var(--radius)",
          background: connected === total ? "#dcfce7" : "#fff",
          border: `2px solid ${connected === total ? "#86efac" : "var(--card-border)"}`,
        }}>
          <span style={{ fontSize: 28 }}>{connected === total ? "🎉" : "🔧"}</span>
          <div>
            <span style={{ fontWeight: 700, fontSize: 16 }}>
              {connected} / {total} 接続済み
            </span>
            <div style={{
              marginTop: 4, height: 6, width: 200, borderRadius: 3, background: "#e5e7eb",
            }}>
              <div style={{
                height: 6, borderRadius: 3, width: `${(connected / total) * 100}%`,
                background: "linear-gradient(90deg, #34d399, #3b82f6)",
                transition: "width 0.5s",
              }} />
            </div>
          </div>
        </div>
      )}

      <div style={{
        padding: "12px 16px", marginBottom: 20, borderRadius: "var(--radius-sm)",
        background: "#fef3c7", border: "1.5px solid #fde68a", fontSize: 13,
      }}>
        ⚠️ APIキーはチャットに貼り付けず、Renderダッシュボードの Environment Variables に直接入力してください。
      </div>

      {GUIDES.map((g) => {
        const isLive = modes?.[g.key] === "live";
        return (
          <div key={g.key} style={{
            background: "#fff", border: `2px solid ${isLive ? "#86efac" : "var(--card-border)"}`,
            borderRadius: "var(--radius)", padding: 20, marginBottom: 14,
            opacity: isLive ? 0.7 : 1,
          }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 12 }}>
              <span style={{ fontSize: 18 }}>{isLive ? "🟢" : "⚪"}</span>
              <h3 style={{ margin: 0, fontSize: 16 }}>{g.name}</h3>
              <span style={{
                padding: "2px 8px", borderRadius: 12, fontSize: 11, fontWeight: 600,
                background: (TYPE_COLOR[g.type] ?? "#999") + "18",
                color: TYPE_COLOR[g.type] ?? "#999",
              }}>
                {g.typeLabel}
              </span>
              {g.free ? (
                <span style={{
                  padding: "2px 8px", borderRadius: 12, fontSize: 11, fontWeight: 600,
                  background: "#dcfce7", color: "#166534",
                }}>無料</span>
              ) : (
                <span style={{
                  padding: "2px 8px", borderRadius: 12, fontSize: 11, fontWeight: 600,
                  background: "#fef3c7", color: "#92400e",
                }}>有料/要申請</span>
              )}
              {isLive && (
                <span style={{
                  marginLeft: "auto", fontSize: 12, fontWeight: 600, color: "#16a34a",
                }}>接続済み ✓</span>
              )}
            </div>

            {!isLive && (
              <>
                <div style={{ marginBottom: 10 }}>
                  <span style={{ fontSize: 12, fontWeight: 600, color: "var(--muted)" }}>必要な環境変数：</span>
                  {g.envVars.map((v) => (
                    <code key={v} style={{
                      marginLeft: 6, padding: "2px 6px", borderRadius: 4,
                      background: "#f3f4f6", fontSize: 12, fontFamily: "monospace",
                    }}>{v}</code>
                  ))}
                </div>

                <ol style={{ margin: "0 0 12px", paddingLeft: 20 }}>
                  {g.steps.map((step, i) => (
                    <li key={i} style={{ fontSize: 13, lineHeight: 1.8, color: "#374151" }}>{step}</li>
                  ))}
                </ol>

                <a
                  href={g.registrationUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  style={{
                    display: "inline-block", padding: "6px 16px", borderRadius: 20,
                    fontSize: 12, fontWeight: 700, textDecoration: "none",
                    background: "linear-gradient(135deg, #60a5fa, #3b82f6)",
                    color: "#fff", boxShadow: "0 2px 8px rgba(59,130,246,0.25)",
                  }}
                >
                  登録ページを開く →
                </a>
              </>
            )}
          </div>
        );
      })}

      <div style={{
        background: "#fff", border: "2px solid var(--card-border)",
        borderRadius: "var(--radius)", padding: 20, marginBottom: 14,
      }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 12 }}>
          <span style={{ fontSize: 18 }}>🔔</span>
          <h3 style={{ margin: 0, fontSize: 16 }}>外部通知（Slack / LINE）</h3>
          <span style={{
            padding: "2px 8px", borderRadius: 12, fontSize: 11, fontWeight: 600,
            background: "#dcfce7", color: "#166534",
          }}>無料</span>
        </div>
        <p style={{ fontSize: 13, color: "var(--muted)", margin: "0 0 12px" }}>
          在庫切れ・売れ筋検出などのアラートを Slack や LINE に自動通知できます。
        </p>

        <h4 style={{ fontSize: 14, margin: "12px 0 6px" }}>Slack</h4>
        <ol style={{ margin: 0, paddingLeft: 20 }}>
          <li style={{ fontSize: 13, lineHeight: 1.8 }}>Slack ワークスペースで「Incoming Webhooks」アプリを追加</li>
          <li style={{ fontSize: 13, lineHeight: 1.8 }}>通知先チャンネルを選択して Webhook URL を取得</li>
          <li style={{ fontSize: 13, lineHeight: 1.8 }}>Render に <code style={{ padding: "2px 6px", borderRadius: 4, background: "#f3f4f6", fontSize: 12, fontFamily: "monospace" }}>SLACK_WEBHOOK_URL</code> として設定</li>
        </ol>

        <h4 style={{ fontSize: 14, margin: "12px 0 6px" }}>LINE Notify</h4>
        <ol style={{ margin: 0, paddingLeft: 20 }}>
          <li style={{ fontSize: 13, lineHeight: 1.8 }}>LINE Notify（notify-bot.line.me）にログイン</li>
          <li style={{ fontSize: 13, lineHeight: 1.8 }}>「トークンを発行する」→ 通知先グループ or 1:1を選択</li>
          <li style={{ fontSize: 13, lineHeight: 1.8 }}>Render に <code style={{ padding: "2px 6px", borderRadius: 4, background: "#f3f4f6", fontSize: 12, fontFamily: "monospace" }}>LINE_NOTIFY_TOKEN</code> として設定</li>
        </ol>
      </div>
    </div>
  );
}
