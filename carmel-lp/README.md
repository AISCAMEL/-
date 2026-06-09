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
