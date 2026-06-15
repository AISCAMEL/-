# AIS Corporate — WordPress テーマ

合同会社アイズ コーポレートサイト（Next.js 版）を **WordPress クラシックテーマ**として移植したものです。
デザイン（Tailwind のデザイントークン・レイアウト）、全ページ、サンプルデータ、書体（Zen Kaku Gothic New）、
スライダー・スクロールアニメーション・事業構造図まで忠実に再現しています。

> 日常の運用・編集の手順（管理画面での編集、文言の直し方、チャット設定、ロゴ・映像の差し替え）は
> **[OPERATIONS.md](OPERATIONS.md)** を参照してください。

## 特長

- **ノープラグインで動作**。有効化するだけで固定ページ・フロントページ・サンプル投稿を自動生成します。
- コンテンツは `inc/data.php` に集約（単一の情報源）。確定情報が出たらここを編集します。
- 事業・実績・お知らせはカスタム投稿タイプ（`/services/<slug>`・`/works/<slug>`・`/news/<slug>`）で
  URL を Next.js 版と一致。お知らせは管理画面の本文編集にも対応（入力があれば優先表示）。
- お問い合わせフォームは `wp_mail()` で実送信（nonce + ハニーポット付き）。送信先は管理者メール。
- **SEO はプラグイン不要で内蔵**（`inc/seo.php`）。各ページ・各事業の `<title>`／meta description／
  canonical／OGP／Twitter カードを Next.js 版と一致させ、トップに構造化データ（Organization＋FAQ）も出力。
  ※ Yoast 等の SEO プラグインを併用する場合は、重複を避けるため本テーマの SEO 出力との調整が必要です。

## AIチャット（OpenRouter 自動応答）

サイト全体に常駐するAIチャットを内蔵しています。会社概要・事業・FAQ を読み込んだ
システムプロンプトで、訪問者の質問に自動で回答します（応答は OpenRouter 経由）。

**APIキーはフロントに出さず、サーバー側（WP REST `ais/v1/chat`）で OpenRouter を呼び出します。**

### セットアップ

