import ConnectorStatus from "./connector-status";

const sections = [
  { title: "猫グッズ スクリーニング", icon: "🔍", desc: "Amazon・楽天で調査し利益率で採点・ランキング", href: "/research", ready: true, color: "#a78bfa" },
  { title: "SNS集客リンク（UTM）", icon: "📣", desc: "投稿ごとの計測リンクを発行（TikTok/Instagram）", href: "/marketing", ready: true, color: "#f472b6" },
  { title: "受注 / 損益", icon: "🧾", desc: "受注一覧と売上・原価・手数料・利益の可視化", href: "/orders", ready: true, color: "#6ee7b7" },
  { title: "在庫・価格同期", icon: "🔄", desc: "仕入れ先を監視し欠品は自動非公開・価格は再計算", href: "/sync", ready: true, color: "#fdba74" },
  { title: "商品・出品管理", icon: "📦", desc: "取り込み・出品状態の管理", href: "#", ready: false, color: "#d1d5db" },
];

const paws = ["🐾", "🐱", "😺", "🐈", "✨"];

export default function DashboardPage() {
  return (
    <div>
      <div style={{ textAlign: "center", margin: "8px 0 28px" }}>
        <h1 style={{ fontSize: 26, margin: "0 0 6px", color: "var(--ink)" }}>
          🐱 ダッシュボード
        </h1>
        <p style={{ color: "var(--muted)", margin: 0, fontSize: 14 }}>
          猫グッズ無在庫販売の全機能をここから操作できます
        </p>
      </div>

      <ConnectorStatus />

      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))", gap: 16 }}>
        {sections.map((s, i) => (
          <a
            key={s.title}
            href={s.href}
            style={{
              display: "block",
              padding: 22,
              border: "2px solid var(--card-border)",
              borderRadius: "var(--radius)",
              textDecoration: "none",
              color: "inherit",
              opacity: s.ready ? 1 : 0.5,
              background: s.ready ? "#fff" : "#fafafa",
              boxShadow: s.ready ? "var(--shadow)" : "none",
              transition: "transform 0.2s, box-shadow 0.2s",
              position: "relative" as const,
              overflow: "hidden" as const,
            }}
          >
            <div style={{
              position: "absolute" as const, top: -8, right: -8,
              fontSize: 48, opacity: 0.08, transform: "rotate(15deg)",
              pointerEvents: "none" as const,
            }}>
              {paws[i % paws.length]}
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 8 }}>
              <span style={{
                width: 36, height: 36, borderRadius: 10, display: "grid", placeItems: "center",
                background: s.ready ? s.color + "20" : "#f3f4f6",
                fontSize: 18,
              }}>
                {s.icon}
              </span>
              <h3 style={{ margin: 0, fontSize: 15, fontWeight: 700 }}>
                {s.title}
              </h3>
            </div>
            <p style={{ margin: 0, color: "var(--muted)", fontSize: 13, lineHeight: 1.5 }}>{s.desc}</p>
            {s.ready && (
              <div style={{ marginTop: 12, fontSize: 12, color: "var(--primary)", fontWeight: 600 }}>
                開く →
              </div>
            )}
            {!s.ready && (
              <div style={{ marginTop: 12, fontSize: 12, color: "#aaa" }}>
                🚧 準備中
              </div>
            )}
          </a>
        ))}
      </div>
    </div>
  );
}
