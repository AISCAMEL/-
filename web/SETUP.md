# IWASAWA SURF BASE — セットアップ手順

Step0（基盤）＋ Step1（認証）まで実装済み。動かすには Supabase の接続が必要です。

## 1. 依存インストール
```bash
cd web
npm install
```

## 2. Supabase プロジェクトを用意
1. https://supabase.com でプロジェクトを作成
2. `web/supabase/migrations/` の SQL を **番号順に** SQL Editor で実行：
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
- `NEXT_PUBLIC_SITE_URL` … 開発は `http://localhost:3000`

## 4. 起動
```bash
npm run dev
```
- `/` トップ（Hero・4サービス）
- `/signup` 新規登録（既定で Beginner / Free になる）
- `/login` ログイン
- `/password/reset` パスワード再設定
- `/me` マイページ（未ログインは `/login` に飛ぶ＝権限ゲート確認）

## 実装メモ
- 権限は UI + API + RLS の三重化：
  - 入口：`src/proxy.ts`（Next.js 16 の middleware 後継）
  - API層：各ページで `auth.getUser()` を再確認
  - DB層：`members` の RLS ポリシー
- 会員は **種別(role) × プラン(plan)** の2軸（仕様書 v2.0 セクション3）
- スタッフ権限の付与は、当面 Supabase 管理画面から
  `members.role` を `staff`/`admin` に変更して行う（管理UIは Step8 以降）
