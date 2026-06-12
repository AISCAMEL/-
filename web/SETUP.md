# IWASAWA SURF BASE — セットアップ手順

Step0（基盤）＋ Step1（認証）まで実装済み。動かすには Supabase の接続が必要です。

## 1. 依存インストール
```bash
cd web
npm install
```

## 2. Supabase プロジェクトを用意
1. https://supabase.com でプロジェクトを作成
2. `web/supabase/migrations/0001_init_members.sql` の内容を
   Supabase ダッシュボード > SQL Editor に貼って実行
   （`members` テーブル・enum・トリガー・RLS が作られます）
3. Authentication > Providers で「Email」を有効化
   - 開発中はメール確認をオフにすると登録がすぐ通って楽です

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
