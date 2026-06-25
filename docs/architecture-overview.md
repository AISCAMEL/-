# 全体構造：フロー & マインドマップ（AIオペレーター24）

このドキュメントは、システム全体の構造・データの流れを図で示します。
（GitHub上では下記のMermaid図がそのまま図として表示されます）

---

## 1. システム全体図（誰が・何を・どこで）

```mermaid
flowchart LR
  caller([お客様<br/>電話をかける/受ける]) -->|電話| twilio[Twilio<br/>電話基盤・音声認識/合成]
  twilio <-->|WebSocket<br/>会話| be

  visitor([見込み客<br/>Webサイト訪問]) -->|LP/問い合わせ| fe
  tenantUser([契約店舗・企業<br/>管理画面]) -->|ログイン| fe
  operator([運営者<br/>あなた]) -->|ログイン| fe

  subgraph cloud[クラウド（Render / Vercel）]
    fe[フロント Next.js<br/>LP・管理画面]
    be[バックエンド Node/Fastify<br/>API・AI・架電]
  end

  fe -->|REST API| be
  be -->|頭脳: 会話/要約| llm[OpenAI / OpenRouter]
  be -->|保存| db[(PostgreSQL<br/>Supabase)]
  be -->|通知| mail[メール Resend / Slack]
  be -->|決済| sq[Square]
```

- **電話の核**：Twilio が音声を扱い、バックエンドの AI が「何を話すか」を決める。
- **頭脳だけ**が OpenAI/OpenRouter。音声認識・読み上げは Twilio 側。
- DB・通知・決済・LLM は **キーを入れた分だけ本物**になる（未接続はデモ動作）。

---

## 2. 着信フロー（お客様 → AIが応答）

```mermaid
sequenceDiagram
  participant C as お客様
  participant T as Twilio
  participant B as バックエンド
  participant AI as AI(LLM)
  participant DB as DB/通知

  C->>T: 電話をかける
  T->>B: 着信Webhook /api/twilio/incoming-call
  B->>B: 発信者ルール確認（ブロック/専用挨拶）
  B-->>T: TwiML（ConversationRelayで会話開始）
  T->>B: WebSocket接続（/ws/conversation）
  loop 会話の各ターン
    C->>T: 話す（音声）
    T->>B: 文字起こし(prompt)
    B->>AI: 要件を判定して返答を生成
    AI-->>B: 返答(JSON: reply/intent/転送判定)
    B-->>T: 返答テキスト→読み上げ
  end
  C->>T: 電話を切る
  T->>B: connect-ended
  B->>AI: 通話を要約
  B->>DB: 通話履歴を保存＋メール/Slack通知
```

判定例：予約／問い合わせ（FAQ回答）／折り返し受付／担当者へ転送／クレームは人へ。

---

## 3. AI営業・架電フロー（こちらから電話）

```mermaid
flowchart TD
  start[キャンペーン作成<br/>目的・最初の一言・ゴール] --> targets[対象リスト追加<br/>名前/会社/電話番号]
  targets --> run{発信ボタン}
  run -->|Twilio接続済| place[Twilioで順次発信]
  run -->|未接続| sim[結果をシミュレート（デモ）]
  place --> relay[ConversationRelayで会話<br/>発信側プロンプト]
  relay --> talk[AIが用件を伝える<br/>商品説明/打合せ打診]
  talk --> result{相手の反応}
  result -->|興味あり| meet[打合せ打診/資料案内]
  result -->|担当者と話したい| transfer[担当者へ転送]
  result -->|不要| end1[丁寧に終了]
  meet --> rec[結果を記録]
  transfer --> rec
  end1 --> rec
  sim --> rec
```

---

## 4. マインドマップ（機能の全体像）

```mermaid
mindmap
  root((AIオペレーター24))
    表側 LP
      ヒーロー/機能/比較表/料金
      AIチャットボット（動画アバター）
      問い合わせ/資料請求フォーム
      法務（特商法/プライバシー/規約）
    顧客の管理画面
      ダッシュボード
        KPI/対応率/前週比
        グラフ（要件/時間帯/タグ）
      通話
        履歴（要対応/期間/タグ/検索/CSV）
        詳細（要約/文字起こし/タグ/メモ/印刷PDF）
        発信者プロフィール（リピーター）
      AI応対テスト
      AI営業・架電
        キャンペーン/対象/発信
      利用状況・原価 / お支払い(Square)
      FAQ管理（並べ替え）
      AI設定（挨拶/話し方/営業時間/転送）
      通知設定（メール/Slack/送信ログ）
      電話番号設定 / 発信者ルール（ブロック）
      ユーザー管理（権限）
    運営の管理画面
      運営ダッシュボード（MRR/粗利）
      問い合わせ管理（リード/ステップメール/商談）
      テナント管理（プラン/停止）
    裏側 バックエンド
      Twilio連携（着信/発信/WS）
      AI Orchestrator（会話/要約）
      課金・原価（usage_records）
      通知（メール/Slack/週次サマリー）
    外部サービス
      Twilio（電話）
      OpenAI / OpenRouter（AI）
      Supabase（DB）
      Resend（メール）
      Square（決済）
      Render / Vercel（ホスティング）
```

---

## 5. ディレクトリ構造（コードの置き場所）

```
backend/src/
  server.ts          … 起動・ルート登録・ワーカー
  twilio/            … 着信/発信Webhook・署名検証・TwiML
  ws/                … 通話のWebSocket（会話の入口）
  ai/                … Orchestrator・プロンプト・要約・LLM
  outbound/          … AI架電（キャンペーン・発信）
  leads/             … 問い合わせ導線・チャットボット
  billing/           … 料金/原価・Square
  notify/            … メール/Slack/週次サマリー
  db/                … DBアクセス・クエリ
  demo/              … デモ用データ

frontend/app/
  page.tsx           … LP
  contact/           … 問い合わせフォーム
  legal/             … 法務ページ
  (app)/             … 管理画面（ダッシュボード/通話/AI営業/設定…）
```

---

## 6. 一言まとめ

- **電話を受ける（着信）**と**電話をかける（架電）**の両方を、AIが会話して処理する。
- **管理画面**は「顧客（店舗）用」と「運営（あなた）用」で内容が切り替わる。
- **外部サービスのキーを入れた分だけ本物**になり、未接続でも全画面をデモで触れる。
