# CARMEL自動生成 WPCodeスニペット

carmelonline.jp（カーメル／合同会社アイズ）の記事自動生成スニペットです。
本体プラグイン v5.7 は一切変更せず、WPCode の「Run Everywhere」PHPスニペットとして動かします。

## ファイル

- `carmel-auto-v4.php` … 最新の完成版

## 貼り付け方（3分）

1. WordPress管理画面 →「Code Snippets」(WPCode) →「CARMEL自動生成」を開く
2. いまの中身を全部消す
3. `carmel-auto-v4.php` の中身を貼り付ける
   - ⚠️ 先頭の `<?php` の行は貼らない（WPCodeが自動で付けます）
   - ⚠️ 末尾に `?>` は付けない
4. 「保存」して有効化

## 動作の要点

- 記事の保存先は `media_article` のまま
- 自動生成は「下書き」保存（金融系のため人の確認後に公開）
- アイキャッチは即時に1枚生成
- 本文の各セクション画像（section_1 / 2 / 3）とヒーロー画像は、
  タイムアウトを避けるため裏側で1枚ずつ順に生成
  - 管理画面の「本文画像を今すぐ1枚進める」ボタンで手動でも進められます
- CTAボタンのURLが壊れている場合は `/contact/` に自動修正
- Google投稿は publish_status が「公開」のときだけ（二重投稿を避ける）

## 設定の初期値

- 下書き保存（draft）
- 画像生成 ON / バナー OFF
- セクション画像生成 ON
- CTA自動修正 ON（空のときは `/contact/`）
- 画像モデル: `google/gemini-2.5-flash-image-preview`
