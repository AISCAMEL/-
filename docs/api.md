# API / Webhook 仕様（AIオペレーター24）

- Base URL: `https://api.ai-operator24.com`
- WebSocket: `wss://api.ai-operator24.com/ws/conversation`
- 管理画面API認証: `Authorization: Bearer {access_token}`（JWT。クレームに `tenant_id` / `role`）
- Twilio Webhook 認証: `X-Twilio-Signature` を検証

---

## Twilio / 通話系

| Method | Path | 説明 |
|--------|------|------|
| POST | `/api/twilio/incoming-call` | 着信。ConversationRelay 用 TwiML を返す |
| POST | `/api/twilio/call-status` | 通話ステータスコールバック |
| POST | `/api/twilio/connect-ended` | `<Connect>` 終了後。要約生成・通知をキック |
| WSS  | `/ws/conversation` | Conversation Relay の双方向メッセージ |

### `/api/twilio/incoming-call` が返す TwiML
```xml
<Response>
  <Connect action="https://api.ai-operator24.com/api/twilio/connect-ended">
    <ConversationRelay
      url="wss://api.ai-operator24.com/ws/conversation"
      welcomeGreeting="お電話ありがとうございます。AI受付です。ご用件をお話しください。"
      language="ja-JP"
      interruptible="any" />
  </Connect>
</Response>
```

### `/ws/conversation` メッセージ（Twilio Conversation Relay）
- 受信 `setup`: `callSid` / `sessionId` / `from` / `to` を取得 → tenant 解決・call 作成。
- 受信 `prompt`: ユーザ発話（`voicePrompt`）。Orchestrator に渡し応答生成。
- 送信 `text`: `{ "type":"text", "token":"...", "last":true }` で AI 応答を発話。
- 送信 `end`: 通話終了。

---

## 管理画面 API

| Method | Path | 説明 |
|--------|------|------|
| GET   | `/api/me` | ログインユーザ・テナント情報 |
| GET   | `/api/dashboard` | 当日着信数・当月通話数・対応内訳・最近の通話 |
| GET   | `/api/calls` | 通話一覧（フィルタ: status/category/date/q） |
| GET   | `/api/calls/{call_id}` | 通話詳細（要約・文字起こし全文・メモ） |
| PATCH | `/api/calls/{call_id}/status` | ステータス更新 |
| POST  | `/api/calls/{call_id}/notes` | 社内メモ追加 |
| POST  | `/api/calls/{call_id}/summarize` | 要約を再生成 |
| POST  | `/api/calls/{call_id}/notify` | 通知を再送 |

## FAQ API
| Method | Path |
|--------|------|
| GET    | `/api/faqs` |
| POST   | `/api/faqs` |
| PUT    | `/api/faqs/{faq_id}` |
| DELETE | `/api/faqs/{faq_id}` |

## 設定 API
| Method | Path |
|--------|------|
| GET / PUT | `/api/settings/ai` |
| GET / PUT | `/api/settings/notification` |
| GET   | `/api/phone-numbers` |
| PATCH | `/api/phone-numbers/{phone_number_id}` |

## ユーザー管理 API（owner / admin / super_admin のみ）
| Method | Path | 説明 |
|--------|------|------|
| GET    | `/api/users` | テナント内ユーザー一覧（閲覧は全ロール可） |
| POST   | `/api/users` | メンバー追加（name/email/role） |
| PATCH  | `/api/users/{user_id}` | 権限変更・有効/無効・氏名 |
| DELETE | `/api/users/{user_id}` | 削除 |

- ロール：`owner`（全権・最低1人必須）/ `admin`（ユーザー管理・設定）/ `staff`（閲覧・対応）。
- 最後の `owner` の降格・無効化・削除は 400 で拒否。

## 請求・利用 API
| Method | Path | 説明 |
|--------|------|------|
| GET | `/api/usage` | 当月の分数・原価・売上・粗利 |
| GET | `/api/usage/invoice` | 請求書データ（税込・印刷/PDF用） |
| GET | `/api/usage/export` | 通話明細CSV（UTF-8 BOM） |
| GET | `/api/admin/usage` | 全テナント横断の利用・原価（super_admin） |

## 問い合わせ導線 / リード管理 API
| Method | Path | 認証 | 説明 |
|--------|------|------|------|
| POST | `/api/public/leads` | なし（公開） | LPフォーム送信。リード作成＋ステップメール予約＋自社通知 |
| GET  | `/api/admin/leads` | super_admin | リード一覧（status/category/q フィルタ） |
| GET  | `/api/admin/leads/{id}` | super_admin | 詳細（メモ・商談・ステップメール） |
| PATCH| `/api/admin/leads/{id}` | super_admin | ステータス・種別・担当変更 |
| POST | `/api/admin/leads/{id}/notes` | super_admin | 対応メモ追加 |
| POST | `/api/admin/leads/{id}/meetings` | super_admin | 商談・ミーティング作成 |
| PATCH| `/api/admin/meetings/{id}` | super_admin | 商談ステータス・日時更新 |
| POST | `/api/admin/leads/{id}/email` | super_admin | 個別メール送信 |
| POST | `/api/admin/leads-worker/run` | super_admin | ステップメール送信を手動実行 |

- ステップメール既定シナリオ：即時お礼 → 翌日資料 → 3日後事例 → 7日後デモ案内（`backend/src/leads/sequence.ts`）。
- 受注/失注/クローズに変更すると未送信のステップメールは自動停止。

## Super Admin API
| Method | Path |
|--------|------|
| GET  | `/api/admin/tenants` |
| POST | `/api/admin/tenants` |
| GET  | `/api/admin/calls` |
| GET  | `/api/admin/usage` |

---

## 通知（通話終了後メール）

- 件名: `【AIオペレーター24】新しい電話受付がありました`
- 本文項目: 顧客名 / 会社名 / 電話番号 / 要件 / 内容 / 希望日時 / 対応結果 / 次の対応 / 通話要約
