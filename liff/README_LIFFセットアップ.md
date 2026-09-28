# LIFF申込アプリ セットアップ手順

LINEの中で「かんたん審査」を完結させ、その場でランク表示＋案件登録するアプリです。

## 構成
```
LINEリッチメニュー「申込」→ LIFF（このアプリ）
   liff/index.html … 申込フォーム（LINE IDを自動取得）
        │ 同一オリジンで送信（CORS回避）
   liff/apply.php  … GAS APIへ中継（トークンはここに保管）
        │
   GAS WebApi.gs（action=apply）… 案件登録＋スコアリング＋信販提案 → 結果を返す
```

---

## 手順

### 1. GAS側（済んでいれば不要）
- `src/WebApi.gs` を最新に更新（`action=apply` を追加済み）→ 保存 → **Webアプリを再デプロイ（新バージョン）**

### 2. LINE Developers で LIFF を作成
1. https://developers.line.biz/ → 対象プロバイダー → **LINEログイン**チャネル（無ければ作成）
2. 「LIFF」タブ →「追加」
   - サイズ：**Full**
   - エンドポイントURL：**`https://carmelonline.jp/liff/`**（下記3で設置する場所）
   - スコープ：**profile**、**openid**
3. 発行された **LIFF ID**（例 `1656xxxxxx-abcdXYZ`）をコピー

### 3. ファイルを設置（XServer ファイルマネージャ）
1. `carmelonline.jp` → `public_html` の中に **`liff`** フォルダを作成
2. その中に次の2ファイルをアップロード：
   - `liff/index.html`
   - `liff/apply.php`
3. `index.html` を開いて、先頭の設定を書き換え：
   ```js
   const LIFF_ID = "ここに手順2のLIFF IDを貼る";
   ```
   （`apply.php` のGAS URL・トークンは設定済み）

### 4. リッチメニューに「申込」ボタン
- LINE Official Account Manager → リッチメニュー →「申込」ボタンのリンク先に **LIFF URL**（`https://liff.line.me/＜LIFF ID＞`）を設定

### 5. テスト
- スマホのLINEでリッチメニュー →「申込」→ フォーム入力 → 送信
- 「ローン案件管理」シートに **LINE_ID付き**で1行追加され、スコア・ランク・打診信販が入ればOK
- ※PCブラウザで `https://carmelonline.jp/liff/` を直接開くと「テストモード」で動作確認できます（仮ID）

---

## よくある確認
- 送信に失敗する → `apply.php` のGAS URLが最新のデプロイURLか／Webアプリ再デプロイ済みか
- 結果が返るがLINE_IDが `TEST-...` → LIFF IDが未設定、またはLINEアプリ外（テストモード）
- ランクが全部D → `Config.gs` のSCORINGブロックが入っているか

## 第2弾（今後）
- 書類アップロード（免許証・車検証）
- 進捗マイページ
- 審査結果の自動プッシュ通知
