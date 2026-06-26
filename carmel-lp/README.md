# カーメルLP チャットボット

既存のカーメルLP（自社ローン・信用回復ローン訴求）を再現しつつ、CV導線を強化する
**右下固定チャットウィジェット**と、**OpenRouter 経由のAI相談チャットボット**を実装したプロジェクト。

設計の根拠は『カーメルLPチャットボット設計 仕様定義書兼開発書』（アップロードPDF）に準拠。
本リポジトリ内の要点は [`docs/specification.md`](docs/specification.md) を参照。

## 構成

```
carmel-lp/
├─ index.html              # LP本体 + 追加UI(chat-layer)
├─ server.js               # 依存ゼロのローカルサーバー(静的配信 + /api/chat)
├─ api/chat.js             # 本番用サーバーレス関数(Vercel等)
├─ lib/carmel-bot.js       # OpenRouterプロキシ共有ロジック(系統/プロンプト/フォールバック)
├─ assets/
│  ├─ css/                 # reset/base/layout/components/utilities + lp-chat.css(追加UI専用)
│  ├─ js/
│  │  ├─ config.js         # 文言・URL・秒数・モデル等の設定を一元管理
│  │  ├─ analytics.js      # 計測(dataLayer)。失敗してもUIを止めない
│  │  ├─ lp-chat.js        # ウィジェット/ポップアップ/タイマーの状態管理
│  │  ├─ chatbot.js        # AI会話エンジン(クライアント側・SSEストリーミング)
│  │  └─ main.js           # エントリ。LP本体のFAQ/診断 + 機能初期化
│  └─ img/widget/avatar.svg
├─ docs/                   # 仕様・開発・変更履歴
└─ tests/checklists/       # 手動QAチェックリスト
```

## 2つのレイヤー

1. **チャット導線ウィジェット**（PDF仕様のスコープ内）
   右下固定ボタン → タップでポップアップ展開、ロード3秒後に自動ポップアップ。
   LP本体DOMとは分離（`chat-layer`）し、取り除けばLP本体は無傷（ロールバック容易）。

2. **AIチャットボット**（追加ご要望による拡張）
   ポップアップ内でAIと会話。OpenRouter のモデルフォールバック
   （`deepseek(無料) → gemini(無料) → claude-haiku`）で応答を生成。
   AIで解決しない場合は **LINE / 電話の有人導線へエスカレーション**。

## 動かす（ローカル）

Node 18 以上。ビルド不要。

```bash
cp .env.example .env        # OPENROUTER_API_KEY を設定
OPENROUTER_API_KEY=sk-or-... npm start
# → http://localhost:3000
```

APIキー未設定でもLPとウィジェットは動作し、AI応答だけがLINE/電話導線に
フォールバックします（[障害時フォールバック方針] に準拠）。

## デプロイ（本番）

- 静的ファイル（`index.html`, `assets/`）をホスティング。
- `/api/chat` は `api/chat.js`（Vercel Node 関数）で提供。
  環境変数 `OPENROUTER_API_KEY`（必須）/ `OPENROUTER_MODELS`（任意）を設定。
- 他基盤（Netlify/Cloudflare/GAS等）へ移す場合も `lib/carmel-bot.js` のロジックを共有。

## セキュリティ / プライバシー

- **APIキーはサーバー側のみ**。クライアントJSには一切含めない。
- 会話履歴はメモリ上のみ（個人情報を localStorage 等に保存しない）。
- AIは審査合否を断定・保証しない／個人情報を聞き出さない（プロンプトでガードレール化）。
- 具体的な手続き・申込は有人窓口（LINE / 電話 050-1793-5554）へ誘導。

## よくある変更（変更箇所の集約）

| 変更内容 | 触る場所 |
|---|---|
| LINE URL / 電話番号 | `assets/js/config.js`（+ ヘッダーCTAのHTML） |
| 自動ポップアップ秒数 | `config.js` の `autoPopupDelay` |
| 文言（タイトル/挨拶/サジェスト） | `config.js` |
| 利用モデル / フォールバック順 | 環境変数 `OPENROUTER_MODELS` または `lib/carmel-bot.js` |
| AIの人格・ガードレール | `lib/carmel-bot.js` の `SYSTEM_PROMPT` |
| **AIの回答ナレッジ（FAQ・車種など）** | **`data/knowledge.csv`** を編集（下記参照） |

## AIナレッジ（`data/knowledge.csv`）の編集

AIが回答の根拠にする知識は **`data/knowledge.csv`** に集約しています。
コードを触らずに、ここを書き換えるだけで回答内容を更新できます（保存後の次の相談から自動反映）。

