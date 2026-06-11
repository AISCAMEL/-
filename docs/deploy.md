# 本番デプロイ手順（AIオペレーター24）

構成：**バックエンド = Render**（常時起動・WebSocket対応）／**フロント = Vercel**（Next.js）／**DB = Supabase**。

```
[利用者の電話] → Twilio → Render(backend: API + /ws/conversation) → Supabase(DB) / OpenAI / Resend
[管理画面ブラウザ] → Vercel(frontend) → Render(backend API)
```

> フロントを Vercel にする場合、Conversation Relay の WebSocket を担うのは **Render(backend)**。
> Vercelのサーバーレスは常時接続WSに不向きなため、**backendは必ずRender等の常時起動環境**に置く。

---

## 1. DB（Supabase）

1. Supabase プロジェクトを作成。
2. SQL Editor で順に実行：
   - `db/schema.sql`
   - `db/seed.sql`（任意・デモデータ）
3. `Project Settings → Database` の接続文字列を `DATABASE_URL` に使う。
4. `Project Settings → API → JWT Secret` を `SUPABASE_JWT_SECRET` に使う。
5. `phone_numbers` に本番Twilio番号(E.164)をテナント紐付きで登録。

---

## 2. バックエンド（Render）

### Blueprint で一括（推奨）
1. リポジトリを Render に接続 → **New → Blueprint** → ルートの `render.yaml` を使用。
2. デプロイ後、払い出されたホスト `https://ai-operator24-backend-xxxx.onrender.com` を控える。
3. ダッシュボードで `sync:false` の環境変数を設定：
   - `PUBLIC_API_BASE_URL = https://<上記ホスト>`
   - `PUBLIC_WS_BASE_URL  = wss://<上記ホスト>`
   - `CORS_ORIGIN = https://<Vercelのフロントドメイン>`
   - `TWILIO_ACCOUNT_SID` / `TWILIO_AUTH_TOKEN`
   - `OPENAI_API_KEY`
   - `DATABASE_URL`（Supabase）
   - `RESEND_API_KEY` / `MAIL_FROM`
   - `SUPABASE_JWT_SECRET`（`AUTH_DEV_MODE` は `false`）
4. 再デプロイ後、`https://<ホスト>/health` が `{"ok":true,...}` を返すか確認。
5. `npm run doctor`（ローカルで同じ環境変数を使い）で設定検証も可能。

### 手動設定の場合
- Root Directory: `backend`
- Build: `npm install && npm run build`
- Start: `npm start`
- Health Check Path: `/health`

---

## 3. フロントエンド（Vercel）

1. Vercel で **New Project** → リポジトリを選択。
2. **Root Directory = `frontend`** を指定（重要）。
3. 環境変数：
   - `NEXT_PUBLIC_API_BASE_URL = https://<Renderのbackendホスト>`
4. デプロイ。`https://<your-app>.vercel.app` がLP、`/login` から管理画面。
5. backend 側の `CORS_ORIGIN` をこの Vercel ドメインに設定し直す（手順2-3）。

---

## 4. Twilio

`docs/twilio-setup.md` に従い、電話番号の Voice Webhook を
`https://<Renderホスト>/api/twilio/incoming-call`（POST）に設定。

---

## 5. デプロイ後チェックリスト

- [ ] `https://<backend>/health` が 200
- [ ] `https://<frontend>/` でLPが表示
- [ ] `/login` → ダッシュボードが表示され、`/api/dashboard` が取得できる（CORS OK）
- [ ] 実際に電話 → AI応答 → 通話後に要約・メール/Slack通知・通話履歴反映
- [ ] `/usage` で当月の分数・原価・粗利、請求書・CSVが出る
- [ ] `AUTH_DEV_MODE=false` かつ `SUPABASE_JWT_SECRET` 設定済（本番認証）
- [ ] `TWILIO_VALIDATE_SIGNATURE=true`
- [ ] `CORS_ORIGIN` がフロントドメインに限定されている

---

## 6. 代替ホスティング

| 用途 | 候補 |
|------|------|
| backend（常時起動・WS） | Render / Railway / Fly.io / AWS(ECS) |
| frontend | Vercel / Netlify / Cloudflare Pages |
| DB | Supabase / Neon / AWS RDS |

backendをDocker化する場合は `node:20-slim` で `npm ci && npm run build`、`CMD ["npm","start"]`。
