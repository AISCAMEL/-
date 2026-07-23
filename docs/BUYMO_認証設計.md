# BUYMO 認証設計（業務システムのログイン）

業務系ページ（本部・加盟店）にログインを必須化し、書き込みAPIを保護します。会員（お客様）はメールでの簡易確認です。

## 1. 仕組み

```
[portal-login.html] --email/pw--> GAS doGet(action=login, JSONP)
        └ 成功: セッショントークンを localStorage(buymo_session) に保存（8h）
[hq*.html / report.html] 先頭で AUTH.guard(role)
        └ 未ログイン→portal-login へ / 権限不足→自分のホームへ
[書き込み: type:"case"/"note"] body に token を同梱 → GAS が verifyToken_ で検証
```

- パスワードは **ソルト付き SHA-256** で「認証ユーザー」シートに保存（平文保存なし）。
- ログインで **ランダムなセッショントークン**を発行し「セッション」シートに保存（8時間有効）。
- 書き込みAPIは **ユーザー登録後**は有効トークン必須（未登録なら後方互換で従来通り）。

## 2. ロールと権限

| ロール | ログイン | 入れる画面 |
|---|---|---|
| 本部 hq | メール＋PW | ダッシュボード/ボード/リード/加盟店/レポート（全部） |
| 加盟店 partner | メール＋PW | 案件ボード（自店フィルタ） |
| 会員 member | メール（簡易） | 会員マイページ |

`AUTH.guard('hq')`＝本部のみ、`AUTH.guard('staff')`＝本部＋加盟店。本部は全権限（superuser）。

## 3. セットアップ（GAS）

1. `gas/Auth.gs` を追加、`WebApp.gs` を最新化。
2. 管理者を作成（エディタで実行）：
   ```js
   createUser('you@company.jp', '強いパスワード', 'hq', '管理者');
   createUser('iwaki@company.jp', 'パスワード', 'partner', 'いわき店');
   ```
   （お試しは `seedAdmin()`→`admin@buymo.local/changeme`。**必ず changePassword で変更**）
3. フロントの `ENDPOINT` を設定：`assets/js/auth.js` と `assets/js/hq-common.js` の `ENDPOINT` にウェブアプリURL（`…/exec`）。
4. これで業務ページはログイン必須・書き込みはトークン必須になります。

> デモ（ENDPOINT空）では、メール＋任意PWでログインできます（ローカルのみ）。

## 4. セキュリティ上の限界（重要・正直な注意）

GASウェブアプリは応答に **CORSヘッダを返さない** ため、ブラウザJSから応答を読むログインは **JSONP(GET)** で行います。これにより：

- **ログイン時のパスワードがURL/サーバーログに残り得ます**（GETクエリのため）。
- 書き込みは `no-cors` POST で応答を読めませんが、サーバー側でトークン検証して拒否はできます（fire-and-forget）。
- 業務ページは静的配信のため、`guard` はあくまで **クライアント側の入口制御**。URL直打ちの一次表示を防ぐ目的で、機密データそのものはGAS側のトークン検証で守ります。

## 5. 本番ハードニング（センシティブ運用時の推奨）

- **アプリを“応答を読める”基盤に移す**：Cloudflare Pages Functions / Workers、Vercel、Netlify Functions、または小さな Node/PHP バックエンドを前段に置き、**HTTPS＋CORS＋POST**でログイン（パスワードをボディ送信、ログに残さない）。
- もしくは GAS を **「Google アカウントでログイン必須」**でデプロイし、`Session.getActiveUser().getEmail()` で本人確認（社内＝Google Workspace 向き）。
- さらに：**ログイン施行回数制限（レート制限）**、**セッションの短命化＋更新**、**監査ログ**、**全ページHTTPS強制**、**最小権限のシート共有**。
- パスワードは利用者に**十分な長さ・使い回し禁止**を徹底。

## 6. 関連ファイル
- フロント：`assets/js/auth.js`（ゲート/ログイン）、`portal-login.html`、各 `hq*.html`/`report.html` 冒頭の `AUTH.guard`
- GAS：`gas/Auth.gs`（ハッシュ/トークン/セッション）、`gas/WebApp.gs`（login/logout・書き込み保護）
