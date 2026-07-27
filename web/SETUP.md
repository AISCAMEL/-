# IWASAWA SURF BASE — セットアップ手順

会員制コミュニティ＋スキル掲示板＋波情報＋運営管理画面。動かすには Supabase の接続が必要です。

## 1. 依存インストール
```bash
cd web
npm install
```

## 2. Supabase プロジェクトを用意
1. https://supabase.com でプロジェクトを作成
2. `web/supabase/migrations/` の SQL を **番号順に**（または `schema.sql` を一括で）SQL Editor で実行：
   - `0001_init_members.sql` … 会員（種別×プラン）・RLS・登録時自動作成
   - `0002_community.sql` … 投稿/コメント/いいね
   - `0003_skills.sql` … スキル掲示板
   - `0004_admin.sql` … 通報・操作ログ
3. Authentication > Providers で「Email」を有効化
   - 開発中はメール確認をオフにすると登録がすぐ通って楽です

### 運営者（Staff/Admin）にする
管理画面 `/admin` は staff/admin のみアクセス可。最初の運営者は
Supabase の Table Editor で対象会員の `members.role` を `admin` に変更してください。
以降は `/admin/members` から他の会員の種別を変更できます。

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