- 列は `category,question,answer` の3つ。
  - `category`: 分類タグ（例: 審査 / 車種 / 費用）。回答の整理用。
  - `question`: 想定される質問。
  - `answer`: その質問への回答方針。
- 1行 = 1つのQ&A。**うまく答えられなかった質問は、1行追加していく**運用を想定。
- Excel/Googleスプレッドシートで編集 →「CSV(UTF-8)」で書き出してこのファイルに上書きすればOK。
- カンマや改行を含めたい場合は、そのセルを `"..."` で囲む。
- 別の場所のCSVを使う場合は環境変数 `KNOWLEDGE_CSV` でパスを指定可能。

> 注意: 審査の合否・金利などの断定は引き続きAI側のガードレールで抑止しています。
> ナレッジに具体的数値を書いても、最終条件は「無料相談で案内」する方針は維持されます。

## 会話ログ（任意 / `CHAT_LOG`）

「うまく答えられなかった質問」を見つけて `data/knowledge.csv` に追加していくための材料として、
会話ログを保存できます。**プライバシー配慮のため既定は無効**で、環境変数で明示的に有効化します。

```bash
CHAT_LOG=1 OPENROUTER_API_KEY=sk-or-... npm start
```

- 保存先: `data/chat-logs/YYYY-MM-DD.jsonl`（`CHAT_LOG_DIR` で変更可）。
- 記録内容: 時刻・モデル・直近の質問・AI回答（IPアドレス等は記録しない）。
- ログは `.gitignore` 済み（個人情報を含みうるためコミットしない）。
- 運用イメージ: ログを見て、答えが弱かった質問を `knowledge.csv` に1行追加 → AIが賢くなる。
- サーバーレス(Vercel等)はファイルが揮発するため、永続保存するなら `CHAT_LOG_DIR` に
  永続ボリュームを指定するか、ログ基盤への連携を別途検討してください。

## 有人ハイブリッド対応（任意 / Slack連携）

営業時間内はお客様の相談を **Slack に通知**し、担当者がSlackで返信するとその内容が
チャット画面に届く——という有人対応を組み込めます。

```
通常のメッセージ … 営業時間に関係なく常にAIが自動応答

［担当者と話す］を押したとき:
        営業時間内 & Slack設定あり?
   ┌──────────┴───────────┐
  Yes                     No / 時間外
   │                        │
 Slackへ通知               担当者にはつながず、
 担当者がSlackで返信 ─▶ チャットに   AIが引き続き対応
 「担当者」メッセージで表示          ＋LINE/電話/後日連絡も案内
   │
 20秒応答なし ─▶「担当者不在」案内＋AI継続＋LINE/電話/後日連絡
```

ポイント:
- **AIは営業時間内・外を問わず常に自動応答**します（時間によるAIの停止はしません）。
- **担当者の返信は営業時間内のみ**。時間外や20秒未応答でも行き止まりにせず、
  AIで会話を継続でき、LINE/電話/後日連絡フォームも案内します。

- **Slack未設定/営業時間外なら自動的にAI＋LINE/電話へフォールバック**するため、
  設定しなくてもLPは通常どおり動作します（段階導入が可能）。
- 設定する環境変数は `.env.example` の「有人ハイブリッド対応」の項を参照。
  - `SLACK_BOT_TOKEN`（`chat:write` と `channels:history`／`groups:history` 権限）
  - `SLACK_CHANNEL`（通知先チャンネルID）
  - 営業時間: `BUSINESS_HOURS_START` / `BUSINESS_HOURS_END` / `BUSINESS_TZ_OFFSET` / `BUSINESS_DAYS`
  - 未応答タイムアウト: `HANDOFF_TIMEOUT_MS`（既定20000=20秒）
- Slack側準備: Slackアプリを作成しBotを対象チャンネルに招待 → Botトークンを設定。
- エンドポイント: `/api/handoff/{status,start,send,poll,callback,end}`（`server.js`）。
- 注意: セッションはメモリ保持のためNodeサーバー(`server.js`)前提。
  サーバーレスで使う場合は外部ストア(Redis等)への置き換えが必要です。

## テスト

```bash
npm test   # 実APIキー/実Slackトークン不要。モックで全フローを検証
```

- `tests/e2e/run.js` … AI応答（ストリーミング/ナレッジ注入/フォールバック/ログ）
- `tests/e2e/handoff.test.js` … 有人対応（営業時間/Slack通知/担当者返信/後日連絡）
