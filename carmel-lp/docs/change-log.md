# 変更履歴

## v1.0.0 — 初版
- 変更理由: カーメル改良版LPの新規実装（PDF仕様）＋ OpenRouter AIチャットボット追加要望。
- 対象箇所:
  - LP本体再現（`index.html`、`assets/css/*`）
  - 追加チャット導線ウィジェット（`chat-layer`、`assets/css/lp-chat.css`、`assets/js/lp-chat.js`）
  - AIチャットボット（`assets/js/chatbot.js`、`lib/carmel-bot.js`、`server.js`、`api/chat.js`）
  - 設定の一元化（`assets/js/config.js`）、計測（`assets/js/analytics.js`）
- 影響範囲: 追加UIはLP本体DOMと分離。LP本体への副作用なし。
- テスト観点: `tests/checklists/manual-qa.md`（機能/UI/レスポンシブ/A11y/性能/回帰/運用）。
- ロールバック: `chat-layer` と関連CSS/JSの読み込みを外せばLP本体は復旧可能。
