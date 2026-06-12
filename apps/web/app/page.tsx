const sections = [
  { title: "商品一覧", desc: "仕入れ取り込み・出品状態の管理", href: "#" },
  { title: "在庫・価格同期", desc: "仕入れ先の在庫/価格を監視し BASE へ反映", href: "#" },
  { title: "受注 / 自動発注", desc: "BASE 注文 → 仕入れ先へ自動発注", href: "#" },
  { title: "損益", desc: "原価・売上・手数料・利益の可視化", href: "#" },
  { title: "設定", desc: "API 連携・価格ルール・規約フィルタ", href: "#" },
];

export default function DashboardPage() {
  return (
    <div>
      <h1>ダッシュボード（骨組み）</h1>
      <p style={{ color: "#666" }}>
        Hub API: <code>{process.env.HUB_API_URL}</code>。各セクションは順次実装します。
      </p>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(240px, 1fr))", gap: 16 }}>
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
            }}
          >
            <h3 style={{ margin: "0 0 8px" }}>{s.title}</h3>
            <p style={{ margin: 0, color: "#777", fontSize: 14 }}>{s.desc}</p>
          </a>
        ))}
      </div>
    </div>
  );
}
