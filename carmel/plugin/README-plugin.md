# カーメルシステム（プラグイン版）

WPCode への手貼りを無くすためのプラグインです。`carmel/` のスニペットを1つにまとめ、
GitHub から **1クリック更新**できます。

## 何が入っているか（モジュール）

| モジュール | 役割 | 旧WPCodeスニペット |
|---|---|---|
| `wpcode-loan-guide.php` | ローン概算＋シミュレーション（固定13.6%・緑ボックス） | 「carmel_loan_guide 確実版」(例 ID 8549) |
| `wpcode-car-detail-ui.php` | 詳細ページの保証バッジ＋不要要素の整理 | car-detail-ui (例 ID 8082) |
| `wpcode-staff-shop.php` | 担当スタッフ・店舗表示 | staff-shop (例 ID 8550) |
| `wpcode-extra-basic.php` | 追加の基本情報4項目の入力欄 | extra-basic |
| `wpcode-shop-checklist.php` | 在庫→店舗ふりわけ（チェックリスト） | shop-checklist |
| `wpcode-shop-bulk-assign.php` | 在庫→店舗 一括割当（一覧の一括操作） | shop-bulk-assign |
| `wpcode-staff-manual.php` | スタッフ用 手順マニュアル | staff-manual |
| `wpcode-form-redirect.js` | 審査/お問い合わせURLの統一 | form-redirect（フッターJS） |

> ここに**無い**スニペット（trust / similar / cta-bar / photos / shinsa-button /
> image-comment / shop-fields / shop-map-auto / staff-shop-field / step-*）は、
> 当面 WPCode のまま残して構いません。検証しながら順次プラグインへ移します。

## 導入手順（初回のみ）

1. **プラグイン → 新規追加 → プラグインのアップロード** で `carmel-system.zip` を入れて有効化。
2. WPCode を開き、**上の表に載っている旧スニペットを無効化**（＝二重読み込み防止）。
   - 表に無いものは触らない。
3. 車両ページと在庫編集画面を1件ずつ見て、表示・動作が今まで通りか確認。

> もし表示がおかしくなったら、プラグインを無効化すれば即座に元（WPCode）に戻せます。

## 以後の更新（手貼り不要）

- こちら側が修正を GitHub に push → 公開配布リポジトリの `update.json` を更新。
- WordPress 管理画面 **プラグイン** に「更新があります」が出る → **更新** を押すだけ。

## 更新の配布元

`carmel-system.php` の `CARMEL_SYS_UPDATE_URL` が更新チェック先です（公開リポジトリの
`update.json`）。秘密情報は含めません（表示・管理UIのコードのみ）。

## ビルド方法（開発者向け）

```
cd carmel/plugin
./build.sh      # modules/ を carmel/ から再コピーして carmel-system.zip を作成
```

モジュールを追加するときは：
1. `carmel/` にファイルを置く
2. `carmel-system.php` の `carmel_sys_modules()` に1行追加
3. `build.sh` の `FILES=(...)` に同じファイル名を追加
4. `./build.sh` で zip を作り直す
