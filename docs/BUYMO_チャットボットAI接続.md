# BUYMO チャットボット AI接続ガイド

チャットボット（`assets/js/chatbot.js`）は**ルールベース**で動作します。さらに **AI（自然応答）** を後付けで有効化できます。未設定でも壊れず、ルールベースで動き続けます（フォールバック）。

## 動作モード
- **既定（AIなし）**：キーワード一致のルールベース。オフラインで即応答。
- **AI接続時**：入力をGASへ送り、社内情報を根拠にAIが回答。一致しない質問にも自然に応答。失敗・空回答・12秒タイムアウト時は自動でルールベースに戻ります。

ボットは **ユーザー用／加盟店用** をヘッダーのトグルで切替（`window.BUYMO_BOT_MODE` で初期値指定）。AIもモード別の社内情報で回答します。

## 有効化の手順
1. **GAS 側**：`gas/Config.gs` の `OPENROUTER_API_KEY` を設定（既存の申込AI要約と同じキーでOK）。`OPENROUTER_MODEL` で使用モデルを指定。
   - 例：`google/gemini-flash-1.5`、`deepseek/deepseek-chat` など。
   - **Claude を使う場合**：`gas/AI.gs` の `botAnswer_()` の `UrlFetchApp.fetch` 先を Anthropic API（`https://api.anthropic.com/v1/messages`、ヘッダー `x-api-key` / `anthropic-version`、`model: "claude-..."`）に差し替え。OpenRouter 経由で `anthropic/claude-*` を指定する方法もあります。
2. **フロント側**：`assets/js/chatbot.js` 先頭の `BOT_ENDPOINT` に GAS ウェブアプリURL（`…/exec`）を設定。
3. 公開 → ボットがAI応答に切り替わります（キー未設定ならルールベースのまま）。

## 仕組み
```
ユーザー入力 → chatbot.js → GAS doGet(action=bot&mode=&q=, JSONP)
            → AI.gs botAnswer_(mode,q)：社内情報＋モード別プロンプトで OpenRouter 呼び出し
            → 回答を表示（空/失敗時は chatbot.js のルールベースが応答）
```
- 社内情報（グラウンディング）は `botAnswer_()` 内の `ctx` を編集して調整できます（料金・流れ・対応範囲など）。
- 回答は「最大3文・社内情報のみを根拠・不明時は問い合わせ/本部へ案内」に制約しています（誤情報の抑制）。

## 注意（運用・セキュリティ）
- ボットの質問はGAS（OpenRouter）に送信されます。**個人情報や機密は入力しない**よう、必要なら入力欄に注意書きを。
- JSONP(GET)のため質問文がURL/ログに残り得ます。機微なやり取りはフォーム/電話へ誘導。
- 料金：OpenRouter等の従量課金。安価モデルでの運用や、レート制限・1日上限の設定を推奨。
- 回答はAI生成のため、**金額確約・法的判断はしない**運用（最終は人が対応）に。

## 関連
`assets/js/chatbot.js`（フロント・KB/フォールバック）／`gas/AI.gs`（`botAnswer_`）／`gas/WebApp.gs`（`doGet action=bot`）／`gas/Config.gs`（キー）。
