# APPREX WordPress テーマ

クラウド型アプリ開発プラットフォーム「APPREX」（合同会社アイズ）公式サイトの WordPress テーマです。現行の静的サイト（Cloudflare Pages 版）の実データ・デザイン・画像をもとに、WordPress 向けにブラッシュアップして再構築しています。

## 特長

- **ワンクリック構築（self-seeder）**：テーマを有効化するだけで、固定ページ・静的フロントページ・グローバルメニュー・導入事例（CPT）と画像が自動生成されます。手動セットアップ不要。
- **現行サイト準拠のデザイン**：明るいブルー系パレット（#3B82F6 / グリーン #10B981 / オレンジ #F59E0B / 背景 #F0F9FF）、ヒラギノ角ゴ系フォント。
- **ライセンス非依存**：Elementor Pro は不要。導入事例・FAQ は標準の WordPress（CPT＋ACF/メタボックス）で編集可能。
- モバイルファースト、Lazy Load、スクロールリビール、カウンターアニメーション。

### ビジネス機能

- **AI サイト内チャットボット（OpenRouter）**：APIキーを入れるだけで、料金・サービスを把握した接客AIが全ページ右下に常駐。キー未設定時は Zapier iframe にフォールバック。
- **チャット学習（ナレッジ）**：管理画面のテキスト欄に自社情報・FAQ・ルールを書くと、ボットが最優先で参照（WP側で随時“学習”更新）。
- **AI 記事生成**：投稿 > AI記事生成 から、テーマ/キーワード/トーン/文字数を指定してブログ記事を OpenRouter で生成・下書き保存。
- **見積もり → 発注フロー**：`/estimate` でサービス・プラン・オプションを選ぶと概算が即時表示され、そのまま発注（注文 CPT 作成＋管理者通知）まで完結。金額はサーバー側で再計算（改ざん防止）。
- **WPネイティブ フォーム**：お問い合わせ / 資料請求 / 無料体験。送信は REST、データは「お問い合わせ」CPT に保存。プラグイン不要。
- **自動返信 ＋ ステップメール（最大1年）**：申込直後に自動返信、その後 1/3/7/14/30/60/90/180/365 日目にフォローメールを wp-cron で自動配信（配信停止リンク付き、内容は `apprex_step_mails` フィルタで編集可）。
- **LINE誘導**：LINE URL を設定すると、チャット・各フォーム・フッターに「LINEで相談」CTA を表示。

### 管理画面メニュー

| 場所 | 内容 |
|------|------|
| 設定 > APPREX チャット | OpenRouter APIキー・モデル・**チャット学習ナレッジ** |
| 設定 > APPREX 連携 | LINE URL・通知先メール・資料DL URL・ステップメール有効化 |
| 投稿 > AI記事生成 | AIブログ記事ジェネレーター |
| 見積・発注（左メニュー） | `/estimate` からの注文一覧 |
| お問い合わせ（左メニュー） | 全フォーム送信の保存・ステップメール状況 |

### OpenRouter 設定

`wp-config.php` に定数で設定するのが安全です（管理画面でも可）。

```php
define( 'APPREX_OPENROUTER_API_KEY', 'sk-or-xxxxxxxx' );
// 任意: モデル指定（未指定なら anthropic/claude-3.5-haiku）
define( 'APPREX_OPENROUTER_MODEL', 'anthropic/claude-3.5-haiku' );
```

> ローカル等でAPIを使わず動作確認するには `define( 'APPREX_CHAT_MOCK', true );`（モック応答）。

## インストール（最短手順）

1. WordPress 管理画面 → **外観 > テーマ > 新規追加 > テーマのアップロード** で `apprex-theme.zip` をアップロードし「有効化」。
2. 有効化と同時に以下が自動生成されます：
   - 固定ページ：ホーム / 特徴 / 機能説明 / 料金プラン / よくある質問 / 無料体験申し込み / お問い合わせ / ホームページ制作 / 会社概要
   - 静的フロントページ（ホーム）設定
   - グローバルメニュー（primary 位置に割り当て）
   - 導入事例（case）5件＋アプリ画面サンプル画像
3. 必要に応じて **設定 > パーマリンク** を一度保存（リライト確実化）。

> 自動生成を無効化したい場合は、有効化前に `wp-config.php` で `define( 'APPREX_DISABLE_SEEDER', true );` を定義してください。既存のページ・事例は上書きされません（冪等）。

## 推奨プラグイン

- **ACF（Advanced Custom Fields）**：導入事例フィールドの編集 UI（未導入でも簡易メタボックスで動作）
- **Contact Form 7 / WPForms**：無料体験・お問い合わせフォーム（未設置時は仮フォームを表示）
- WebP 変換・遅延読み込み系（例：EWWW Image Optimizer）

## 編集ポイント

- **キャンペーンバー・ヒーロー文言**：`header.php` / `template-parts/sections/hero.php`
- **料金**：`template-parts/pricing-table.php`（アプリ開発／制作代行）、`page-templates/page-hp-creation.php`（HP制作）
- **チャットボット URL**：`apprex_chatbot_url()`（`functions.php`）。フィルター `apprex_chatbot_url` で上書き可。
- **導入事例**：管理画面「導入事例」から追加。業種・成果指標・開発期間・利用機能を入力し、アイキャッチ（アプリ画面）を設定。
- **メニュー**：外観 > メニューで「メインメニュー」を編集。

## ファイル構成

```
apprex/
├── style.css                  デザイントークン＋全スタイル
├── functions.php              初期化・メニュー・チャットボット注入
├── header.php / footer.php     キャンペーンバー＋ナビ / 合同会社アイズ情報
├── front-page.php             HOME（hero→stats→problem→solution→features→functions→cases→instagram→pricing→hp-cta→faq→final-cta）
├── archive-case.php / single-case.php   導入事例 一覧・詳細
├── page.php / index.php
├── inc/
│   ├── installer.php          ★ self-seeder（有効化時の自動構築）
│   ├── cpt-cases.php          CPT「case」＋タクソノミー「industry」
│   ├── acf-fields.php         ACF フィールド＋非 ACF フォールバック
│   ├── enqueue.php / template-helpers.php
├── template-parts/
│   ├── sections/              HOME 各セクション
│   ├── case-card.php / pricing-table.php / faq-list.php
│   ├── chatbot.php            Zapier フローティングチャット
│   ├── placeholder-form.php / final-cta.php
├── page-templates/            下層ページ 8 種
└── assets/
    ├── images/                ロゴ＋アプリ画面サンプル（現行サイト実画像）
    └── js/main.js
```

## ローカル検証済み環境

WordPress 6.7.1 / PHP 8.4 / SQLite で構築・全ページ HTTP 200・PHP エラーなしを確認済み（`docs/APPREX_WordPress構築レポート.md` 参照）。

## 現行サイトからの修正（ブラッシュアップ）点

- 料金表記の統一：stale だった `pricing.html`（¥30,000/¥80,000、電話番号 03-XXXX-XXXX、©2024、ブランド誤記「アプリックス」）は不採用。README/トップページの正データ（アプリ開発 Trial¥19,800〜）に統一。
- 電話番号は全ページ非表示（現行方針を踏襲）。
- 「即日公開」表現は不使用。「スピード公開（最短2週間）」に統一。
- 配色は要件「案A 爽やかなブルー系」を正式採用。
