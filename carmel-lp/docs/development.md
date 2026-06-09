# 開発ガイド

## 原則
- 構造はHTML / 見た目はCSS / 挙動はJavaScript / 状態はJSで一元管理。
- DOM識別は `data-*` 属性。セレクタ依存を減らす。
- 文言・リンク・秒数・モデルは `assets/js/config.js`（クライアント）と
  `lib/carmel-bot.js`（サーバー）に集約。変更時はまずここを見る。
- 追加UI（chat-layer / lp-chat.css / lp-chat.js / chatbot.js）はLP本体から独立。

## 起動
```bash
OPENROUTER_API_KEY=sk-or-... npm start   # http://localhost:3000
```
キー未設定でもLP/ウィジェットは動作（AI応答のみフォールバック）。

## レイヤー責務
| 層 | ファイル | 責務 |
|---|---|---|
| 設定 | `assets/js/config.js` | 文言/URL/秒数/サジェスト/計測名 |
| 計測 | `assets/js/analytics.js` | dataLayer push（失敗してもUIを止めない） |
| ウィジェット | `assets/js/lp-chat.js` | 開閉・タイマー・ARIA同期・状態管理 |
| AI会話 | `assets/js/chatbot.js` | 送受信・SSE読取・描画・フォールバック |
| サーバー | `lib/carmel-bot.js` | OpenRouterプロキシ・プロンプト・モデル順 |

## SSEプロトコル（/api/chat）
- リクエスト: `POST { messages: [{role:'user'|'assistant', content}] }`
- レスポンス: `text/event-stream`
  - 差分: `data: {"delta":"..."}`
  - 完了: `data: [DONE]`
  - 異常: `data: {"error":"..."}`（クライアントは有人導線へフォールバック）

## 変更のやり方（例）
- 自動表示秒数 → `config.js` の `autoPopupDelay`
- LINE/電話 → `config.js`（+ ヘッダーCTAのHTML）
- AIの口調・禁止事項 → `lib/carmel-bot.js` の `SYSTEM_PROMPT`
- 利用モデル → 環境変数 `OPENROUTER_MODELS`（カンマ区切り）

## テスト
`tests/checklists/manual-qa.md` の手動チェックリストを使用。
スマホ実機での開閉・遷移・自動ポップアップ・既存LP非破壊を最重要回帰観点とする。

## ロールバック
`index.html` の `chat-layer` ブロックと `lp-chat.css` / `lp-chat.js` /
`chatbot.js` の読み込みを外せば、LP本体は無傷で復旧する。
