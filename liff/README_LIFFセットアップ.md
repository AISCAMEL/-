# LINE LIFF申込アプリ セットアップ手順（本番システム連携版）

LINEのリッチメニュー「申込」からLIFFフォームを開き、送信すると
**CarMel本番システム（Next.js/Supabase）の `/api/intake/shinsa` に自動登録**されます。
その場で「目安ランク」も表示します。

## 構成
```
LINEリッチメニュー「申込」→ LIFF（index.html）
   └ 同一オリジンの apply.php に送信（CORS・鍵露出なし）
        └ apply.php が本番API /api/intake/shinsa へ中継（x-api-key）
             └ applications 登録 → 店舗マッチング → ログイン発行(メール)
                → 顧客マスタ保存 → Slack/Asana通知
```
※ 申込者には**ログイン情報メール**が自動送信され、そのまま `/mypage` を利用できます。

---

## 手順

### 1. 本番システム側（Vercel）の環境変数
Vercel プロジェクト `new-repository` の環境変数に **`INTAKE_API_KEY`**（任意の長い文字列）を設定。
※ `NEXT_PUBLIC_SUPABASE_URL` / `SUPABASE_SERVICE_ROLE_KEY` は設定済み前提。

### 2. LINE Developers で LIFF を作成
1. LINEログインチャネル → 「LIFF」→「追加」
   - サイズ：**Full**
   - エンドポイントURL：**`https://carmelonline.jp/liff/`**
   - スコープ：**profile**、**openid**
2. 発行された **LIFF ID** をコピー

### 3. ファイルを設置（XServer ファイルマネージャ）
`carmelonline.jp/public_html/` に **`liff`** フォルダを作り、次の2つをアップロード：
- `liff/index.html`
- `liff/apply.php`

**設定の書き換え（2か所だけ）**
- `index.html` 冒頭：`const LIFF_ID = "手順2のLIFF ID";`
- `apply.php` 冒頭：`$API_KEY = '手順1の INTAKE_API_KEY と同じ値';`

### 4. リッチメニューに「申込」ボタン
LINE Official Account Manager → リッチメニュー →「申込」のリンク先に
**LIFF URL**（`https://liff.line.me/＜LIFF ID＞`）を設定。

### 5. テスト
- スマホのLINE →「申込」→ 入力 → 送信
- 本番システムの **申込一覧（/hq）に新規「審査申込」が表示**され、申込者にログインメールが届けばOK
- ※PCで `https://carmelonline.jp/liff/` を直接開くと「テストモード」で動作確認可（仮ID）

---

## よくある確認
- `unauthorized` が返る → `apply.php` の `$API_KEY` と Vercel の `INTAKE_API_KEY` が一致しているか
- 申込は入るがマイページに入れない → ログインメール（Resend）設定、`profiles` の確認
- 送信失敗 → `apply.php` の `$INTAKE_URL` が本番ドメインか

## 補足：目安ランク表示について
送信時に表示する A〜D は**フォーム入力からその場で算出する目安**です（本番の最終審査とは別）。
実際の審査・信販提案は本番システム（/hq）側で行われます。

## 第2弾（今後）
- `profiles.line_user_id` と LINE userId を紐付け → **マイページ自動ログイン**
- 書類アップロード（LIFF → Supabase Storage）
- 審査結果のLINE通知
