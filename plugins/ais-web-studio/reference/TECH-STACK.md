# 技術スタックと規約

## 標準構成

| 領域 | 採用 | 備考 |
|---|---|---|
| フレームワーク | **Next.js（App Router）** | SSR/SSG/ISR・画像最適化・メタ管理 |
| 言語 | **TypeScript** | strict 有効 |
| スタイル | **Tailwind CSS** | デザイントークンは `tailwind.config` に集約 |
| UI | **shadcn/ui** | 必要コンポーネントのみ導入。過剰導入禁止 |
| アニメーション | **Motion**（旧 Framer Motion） | 目的のある動きのみ |
| アイコン | **Lucide Icons** | 統一セット |
| ホスティング | **Vercel** | プレビュー/本番の自動デプロイ |

必要に応じ最適なライブラリを選ぶ（例: フォーム=React Hook Form+Zod、
メタ=Next Metadata API、解析=Vercel Analytics）。重い依存は持ち込まない。

## スキャフォールド手順（新規サイト）

```bash
# 1) プロジェクト作成
npx create-next-app@latest <name> --ts --tailwind --app --eslint --src-dir --import-alias "@/*"
cd <name>

# 2) shadcn/ui 初期化（必要コンポーネントのみ追加）
npx shadcn@latest init
npx shadcn@latest add button card input ...

# 3) アニメーション・アイコン
npm i motion lucide-react

# 4) 開発サーバ
npm run dev   # http://localhost:3000
```

> 注: この実行環境には Playwright 同梱の Chromium があります。
> スクショ検証は `require('playwright')` の `chromium.launch()` を使用（再インストール不要）。

## ディレクトリ規約

```
src/
├── app/                 ルーティング（App Router）
│   ├── layout.tsx       共通レイアウト・メタ
│   ├── page.tsx         トップ
│   └── (routes)/…       下層
├── components/
│   ├── ui/              shadcn 由来
│   ├── sections/        ページのセクション単位
│   └── common/          共通部品（Header/Footer 等）
├── lib/                 ユーティリティ
├── styles/              globals.css（トークン）
└── content/             原稿・データ（JSON/MDX）
```

## コーディング規約

- コンポーネントは**セクション単位**で分割し、1ファイル1責務。
- 色・間隔・角丸は**直書きせず**トークン（Tailwind theme / CSS変数）経由。
- 画像は必ず `next/image`、外部フォントは `next/font`。
- `any` 禁止・未使用変数禁止・ESLint/型エラー0 で納品。
- アクセシビリティ属性（alt, aria, label, focus）を実装時に付与。

## デプロイ（Vercel）

- `main`/本番ブランチ → 本番、PR → プレビューURL自動生成。
- 独自ドメインは Vercel のドメイン設定で割当。
- 公開後に本番URLで 3デバイス実機確認・Lighthouse を再取得。
