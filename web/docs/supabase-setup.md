# Supabase 接続ガイド（公開に向けて・上から順に）

所要時間：30分ほど。これが終わると、ログイン・会員登録・投稿・管理画面・お問い合わせが本番で動きます。
（デモ用のサンプル講師・投稿などは自動で消え、本物のデータに切り替わります）

> SQL は 2026-11-08 に、Supabase と同じ構成のテスト用データベースで
> 全ファイルがエラーなく通ること・権限まわりの攻撃を防げることを確認済みです。

---

## 1. Supabase のプロジェクトを作る（5分）

1. https://supabase.com にログイン →「New project」
2. 入力
   - **Name**：`iwasawa-surf-base`
   - **Database Password**：強いパスワード（パスワード管理アプリなどに保存。あとで使いません）
   - **Region**：**Northeast Asia (Tokyo)**（日本の利用者が速い）
3. 「Create new project」→ 1〜2分待つ

## 2. データベースを作る（3分）

1. 左メニュー **SQL Editor** →「New query」
2. リポジトリの **`web/supabase/schema.sql` の中身を全部コピーして貼り付け** →「Run」
   - 「Success. No rows returned」と出ればOK
   - `NOTICE: ... does not exist, skipping` が出ても問題ありません（正常です）
   - ※ ファイルを分けて実行したい場合は `web/supabase/migrations/` の 0001〜0016 を番号順に

## 3. ログインの設定（3分）

左メニュー **Authentication**

1. **Sign In / Providers → Email** が有効になっていることを確認
   - 「Confirm email」は **オン**（なりすまし登録を防ぐ）
2. **URL Configuration**
   - **Site URL**：`https://iwasawa-surf-base.vercel.app`（独自ドメインにしたらそれに変更）
   - **Redirect URLs** に次を追加
     - `https://iwasawa-surf-base.vercel.app/auth/callback`
     - `http://localhost:3200/auth/callback`（開発用）
3. （推奨）**Emails → SMTP Settings**：Supabase 標準のメール送信は1時間に数通までの制限があります。
   公開後に登録が増えたら、Resend などのメール送信サービスを設定してください。

## 4. 接続情報をコピー（1分）

左メニュー **Project Settings → API**（または「Data API」）

- **Project URL**（`https://xxxx.supabase.co`）
- **anon public** キー（`eyJ...` で始まる長い文字列）

この2つは「ブラウザに公開される前提」の値なので、チャットで送っても問題ありません。
（**service_role** キーは絶対に共有しないでください。このサイトでは使いません）

## 5. Vercel に登録して再デプロイ（Claude が代行できます）

Vercel → プロジェクト `iwasawa-surf-base` → **Settings → Environment Variables**

| 名前 | 値 | 環境 |
|---|---|---|
| `NEXT_PUBLIC_SUPABASE_URL` | 手順4の Project URL | Production・Preview |
| `NEXT_PUBLIC_SUPABASE_ANON_KEY` | 手順4の anon public キー | Production・Preview |

登録後に **再デプロイ** が必要です（この2つはビルド時に組み込まれるため）。
→ 2つの値を Claude に送ってもらえれば、登録・再デプロイ・動作確認まで代行します。

## 6. 管理者（あなた）のアカウントを作る（3分）

1. 本番サイトの `/signup` で、あなたのメールアドレスで会員登録 → 届いた確認メールのリンクを開く
2. Supabase の **SQL Editor** で次を実行（メールアドレスはご自身のものに）

```sql
update public.members set role = 'admin' where email = 'あなたのメールアドレス';
```

3. サイトに再ログイン → `/admin` が開ければ完了

## 7. 初期コンテンツを入れる（1分）

講座・レッスン・ブログが空のままにならないよう、サンプル内容を入れます。
**手順6のあとに**、SQL Editor で **`web/supabase/seed_initial_content.sql`** の中身を貼り付けて「Run」。

- 講座3つ・レッスン8つ・ブログ3本が入ります（ブログは管理者の名前で掲載）
- 何度実行しても重複しません。内容はあとで管理画面から編集できます
- 講師はご本人の会員登録が必要なため、ここでは作りません
  （講師の方に登録してもらい、管理画面で講師に設定）

## 8. 動作チェック

- [ ] `/signup` → 確認メール → ログインできる
- [ ] `/me` でプロフィール・アイコン画像を変更できる
- [ ] `/community/new` で投稿できる
- [ ] `/contact` から送信 → `/admin/inquiries` に届く
- [ ] `/admin/ads` で広告を登録 → 各ページの広告枠に表示される
- [ ] `/courses` に講座、`/articles` にブログが表示される

---

## 入っているセキュリティ対策（0015）

データベースに直接リクエストを送られても、次はできないようにしてあります。

- 会員が自分の種別（admin など）・プラン（premium）・状態を書き換える
- staff が自分や他人を admin にする（admin の付与は admin のみ）
- 講師が自分のランク・評価・おすすめ表示を書き換える
- 投稿のいいね数・閲覧数の偽装、運営が非表示にした投稿の再公開
- 会員が自分で有効な契約（subscription）を作る、注文を支払済みで作る
- スキル申込者が自分で「承認」「完了」にする
