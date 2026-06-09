# 仕様サマリ（カーメルLP チャットボット）

本書は『カーメルLPチャットボット設計 仕様定義書兼開発書』（PDF原本）の実装対応サマリ。
詳細・背景・受け入れ基準は原本を正とする。ここでは実装との対応関係を示す。

## スコープ
- 既存LP構成の再現（ヘッダー/ヒーロー/5問診断/無料相談/車ラインナップ/メリット/仮審査の流れ/比較表/お客様の声/メッセージ/FAQ/フッター）
- 右下固定チャットウィジェット、手動ポップアップ、3秒後自動ポップアップ
- LINE / 電話 CTA 導線、レスポンシブ、基本アクセシビリティ、基本計測ポイント
- **【拡張】OpenRouter 経由のAI会話チャットボット**（原本では非スコープだった会話エンジンを追加要望により実装）

## 追加UIのDOM（非侵襲）
`</body>` 直前に `chat-layer` を配置（`index.html`）。LP本体DOMとは分離し、
`data-role` / `data-action` 属性でJSが識別する。

| 要素 | role | 役割 |
|---|---|---|
| `[data-role="chat-widget"]` | button | 常時表示・開閉トリガー |
| `[data-role="chat-popup"]` | dialog | AI会話UI（手動ポップアップ） |
| `[data-role="auto-popup"]` | status | 3秒後の自動声掛け |

## 状態（lp-chat.js）
`idle → widget-visible → (timer 3s) auto-shown / popup-opened → minimized …`
原本「11. 状態遷移表」に準拠。自動ポップアップはセッション内1回（`sessionStorage`）。

## 計測イベント（analytics.js / config.js）
`chat_widget_impression` / `auto_popup_impression` / `auto_popup_close` /
`chat_widget_click` / `chat_popup_open` / `chat_popup_close` /
`cta_line_click` / `cta_tel_click` ＋ チャットボット系
（`chatbot_start` / `chatbot_message_sent` / `chatbot_response` / `chatbot_error` / `chatbot_suggestion_click`）。
`window.dataLayer` があれば push、無くてもエラーにしない。

## AIチャットボット拡張
- クライアント `chatbot.js` → サーバープロキシ `/api/chat` → OpenRouter（SSEストリーミング）。
- モデルフォールバック: `deepseek(無料) → gemini(無料) → claude-haiku`（CarLoan_System 仕様準拠）。
- システムプロンプトでガードレール（審査合否を断定しない／個人情報を聞かない／具体相談は有人へ）。
- 通信失敗・キー未設定時は LINE / 電話の有人導線へフォールバック。

## 要確認事項（原本「18. 要確認事項一覧」より未解決）
- アバター/ロゴ/セクション画像の正式素材（現状はSVGプレースホルダ）
- 表示文言の法務監修（景表法等）
- GA4/GTM/広告タグの導入状況と命名規則
- 自動ポップアップ再表示ポリシーの最終確定
- 既存LP資産の再構築 or 流用（現状は仕様準拠の再現）
- OpenRouter の正確なモデルID・無料枠の可用性