1. [openrouter.ai](https://openrouter.ai/) で APIキーを発行します。
2. キーの設定方法は2通り（**定数を推奨**：DBに保存されません）。
   - `wp-config.php` に追記：
     ```php
     define( 'AIS_OPENROUTER_API_KEY', 'sk-or-xxxxxxxx' );
     ```
   - または管理画面 **設定 → AIチャット** の「OpenRouter APIキー」に入力。
3. 同設定画面で、有効化・モデル・あいさつ文・追加指示を調整できます。
   - モデル例：`openai/gpt-4o-mini`（既定）、`anthropic/claude-3.5-haiku`、`google/gemini-flash-1.5`
4. 有効化＋キー設定済みのときだけ、フロントにチャットが表示されます。

会社概要・事業内容・FAQ は `inc/data.php` から自動でAIに渡されるため、
データを更新すればAIの回答内容も追従します。電話番号は案内しない方針もプロンプトに含めています。

### 案内係（女性コンシェルジュ）と音声読み上げ

- アイコンは**女性コンシェルジュ（案内係）**。AIの吹き出しにアバターを添え、回答末尾で
  関連ページ・お問い合わせへ「ご案内／ご誘導」する接客トーンです。
- 回答を**音声で読み上げ**ます（ブラウザ標準の Web Speech API＝**追加費用なし**、日本語音声）。
  - ヘッダーのスピーカーアイコンでオン／オフ切替（設定はブラウザに記憶）。
  - 読み上げ中はヘッダーのアバターが点灯。送信・パネルを閉じると停止。
  - 音声に非対応のブラウザでは自動的にトグルを隠し、テキストのみで動作します。
### 動画オペレーター（人物映像）

チャットを開くと、ヘッダー下に**オペレーター映像**（ミュート・自動ループ）が再生され、
読み上げ音声と合わせて「人が応対している」体験になります。アバター（起動ボタン・
ヘッダー・吹き出し）も同じ人物の写真を使用します。

媒体は `assets/media/` に置いた次のファイルを自動採用します（差し替え可）：

| ファイル | 用途 | 目安 |
|---|---|---|
| `operator.mp4` | ループ映像（ミュート） | 480×270 / H.264 / 数秒〜十数秒 |
| `operator-poster.jpg` | 映像の静止画（読込前/非対応時） | 480×270 |
| `operator-avatar.jpg` | 丸アイコン（起動ボタン・ヘッダー・吹き出し） | 正方形 256px |

- ファイルを差し替えるだけで別人物に変更できます。`operator-avatar.jpg` が無ければ
  内蔵のイラストアバター（SVG）に自動フォールバックします。
- 映像は**ミュート**で再生し、音声は読み上げ（TTS）側で出します。
- **重要（権利）**：人物の写真・映像を利用する際は、本人の同意または素材の利用許諾を
  必ずご確認ください（AI生成素材の場合も生成サービスの規約に従ってください）。

### さらに本格的な動画オペレーター

回答に合わせて口が動く（リップシンク）／ビデオ通話風のリアルタイム会話アバターは、
D-ID / HeyGen / Tavus 等の外部サービス連携で拡張可能です（有料・別途APIキー）。
現在の「OpenRouter（頭脳）＋サーバー側プロキシ」の構成はそのまま“顔”だけ差し替えられます。

## インストール

1. このディレクトリ（`ais-corporate`）を WordPress の `wp-content/themes/` に配置します。
2. 管理画面 → 外観 → テーマ で「AIS Corporate」を有効化します。
   - 有効化時に固定ページ（ホーム / about / message / philosophy / brands / faq / contact / privacy）、
     フロントページ設定、サンプル投稿（事業・実績・お知らせ）が自動作成されます。
3. 管理画面 → 設定 → パーマリンク を一度「変更を保存」して、リライトルールを反映してください
   （カスタム投稿タイプの URL を有効化するため）。

## ローカルでの実機確認

MySQL を用意しなくても、SQLite で WordPress 一式を立ち上げて確認できます。

```bash
bash wordpress-theme/dev/setup-local-wp.sh
# 完了後に表示されるコマンドでサーバーを起動 → http://localhost:8089
```

> WordPress 6.5 + SQLite で、テーマ有効化時のページ自動生成・フロントページ設定・
> 事業/実績/お知らせのカスタム投稿の URL（`/services/carmel/` 等）・SEO（title/description/
> canonical/OGP）・404・お問い合わせフォーム・AIチャットの表示制御まで動作を確認済みです。

## スタイルのビルド（編集する場合）

スタイルは Tailwind CSS でビルドし、`assets/css/theme.css`（コミット済み）を読み込みます。
PHP を編集してクラスを追加・変更した場合は、再ビルドが必要です。

```bash
# テーマディレクトリで
npx tailwindcss -c ./tailwind.config.js -i ./assets/css/src.css -o ./assets/css/theme.css --minify
```

- `tailwind.config.js` … デザイントークン（colors / shadow / font 等）。Next.js 版と一致。
- `assets/css/src.css` … 入力 CSS（base / components / utilities）。
- `assets/js/main.js` … モバイルメニュー・ブランドスライダー・FAQ アコーディオン・スクロール表示。

## 主なファイル構成

```
ais-corporate/
├─ style.css                 テーマ宣言
├─ functions.php             読み込み・CPT登録・固定ページ自動生成・フォーム処理
├─ header.php / footer.php   共通ヘッダー・フッター（ナビ／ドロップダウン）
├─ front-page.php            トップ（各セクションを template-parts から読込）
├─ template-parts/home/*     hero / problems / solutions / business-map /
│                            brand-slider / strengths / workflow / case-studies /
│                            message / faq
├─ page-templates/*          about / message / philosophy / brands / faq /
│                            contact / privacy
├─ archive-ais_service.php / single-ais_service.php   事業一覧・詳細
├─ archive-ais_work.php    / single-ais_work.php      実績一覧・詳細
├─ archive-ais_news.php    / single-ais_news.php      お知らせ一覧・詳細
├─ index.php / page.php / 404.php                     フォールバック
├─ inc/data.php             全コンテンツ（データ層）
├─ inc/helpers.php          表示ヘルパー（アイコン・ボタン・見出し・CTA 等）
├─ inc/seo.php              SEO（title/description/canonical/OGP）
├─ inc/chat.php             AIチャット（OpenRouter プロキシ・管理画面設定）
├─ template-parts/chat-widget.php   チャットUI
└─ assets/css|js            ビルド済み CSS・スクリプト（main.js / chat.js）
```

## 差し替えポイント（placeholder）

- 代表メッセージ（`inc/data.php` の `ais_representative()`）はドラフトです。
- 実績・お知らせはサンプル（`is_placeholder`）。確定したら `inc/data.php` を更新、
  または管理画面の各投稿を編集してください。
- プライバシーポリシーの制定日・連絡先は実情報に合わせて確認してください。
