# APPREX WordPress テーマ

クラウド型アプリ開発プラットフォーム「APPREX」公式サイト（`site.aiscompany.jp`）リニューアル用の自作テーマです。制作指示書（`docs/APPREX_WordPress制作指示書.md`）の構成を実装しています。

## 実装方針（指示書からの修正点）

指示書では Elementor Pro + Hello Elementor が第一候補ですが、**Elementor Pro のライセンスが「要確認」ステータス**のため、本テーマは **ライセンス非依存の独立テーマ** として実装しました。

- 導入事例（cases）と FAQ は **CPT + ACF（無料版でも可）** で wp-admin から編集可能 → 指示書 §9「クライアント自身が編集可能」を Elementor 非依存で達成。
- ACF が未インストールでも、簡易メタボックスで事例フィールドを入力できるフォールバックを内蔵。
- 後から Elementor を導入してもテンプレートと共存できる構造。

## 動作要件

- WordPress 6.0 以上 / PHP 7.4 以上
- 推奨プラグイン：
  - **ACF（Advanced Custom Fields）** … 導入事例のフィールド入力（無くても動作）
  - **Contact Form 7** または **WPForms** … 無料体験・お問い合わせフォーム
  - WebP 変換・遅延読み込み系（例：EWWW Image Optimizer）※ 画像最適化（§9）

## セットアップ手順

1. `wp-content/themes/apprex/` をテーマディレクトリに配置し、外観 > テーマで **APPREX** を有効化。
   - 有効化時に `cases` の CPT・`industry` タクソノミーが登録され、パーマリンクが自動フラッシュされます。
2. **固定ページを作成**（指示書 §12 のスラッグで作成）：

   | ページ名 | スラッグ | 割り当てるテンプレート |
   |---------|---------|----------------------|
   | ホーム | `front-page`（任意） | （フロントページに指定） |
   | 特徴 | `features` | 特徴ページ (Features) |
   | 機能説明 | `functions` | 機能説明ページ (Functions) |
   | 料金プラン | `pricing` | 料金プランページ (Pricing) |
   | よくある質問 | `faq` | よくある質問ページ (FAQ) |
   | 無料体験申し込み | `free-trial` | 無料体験申し込みページ (Free Trial) |
   | お問い合わせ | `contact` | お問い合わせページ (Contact) |

3. **設定 > 表示設定** で「ホームページの表示」を「固定ページ」にし、作成したホーム用固定ページを割り当て（`front-page.php` が自動適用されます）。
4. **外観 > メニュー** で「グローバルナビ」位置にメニューを割り当て（未割り当て時はフォールバックメニューを表示）。
5. **導入事例**：左メニュー「導入事例」から 9 件を登録（指示書 §7 の表を参照）。各事例で業種・成果指標・開発期間・利用機能を入力し、アイキャッチ画像（WebP 推奨）を設定。
6. **フォーム**：`free-trial` / `contact` ページの本文に Contact Form 7 等のショートコードを貼り付け（未入力時は仮フォームを表示）。

## ファイル構成

```
apprex/
├── style.css                  テーマヘッダー＋全スタイル（デザイントークン/モバイルファースト）
├── functions.php              テーマ初期化・メニュー・画像サイズ
├── header.php / footer.php     共通ヘッダー（ナビ）/ フッター
├── front-page.php             HOME（セクション 01–10 を組み立て）
├── page.php / index.php        汎用ページ / フォールバック
├── archive-case.php           導入事例一覧（業種フィルター付き）
├── single-case.php            導入事例詳細
├── inc/
│   ├── enqueue.php            CSS/JS 読み込み・Lazy Load 付与
│   ├── cpt-cases.php          CPT「case」＋タクソノミー「industry」
│   ├── acf-fields.php         ACF フィールド群＋非 ACF フォールバック
│   └── template-helpers.php   apprex_field() 等の共通関数
├── template-parts/
│   ├── sections/              HOME 各セクション（hero〜faq）
│   ├── case-card.php          事例カード（一覧で再利用）
│   ├── pricing-table.php      3 プラン料金表（HOME/料金ページ共用）
│   ├── faq-list.php           FAQ アコーディオン（共用）
│   ├── placeholder-form.php   仮フォーム
│   └── final-cta.php          共通 Final CTA
├── page-templates/            下層ページ用テンプレート 6 種
└── assets/js/main.js          ナビ/タブ/アコーディオン/リビール/カウンター
```

## 公開前チェック（指示書 §10）

- [ ] スマホ・タブレット実機で表示崩れ確認
- [ ] フォーム送信テスト（本番フォームに差し替え後）
- [ ] 全リンクの疎通確認
- [ ] PageSpeed Insights 計測（画像 WebP 化・Lazy Load 済み）
- [ ] 導入事例 9 件の登録とアイキャッチ設定
