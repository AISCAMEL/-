# IWASAWA SURF BASE — セットアップ手順

会員制コミュニティ＋スキル掲示板＋波情報＋運営管理画面。動かすには Supabase の接続が必要です。

## 1. 依存インストール
```bash
cd web
npm install
```

## 2. Supabase プロジェクトを用意
本番の接続手順は **`docs/supabase-setup.md`**（上から順に進めるだけ）を参照してください。
- データベース：`supabase/schema.sql` を SQL Editor で一括実行（または `supabase/migrations/` の 0001〜0016 を番号順に）
- 最初の管理者：会員登録後に `update public.members set role = 'admin' where email = '...';`
- 初期コンテンツ：`supabase/seed_initial_content.sql`（講座・レッスン・ブログ）

## 3. 環境変数
```bash
cp .env.example .env.local
```
`.env.local` に Supabase の値を入れる：
- `NEXT_PUBLIC_SUPABASE_URL` … Project Settings > API の URL
- `NEXT_PUBLIC_SUPABASE_ANON_KEY` … 同じく anon public キー
- `NEXT_PUBLIC_SITE_URL` … 開発は `http://localhost:3200`

## 4. 起動
```bash
npm run dev
```
→ ブラウザで **http://localhost:3200**（3000は別アプリ用に3200で起動）
- `/` トップ / `/signup` 登録 / `/login` ログイン
- `/community` コミュニティ / `/skills` スキル掲示板 / `/waves` 波情報
- `/me` マイページ / `/admin` 運営管理（staff/admin）

## PWA（スマホアプリ化）
本番ビルドでは PWA として動作し、スマホの「ホーム画面に追加」でアプリのように使えます。
- マニフェスト：`src/app/manifest.ts`／アイコン：`public/icon-*.png`／SW：`public/sw.js`
- `npm run build && npm run start` → スマホ/Chromeで開く → 「ホーム画面に追加」
- ※ サービスワーカー登録は本番のみ。`npm run dev` では無効です。

## 実装メモ
- 権限は UI + API + RLS の三重化：
  - 入口：`src/proxy.ts`（Next.js 16 の middleware 後継）
  - API層：各ページで `getCurrentMember()` / `requireStaff()` を再確認
  - DB層：各テーブルの RLS ポリシー（`is_staff()` / `current_member_role()` で再帰回避）
- 会員は **種別(role) × プラン(plan)** の2軸（仕様書 v2.0 セクション3）
- 波情報は Open-Meteo（無料・キー不要）。`api.open-meteo.com` /
  `marine-api.open-meteo.com` への通信許可が必要です。
- 収益機能（決済・広告・有料会員）は列/フラグの“仕込み”のみ。処理は将来実装。
