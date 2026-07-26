export default function ManualPage() {
  const sections = [
    {
      icon: "🐱",
      title: "ダッシュボード",
      path: "/",
      steps: [
        "ログイン後に表示されるトップページです",
        "各機能カードをクリックして移動できます",
        "上部に為替レート（CNY→JPY）がリアルタイム表示されます",
        "未読アラート（在庫切れ・売れ筋検出など）がバナーで表示されます",
        "コネクター接続状況（THE CKB / BASE）も確認できます",
      ],
    },
    {
      icon: "🔍",
      title: "猫グッズ スクリーニング",
      path: "/research",
      steps: [
        "「スクリーニング開始」ボタンを押すと、猫グッズキーワードで市場調査を実行します",
        "結果は A / B / C のグレードで自動評価されます",
        "A評価 = 利益率が高く、市場に競合商品が存在するおすすめ商品",
        "各行の「楽天で見る」リンクから実際の出品を確認できます",
        "「DB取込」ボタンで、気に入った商品をワンクリックでデータベースに登録できます",
      ],
    },
    {
      icon: "📦",
      title: "商品・出品管理",
      path: "/products",
      steps: [
        "仕入れ先（THE CKB / Alibaba）を選択し、商品IDを入力して「取込」します",
        "取り込んだ商品は一覧テーブルに表示されます（画像・原価・売値・在庫）",
        "「BASE出品」ボタンで、その商品をBASEショップに出品できます",
        "出品状態は「出品中」「下書き」「非公開」「エラー」で色分け表示されます",
        "不要な商品は「削除」ボタンで削除できます",
      ],
    },
    {
      icon: "🔄",
      title: "在庫・価格同期",
      path: "/sync",
      steps: [
        "「手動同期」ボタンで仕入れ先の最新在庫・価格を取得します",
        "在庫が0の商品は自動的にBASE上で非公開になります（在庫切れ通知も発行）",
        "在庫が復活した商品は自動で再公開されます",
        "為替レートの変動に応じて売値が自動再計算されます",
        "同期は定期的にバックグラウンドでも実行されます（5分間隔）",
      ],
    },
    {
      icon: "🧾",
      title: "受注 / 損益",
      path: "/orders",
      steps: [
        "BASEで受注した注文が一覧表示されます",
        "各注文の売上・原価・手数料・利益が一目で分かります",
        "上部のサマリーで合計売上・利益・平均利益率を確認できます",
        "ステータス：受注 → 手配中 → 発送済み → 完了",
      ],
    },
    {
      icon: "📣",
      title: "SNS集客リンク（UTM）",
      path: "/marketing",
      steps: [
        "BASE商品のURLを入力します",
        "SNSプラットフォーム（TikTok / Instagram / X / YouTube）を選択します",
        "キャンペーン名・コンテンツ名を入力して「リンク生成」を押します",
        "生成されたUTMリンクをSNS投稿に貼り付けることで、流入元を計測できます",
        "「コピー」ボタンでクリップボードにコピーできます",
      ],
    },
  ];

  const alerts = [
    { icon: "😻", label: "お知らせ（青）", desc: "売れ筋検出など、良い知らせです" },
    { icon: "😾", label: "注意（黄）", desc: "為替変動など、確認が必要な情報です" },
    { icon: "🙀", label: "重要（赤）", desc: "在庫切れなど、すぐ対応が必要です" },
  ];

  const workflow = [
    { step: "1", title: "スクリーニング", desc: "売れそうな猫グッズを調査・評価", icon: "🔍" },
    { step: "2", title: "商品取込", desc: "A評価の商品をDBに登録", icon: "📥" },
    { step: "3", title: "BASE出品", desc: "ワンクリックでBASEに出品", icon: "🛒" },
    { step: "4", title: "SNSリンク作成", desc: "TikTok等の集客用UTMリンク発行", icon: "📣" },
    { step: "5", title: "自動同期", desc: "在庫・価格を自動で監視・更新", icon: "🔄" },
    { step: "6", title: "損益確認", desc: "売上・利益をリアルタイムで確認", icon: "💰" },
  ];

  return (
    <div style={{ maxWidth: 800 }}>
      <p style={{ marginBottom: 16 }}>
        <a href="/">← ダッシュボード</a>
      </p>
      <h1 style={{ fontSize: 22 }}>📖 操作マニュアル</h1>
      <p style={{ color: "var(--muted)", fontSize: 14, marginBottom: 24 }}>
        necorope（猫グッズ無在庫販売ハブ）の全機能の使い方ガイドです。
      </p>

      {/* Workflow */}
      <div style={{
        background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
        padding: 20, marginBottom: 24,
      }}>
        <h2 style={{ fontSize: 17, margin: "0 0 16px" }}>🐾 基本の流れ（ワークフロー）</h2>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(200px, 1fr))", gap: 12 }}>
          {workflow.map((w) => (
            <div key={w.step} style={{
              padding: 14, borderRadius: 12, border: "1.5px solid #e5e7eb",
              background: "linear-gradient(135deg, #fafafa, #fff)",
            }}>
              <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 6 }}>
                <span style={{
                  width: 28, height: 28, borderRadius: "50%", display: "grid", placeItems: "center",
                  background: "linear-gradient(135deg, #a78bfa, #f472b6)", color: "#fff",
                  fontSize: 13, fontWeight: 700,
                }}>{w.step}</span>
                <span style={{ fontWeight: 700, fontSize: 14 }}>{w.icon} {w.title}</span>
              </div>
              <p style={{ margin: 0, color: "var(--muted)", fontSize: 12, lineHeight: 1.5 }}>{w.desc}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Each Section */}
      {sections.map((s) => (
        <div key={s.title} style={{
          background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
          padding: 20, marginBottom: 16,
        }}>
          <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 12 }}>
            <span style={{ fontSize: 24 }}>{s.icon}</span>
            <h2 style={{ margin: 0, fontSize: 16 }}>{s.title}</h2>
            <a href={s.path} style={{
              marginLeft: "auto", fontSize: 12, fontWeight: 600,
              color: "var(--primary)", textDecoration: "none",
            }}>
              開く →
            </a>
          </div>
          <ol style={{ margin: 0, paddingLeft: 20 }}>
            {s.steps.map((step, i) => (
              <li key={i} style={{ fontSize: 14, lineHeight: 1.8, color: "#374151" }}>{step}</li>
            ))}
          </ol>
        </div>
      ))}

      {/* Alert Guide */}
      <div style={{
        background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
        padding: 20, marginBottom: 16,
      }}>
        <h2 style={{ fontSize: 16, margin: "0 0 12px" }}>🔔 アラートの見方</h2>
        <p style={{ fontSize: 13, color: "var(--muted)", marginBottom: 12 }}>
          ダッシュボード上部にアラートが表示されます。色と猫の表情で重要度が分かります。
        </p>
        {alerts.map((a) => (
          <div key={a.label} style={{
            display: "flex", alignItems: "center", gap: 10,
            padding: "8px 12px", marginBottom: 6, borderRadius: 8, background: "#f9fafb",
          }}>
            <span style={{ fontSize: 20 }}>{a.icon}</span>
            <span style={{ fontWeight: 600, fontSize: 13, minWidth: 100 }}>{a.label}</span>
            <span style={{ fontSize: 13, color: "var(--muted)" }}>{a.desc}</span>
          </div>
        ))}
      </div>

      {/* Auto Features */}
      <div style={{
        background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
        padding: 20, marginBottom: 16,
      }}>
        <h2 style={{ fontSize: 16, margin: "0 0 12px" }}>⚡ 自動機能について</h2>
        <ul style={{ margin: 0, paddingLeft: 20 }}>
          <li style={{ fontSize: 14, lineHeight: 1.8 }}>
            <strong>在庫・価格同期</strong>：5分ごとに仕入れ先の在庫と価格をチェックし、BASEの出品を自動更新します
          </li>
          <li style={{ fontSize: 14, lineHeight: 1.8 }}>
            <strong>在庫切れ自動非公開</strong>：仕入れ先で在庫が0になった商品は、自動でBASE上で非公開になります
          </li>
          <li style={{ fontSize: 14, lineHeight: 1.8 }}>
            <strong>為替レート更新</strong>：6時間ごとにCNY→JPYの為替レートを取得し、売値を自動再計算します
          </li>
          <li style={{ fontSize: 14, lineHeight: 1.8 }}>
            <strong>在庫切れアラート</strong>：在庫が切れた商品はダッシュボードに警告が表示されます
          </li>
          <li style={{ fontSize: 14, lineHeight: 1.8 }}>
            <strong>売れ筋検出</strong>：定期スクリーニングでA評価の商品が見つかると通知されます
          </li>
        </ul>
      </div>

      {/* Price Formula */}
      <div style={{
        background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
        padding: 20, marginBottom: 16,
      }}>
        <h2 style={{ fontSize: 16, margin: "0 0 12px" }}>💴 売値の計算ルール</h2>
        <div style={{ fontSize: 14, lineHeight: 1.8, color: "#374151" }}>
          <p style={{ margin: "0 0 8px" }}>売値は以下の式で自動計算されます：</p>
          <div style={{
            padding: 12, background: "#f3f4f6", borderRadius: 8,
            fontFamily: "monospace", fontSize: 13, marginBottom: 8,
          }}>
            売値 = (原価 x 為替レート + 送料) x マークアップ率 + 固定利益
          </div>
          <ul style={{ margin: 0, paddingLeft: 20 }}>
            <li><strong>為替レート</strong>：自動取得（例: 1 CNY = 21.5 JPY）</li>
            <li><strong>送料</strong>：800円（デフォルト）</li>
            <li><strong>マークアップ率</strong>：1.3倍（30%上乗せ）</li>
            <li><strong>固定利益</strong>：500円</li>
          </ul>
        </div>
      </div>

      {/* Glossary */}
      <div style={{
        background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
        padding: 20, marginBottom: 24,
      }}>
        <h2 style={{ fontSize: 16, margin: "0 0 12px" }}>📚 用語集</h2>
        <div style={{ display: "grid", gap: 8 }}>
          {[
            ["THE CKB", "中国の仕入れ先サイト。猫グッズの原価と在庫を取得します"],
            ["BASE", "日本のECプラットフォーム。ここに出品して販売します"],
            ["CNY", "中国人民元（仕入れ先の通貨）"],
            ["UTMリンク", "SNS投稿に使う計測用リンク。どの投稿から購入されたか分かります"],
            ["スクリーニング", "猫グッズキーワードで市場を調査し、利益率で評価する機能"],
            ["同期", "仕入れ先の最新情報をBASEの出品に反映する操作"],
            ["無在庫販売", "在庫を持たず、注文が入ってから仕入れる販売方式"],
          ].map(([term, desc]) => (
            <div key={term} style={{ display: "flex", gap: 10, padding: "6px 0", borderBottom: "1px solid #f3f4f6" }}>
              <span style={{
                fontWeight: 700, fontSize: 13, minWidth: 120,
                padding: "2px 8px", background: "#f3f4f6", borderRadius: 6,
                display: "inline-block", textAlign: "center",
              }}>{term}</span>
              <span style={{ fontSize: 13, color: "#374151" }}>{desc}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
