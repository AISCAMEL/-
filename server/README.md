# バイモダイレクト 加盟店ポータル サーバー

一般公開サイト（`site/`）を配信しつつ、**加盟店ポータル `/partner/` を本物のサーバー認証で保護**する、依存ライブラリ不要の Node サーバーです。社外秘コンテンツを「URLを知られても見られない」状態にします。

## できること
- `site/` を静的配信（一般サイトは誰でも閲覧可）
- `/partner/*.html` は**ログイン必須**（未ログインは自動でログイン画面へ）
- パスワードは **scrypt でハッシュ化**して保存（平文は保存しない）
- セッションは **HMAC 署名付き Cookie（HttpOnly）**
- ログイン成功・失敗・拒否を **`access.log`** に記録（漏洩抑止・監査）
- 社外秘ページは `X-Robots-Tag: noindex` で検索避け

## 起動

```bash
node server/server.js
# → http://localhost:8080
```

初回起動時、`server/partners.json` が無ければ**デモ用アカウントを自動生成**します。

- 加盟店コード：`BMD-001`
- パスワード：`bmd-demo-2026`

一般サイト：`http://localhost:8080/index.html`
加盟店ログイン：`http://localhost:8080/partner/login.html`

## 本番運用（重要）

1. **秘密鍵を必ず設定**（セッション署名）
   ```bash
   BMD_SECRET="$(openssl rand -hex 32)" PORT=8080 node server/server.js
   ```
2. **HTTPS 必須**：リバースプロキシ（Nginx / Caddy 等）を前段に置き、`X-Forwarded-Proto: https` を渡す（Cookie が Secure になります）。
3. **プロセス常駐**：`pm2` / `systemd` 等で常駐化。
4. **アカウント発行**：
   ```bash
   node server/add-partner.js BMD-002 郡山店 <パスワード>
   ```
   `partners.json` にハッシュ化して追記されます。停止するには対象アカウントの `"active": false` に変更。

## 環境変数
| 変数 | 既定 | 説明 |
|---|---|---|
| `PORT` | 8080 | 待受ポート |
| `BMD_SECRET` | （未設定・要変更） | セッション署名の秘密鍵。**本番必須** |
| `SESSION_HOURS` | 12 | セッション有効時間 |

## ファイル
- `server.js` … 本体（配信＋認証＋保護）
- `add-partner.js` … 加盟店アカウント追加/更新（ハッシュ化）
- `partners.json` … アカウント（scryptハッシュ）※gitignore・自動生成
- `access.log` … ログイン監査ログ ※gitignore

## 本部（管理者）コンソール
本部用の管理画面が `/admin/` にあります（**admin 権限が必要**、サーバー側で保護）。
- **加盟店アカウントの発行・停止・パスワード変更**（`add-partner.js` と同じことをブラウザから）
- **保存レコードの横断検索**（キーワード／種類／加盟店で絞り込み、「開く」で内容を再表示）

デモ管理者：`HQ-ADMIN` / `admin-demo-2026`（初回起動時に自動生成）
入口：`http://localhost:8080/admin/login.html`

管理API（admin セッション必須）：`GET/POST /api/admin/partners`、`GET /api/admin/records?q=&doc=&code=`

## Docker で動かす

```bash
# ビルド＆起動（秘密鍵は必ず指定）
BMD_SECRET="$(openssl rand -hex 32)" docker compose up -d --build
# → http://localhost:8080
```

`docker-compose.yml` はホストの `./server` を bind マウントするので、
`partners.json`（アカウント）・`access.log`・`data/records.json` がホスト側に永続化されます。

イメージ単体（Render / Fly.io 等）の場合：
- 環境変数 `BMD_SECRET` を設定
- 永続ディスクを `/app/server`（または最低限 `/app/server/data` と `partners.json`）にマウント
- アカウントは初回起動の自動生成、または `docker exec <container> node server/add-partner.js ...` で追加

## 静的ホスティングのままにしたい場合（サーバーを使わない選択肢）
`site/` をそのまま Netlify / Cloudflare Pages 等に置く場合、`/partner/` の保護はホスト側機能で行います。
- **Cloudflare Access**（推奨）：`/partner/*` にアクセスポリシーを設定し、許可メール/ID のみ通す
- **Netlify**：Role-based access control（有料）、または Basic 認証アドオン
- **Basic 認証**：Nginx/Apache の `.htpasswd` で `/partner/` を保護

いずれの場合も、クライアント側の localStorage ガードは**気休め**であり、必ずホスト/サーバー側で保護してください。
