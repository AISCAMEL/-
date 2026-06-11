# AIオペレーター24

> 電話に出られない時間を、売上機会に変えるAI電話受付SaaS。

AIが企業・店舗に代わって電話に自動応答し、**予約受付・問い合わせ対応・折り返し受付・担当者転送・通話文字起こし・通話要約・通知・履歴管理**までを行うAI電話受付サービスです。

## アーキテクチャ

```
顧客が電話
  ↓
Twilio Phone Number
  ↓
Twilio Voice / Conversation Relay   (STT / TTS / 低遅延会話)
  ↓
自社 WebSocket Gateway   (/ws/conversation)
  ↓
AI Orchestrator   (state machine + LLM)
  ↓
LLM / FAQ / DB / 通知 / 管理画面
```

## ディレクトリ構成

```
.
├── README.md
├── docs/
│   ├── AIオペレーター24_開発仕様書.md   # マスター仕様書（本プロジェクトの正）
│   ├── ai-conversation.md               # AI会話仕様・プロンプト・state machine
│   ├── api.md                           # API / Webhook 仕様
│   └── 開発仕様書.md                    # （別案件 CarLoan_System・参考）
├── db/
│   ├── schema.sql                       # PostgreSQL / Supabase スキーマ（ENUM・テーブル・index・RLS）
│   └── seed.sql                         # デモ用シードデータ
└── backend/                             # Node.js + TypeScript バックボーン
    ├── package.json
    ├── tsconfig.json
    ├── .env.example
    └── src/
        ├── server.ts                    # Fastify エントリ
        ├── config.ts
        ├── twilio/                      # 着信Webhook・署名検証・TwiML
        ├── ws/                          # Conversation Relay WebSocket ハンドラ
        ├── ai/                          # Orchestrator・プロンプト・要約
        ├── notify/                      # メール / Slack 通知
        └── db/                          # DBアクセス層
```

## MVPスコープ

MVP完成条件（`docs/AIオペレーター24_開発仕様書.md` 第20章）を満たすことを目標とします。
後回し機能（Stripe自動課金・Twilio Subaccount自動作成・CRM/LINE連携・ホワイトラベル等）には踏み込みません。

## セットアップ（backend）

```bash
cd backend
cp .env.example .env   # 各種キーを設定
npm install
npm run dev            # http://localhost:8080
```

## 設計原則

- **MVP優先** — 4週間でデモ可能な状態を目指す。
- **マルチテナント前提** — MVPでも全テーブルに `tenant_id` を通し、RLSで分離。
- **AI誤回答防止** — FAQ外は推測しない／予約は「予約希望として受付」表現／禁止事項を厳守。
- **将来のSaaS化を見据えた設計** — Twilio Subaccount・課金・番号自動購入を後付けできる構造。
