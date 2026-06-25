# CARMEL自動生成 WPCodeスニペット

carmelonline.jp（カーメル／合同会社アイズ）の記事自動生成スニペットです。
本体プラグイン v5.7 は一切変更せず、WPCode の「Run Everywhere」PHPスニペットとして動かします。

## ファイル

- `carmel-auto-v5.php` … **最新版（これを使う）**。実際に稼働中のスニペット
  7547「CARMEL自動生成」を土台に、本文セクション画像の裏側生成と
  CTA自動修正を追加したもの。
- `carmel-auto-v4.php` … 旧版（引き継ぎ書からの再構成。エンジン呼び出しと
  テーマキューの仕様が実物と異なるため**使用しない**）。

## 貼り付け先

WordPress管理画面 →「Code Snippets」(WPCode) → スニペット **ID 7547
「CARMEL自動生成」**（php / 有効）。新規追加は不要で、このスニペットの
中身を入れ替えるだけ。

> ⚠️ 似た名前の 7272「CARMEL 自動生成（WP-Cron）」と 7104 は**無効のまま**に。
> 両方ONにすると記事やGoogle投稿が二重になる。今回ONにするのは 7547 だけ。

## 貼り付け手順（3分）

1. スニペット 7547 を開く → コード欄を全部消す
2. `carmel-auto-v5.php` の中身を貼り付ける
   - ⚠️ 先頭の `<?php` の行は貼らない（WPCodeが自動で付ける）
   - ⚠️ 末尾に `?>` は付けない
3. 「更新（保存）」→ ステータスは**有効のまま**

## v5 で追加した点（v4 ではなく実物を土台にしている）

- 記事生成エンジンは実物どおり `WP_REST_Request('/carmel/v1/generate')` 経由
- テーマキュー（`account | category | keyword | 県 | 市 | title`）はそのまま維持
- **本文セクション画像（section_1 / 2 / 3）を裏側で1枚ずつ生成**
  （見出しのあるセクションだけ・画像が空のときだけ。タイムアウト回避）
  - 管理画面の「本文画像を今すぐ1枚進める」ボタンで手動でも進められる
- **CTAボタンURLの自動修正**（main_cta_url / cta_button_url が壊れていたら
  連絡先URL〈既定 `/contact/`〉へ置き換え）
- 画像APIのタイムアウトを180秒に延長＋最大3回リトライ（429/5xx/通信エラー時）

## 動作の前提・確認ポイント

- 記事の保存先は `media_article` のまま
- 自動生成は既定で「下書き」保存（金融系のため人の確認後に公開）
- Google投稿は既定OFF（外部プラグイン「Auto Publish for Google My Business」
  との二重投稿を避けるため）
- セクション画像のACFフィールドキーは編集ページHTMLから確認した値を使用：
  - section_1_image: `field_69ffb5a4d372b`
  - section_2_image: `field_69ffb5e5d372e`
  - section_3_image: `field_69ffb8a0c02c3`
  - main_cta_url: `field_69fef07b6cbf3` / cta_button_url: `field_69ffb66cd3733`
