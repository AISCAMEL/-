# Twilio 実機通話テスト 手順書

AIオペレーター24を実際のTwilio番号にかけて動作確認するための手順とチェックリスト。
Conversation Relay は **公開された HTTPS / WSS** が必要なため、ローカルでは ngrok 等のトンネル、
本番では Render / Railway / Fly.io 等のデプロイを使う。

---

## 0. 前提

| 必要なもの | 用途 |
|-----------|------|
| Twilioアカウント + 音声対応の電話番号 | 着信 |
| OpenAI APIキー | AI応答・要約 |
| 公開URL（ngrok or デプロイ先） | TwilioからWebhook/WSへ到達 |
| (任意) PostgreSQL/Supabase | 通話ログ永続化 |
| (任意) Resend APIキー | メール通知 |

> DB・OpenAI・通知が未設定でも**デモモード**で起動はするが、実機テストでは最低限 **OpenAI** を設定する。

---

## 1. バックエンドを公開する

### ローカル + ngrok の場合
```bash
cd backend
cp .env.example .env        # 値を設定（下記2章）
npm install
npm run dev                 # localhost:8080

# 別ターミナル
ngrok http 8080             # → https://xxxx.ngrok-free.app を取得
```
取得した公開URLを `.env` の `PUBLIC_API_BASE_URL` / `PUBLIC_WS_BASE_URL` に設定し直して再起動する。
```
PUBLIC_API_BASE_URL=https://xxxx.ngrok-free.app
PUBLIC_WS_BASE_URL=wss://xxxx.ngrok-free.app
```

### デプロイの場合（Render等）
- `backend/` を Web Service としてデプロイ（`npm run build` → `npm start`）。
- 環境変数を設定し、払い出された `https://...onrender.com` を `PUBLIC_API_BASE_URL`、`wss://...onrender.com` を `PUBLIC_WS_BASE_URL` に。

---

## 2. 環境変数（backend/.env）

```
PUBLIC_API_BASE_URL=https://<公開ホスト>
PUBLIC_WS_BASE_URL=wss://<公開ホスト>

TWILIO_ACCOUNT_SID=ACxxxx
TWILIO_AUTH_TOKEN=xxxx
TWILIO_VALIDATE_SIGNATURE=true     # 実機テストは true 推奨

OPENAI_API_KEY=sk-xxxx
OPENAI_MODEL=gpt-4o-mini

# 任意
DATABASE_URL=postgres://...        # 設定時は schema.sql/seed.sql を投入
RESEND_API_KEY=re_xxxx
MAIL_FROM=AIオペレーター24 <noreply@your-domain>
```

設定後、診断を実行：
```bash
npm run doctor
```
✅ がそろう（または許容できる ⚠️ のみ）ことを確認する。

---

## 3. DBを使う場合（任意）

```bash
psql "$DATABASE_URL" -f db/schema.sql
psql "$DATABASE_URL" -f db/seed.sql
```
`phone_numbers` テーブルに、**実際にかけるTwilio番号(E.164)** をテナント紐付きで登録する。
seed のデモ番号 `+815099998888` を自分の番号に変更するか、行を追加する：
```sql
update phone_numbers set phone_number = '+81XXXXXXXXXX'
 where phone_number = '+815099998888';
```
> DBなしデモモードでは、かかってきた番号に関係なくデモテナントとして応答する。

---

## 4. Twilio番号の設定

Twilio Console → Phone Numbers → 対象番号 → **Voice Configuration**：

| 項目 | 値 |
|------|-----|
| A CALL COMES IN | **Webhook** |
| URL | `https://<公開ホスト>/api/twilio/incoming-call` |
| HTTP | **POST** |
| (任意) CALL STATUS CHANGES | `https://<公開ホスト>/api/twilio/call-status` (POST) |

保存。`incoming-call` が下記 TwiML を返し、Conversation Relay が `/ws/conversation` に接続する。
```xml
<Response>
  <Connect action="https://<公開ホスト>/api/twilio/connect-ended">
    <ConversationRelay url="wss://<公開ホスト>/ws/conversation"
      welcomeGreeting="..." language="ja-JP" interruptible="any" />
  </Connect>
</Response>
```

> Conversation Relay はアカウントで有効化が必要な場合がある。Console で利用可否を確認すること。

---

## 5. 実機テスト：シナリオ

対象番号に電話をかけ、以下を順に試す。

1. **予約**：「予約したいです」→ 日時 → 名前 → 確認（「予約希望として受け付けます」と言うか）
2. **問い合わせ**：「営業時間を教えて」→ FAQに基づく回答が返るか
3. **FAQ外**：登録のない質問 → 推測せず折り返し提案に切り替わるか
4. **折り返し**：「折り返してほしい」→ 名前・会社・要件・番号確認
5. **転送**：「担当者につないで」→ 転送応答（転送先未設定なら折り返しに切替）
6. 電話を切る → **要約・通知・通話ログ**が生成されるか

---

## 6. 疎通チェックリスト

- [ ] `npm run doctor` が公開URL/Twilio/OpenAI を ✅ で表示
- [ ] `curl https://<公開ホスト>/health` が `{"ok":true,...}` を返す
- [ ] `curl -X POST https://<公開ホスト>/api/twilio/incoming-call -d 'To=+81...&From=+81...&CallSid=CAtest'` が `<ConversationRelay .../>` を含むTwiMLを返す（URLが公開ホストになっているか）
- [ ] 着信するとAIが挨拶を話す（welcomeGreeting）
- [ ] 発話に対しAIが日本語で応答する（無音/英語になっていないか）
- [ ] 予約・問い合わせ・折り返し・転送の各シナリオが意図通り分類される
- [ ] 通話終了後、サーバログ（または通知メール dry-run）に**要約**が出力される
- [ ] (DB接続時) `calls` / `transcripts` に行が入る
- [ ] (DB接続時) 管理画面の通話履歴に表示される

---

## 7. トラブルシューティング

| 症状 | 確認点 |
|------|--------|
| 着信しても無音/即切れ | TwiMLのURLが公開ホストか／Conversation Relay有効化／WSが `wss://` か |
| 403 invalid signature | `TWILIO_VALIDATE_SIGNATURE=true` 時、`PUBLIC_API_BASE_URL` がTwilioに設定したURLと**完全一致**しているか（ngrok URL更新漏れに注意）。検証を一時的に `false` で切り分け |
| AIが定型文しか返さない | `OPENAI_API_KEY` 未設定（フォールバック応答）。doctorで確認 |
| 応答が英語 | language=ja-JP は設定済。プロンプトは日本語固定だが、モデル/音声設定を確認 |
| 要約・通知が出ない | `connect-ended` Webhookが届いているか（Twilioの `action` URL）。サーバログ確認 |
| DBに保存されない | `DATABASE_URL` 設定＋schema投入＋`phone_numbers` に着信番号が登録済みか |
| 管理画面が401/空 | フロントの `NEXT_PUBLIC_API_BASE_URL` がbackendを指すか、CORS(`CORS_ORIGIN`)許可 |

---

## 8. 本番化チェック（テスト後）

- [ ] `TWILIO_VALIDATE_SIGNATURE=true`
- [ ] `AUTH_DEV_MODE=false` + `SUPABASE_JWT_SECRET` 設定（管理画面の署名検証）
- [ ] `CORS_ORIGIN` を管理画面ドメインに限定
- [ ] DB接続・RLS有効を確認
- [ ] 通知先メール（Resend）疎通
