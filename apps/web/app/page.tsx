import ConnectorStatus from "./connector-status";

const sections = [
  { title: "🔎 猫グッズ スクリーニング", desc: "Amazon・楽天で調査し利益率で採点・ランキング", href: "/research", ready: true },
  { title: "📣 SNS集客リンク（UTM）", desc: "投稿ごとの計測リンクを発行（TikTok/Instagram）", href: "/marketing", ready: true },
  { title: "📦 商品・出品管理", desc: "取り込み・出品状態の管理", href: "#", ready: false },
  { title: "🔄 在庫・価格同期", desc: "仕入れ先を監視し BASE へ反映", href: "#", ready: false },
  { title: "🧾 受注 / 自動発注", desc: "BASE 注文 → 仕入れ先へ自動発注", href: "#", ready: false },
  { title: "💹 損益", desc: "原価・売上・手数料・利益の可視化", href: "#", ready: false },
];

export default function DashboardPage() {
  return (
    <div>
      <h1>猫グッズ 無在庫販売ハブ — ダッシュボード</h1>
      <p style={{ color: "#666" }}>
        Hub API: <code>{process.env.HUB_API_URL}</code>。✅ が利用可能な機能です。
      </p>
      <ConnectorStatus />
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))", gap: 16 }}>
        {sections.map((s) => (
          <a
            key={s.title}
            href={s.href}
            style={{
              display: "block",
              padding: 20,
              border: "1px solid #e5e5e5",
              borderRadius: 12,
              textDecoration: "none",
              color: "inherit",
              opacity: s.ready ? 1 : 0.55,
              background: s.ready ? "#fff" : "#fafafa",
            }}
          >
            <h3 style={{ margin: "0 0 8px" }}>
              {s.title} {s.ready ? "✅" : "🚧"}
            </h3>
            <p style={{ margin: 0, color: "#777", fontSize: 14 }}>{s.desc}</p>
          </a>
        ))}
      </div>
    </div>
  );
}
