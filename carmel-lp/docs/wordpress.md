# WordPress への設置（チャットウィジェット埋め込み）

カーメル相談AIのウィジェットを、WordPressサイトの右下に表示させる手順です。
チャットの頭脳（AI応答・予約・有人対応）は Node アプリ側で動き、WordPress には
**読み込み用のタグを1つ貼るだけ**で設置できます。

## 前提

1. この Node アプリ（`server.js`）を、**HTTPSでアクセスできるホスト**に公開しておく。
   例: `https://chat.example.com` （以下「配信元ホスト」と呼びます）
   - WordPress が HTTPS の場合、配信元ホストも **必ず HTTPS**（混在コンテンツ回避）。
2. 配信元ホストの環境変数 `ALLOWED_ORIGINS` に **WordPressサイトのドメイン**を設定。
   ```bash
   ALLOWED_ORIGINS=https://www.your-wordpress-site.com
   OPENROUTER_API_KEY=sk-or-...   # 既存のAI設定
   # 必要に応じて SLACK_* / ADMIN_* など
   ```
   （未設定だと全オリジン許可になります。本番では設定推奨）

## 設置方法 A：タグを貼る（最も簡単）

WordPress 管理画面で、フッターにHTMLを差し込めるプラグイン
（例: 「WPCode」「Insert Headers and Footers」）や、テーマの「フッター」設定に
次の1行を貼り付けます。`YOUR-HOST` を配信元ホストに置き換えてください。

```html
<script src="https://YOUR-HOST/assets/embed.js"
        data-api-base="https://YOUR-HOST"
        data-line-url="https://lin.ee/u2tox5s"
        data-tel="050-1793-5554"></script>
```

- `data-api-base`: `/api/*` を提供する配信元ホスト（省略時はスクリプトの配信元）。
- `data-line-url` / `data-tel`: LINE・電話の導線を上書き（任意）。

保存してサイトを開くと、右下にチャットアイコンが表示されます。

## 設置方法 B：プラグインを使う

`docs/carmel-chat-plugin/` をフォルダごと zip 化して、WordPress の
「プラグイン → 新規追加 → プラグインのアップロード」から導入し、有効化します。
配信元ホスト等は `carmel-chat.php` 冒頭の定数、または
`functions.php` でフィルタ `carmel_chat_options` で設定できます。

## 動作の仕組み（概要）

```
WordPressページ
  └─ <script embed.js>   ← ウィジェットのCSS/HTMLを注入し、設定を渡して初期化
        └─ /api/chat 等へ（配信元ホスト・CORS許可済み）
              └─ Node(server.js) → OpenRouter / Slack / 予約保存 ...
```

- ウィジェットのCSSは `.chat-layer` 配下にスコープしてあり、**WordPressテーマの
  デザインを壊しません**（色変数も汚染しない設計）。
- 既存ページにチャット用のDOMが無くても、`embed.js` が自動で生成します。

## うまく出ないとき

- ブラウザのコンソールに CORS エラー → 配信元の `ALLOWED_ORIGINS` に WP ドメインを追加。
- 混在コンテンツ(mixed content)エラー → 配信元ホストを HTTPS にする。
- アイコンは出るが応答しない → 配信元で `OPENROUTER_API_KEY` が設定されているか確認。
- `404 /assets/embed.js` → `data-api-base`/`src` のホストが正しいか確認。
