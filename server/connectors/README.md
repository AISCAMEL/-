# 取込コネクタ

外部サービスのAPIを叩いて、その結果を会計アプリの取込Webhook（同期サーバーの
`/api/inbox`）へ投入する**サーバー側スクリプト**です。APIキーはサーバー側だけで
保持し、ブラウザには一切置きません。投入されたデータは、会計アプリの
「明細の取込」→「📥 受信データを取得」で取り込めます。

```
[外部サービスAPI] → (コネクタ) → /api/inbox（同期サーバー） → 会計アプリで取込・仕訳
```

## Stripe コネクタ（`stripe.js`）

Stripe の売上（balance transactions の charge）を取得し、**売上**と**決済手数料**の
2件の仕訳データを投入します（`借）普通預金＋支払手数料 ／ 貸）売上高` 相当を、
2本の仕訳に分解）。

### 実行

```bash
STRIPE_API_KEY=sk_live_xxx \
SYNC_URL=http://localhost:8787 \
WORKSPACE=aizu-2026 \
TOKEN=合言葉 \
node server/connectors/stripe.js
```

### 環境変数

| 変数 | 既定 | 説明 |
|------|------|------|
| `STRIPE_API_KEY` | （必須） | Stripe のシークレットキー |
| `SYNC_URL` | `http://localhost:8787` | 同期サーバーのURL |
| `WORKSPACE` / `TOKEN` | （必須/任意） | 会計アプリと同じワークスペース・トークン |
| `SALES_ACCOUNT` | `400` | 売上高の科目コード |
| `FEE_ACCOUNT` | `580` | 支払手数料の科目コード |
| `SALES_TAX` / `FEE_TAX` | `sales10` / `out` | 税区分 |
| `STRIPE_API_BASE` | `https://api.stripe.com` | テスト用に差し替え可 |

- 日本円などゼロデシマル通貨はそのまま円、その他通貨は最小単位/100で換算します。
- **重複防止**：前回取得した最大 `created` を `.stripe_cursor` に保存し、次回はそれ以降のみ取得します。

### 定期実行（cron 例：15分ごと）

```cron
*/15 * * * * cd /path/to/app && STRIPE_API_KEY=sk_live_xxx SYNC_URL=http://localhost:8787 WORKSPACE=aizu-2026 TOKEN=合言葉 node server/connectors/stripe.js >> /var/log/kaikei-stripe.log 2>&1
```

## Square コネクタ（`square.js`）

Square の決済（Payments API）を取得し、売上と決済手数料の2件を投入します。

```bash
SQUARE_ACCESS_TOKEN=xxx SYNC_URL=http://localhost:8787 \
WORKSPACE=aizu-2026 TOKEN=合言葉 node server/connectors/square.js
```

| 変数 | 説明 |
|------|------|
| `SQUARE_ACCESS_TOKEN` | Square のアクセストークン（必須） |
| `SQUARE_API_BASE` | 既定 `https://connect.squareup.com`（sandbox は `https://connect.squareupsandbox.com`） |
| `SQUARE_VERSION` | Square-Version ヘッダ（既定 `2024-01-18`） |

重複防止は最後の `created_at` を `.square_cursor` に保存します。

## PayPal コネクタ（`paypal.js`）

PayPal の取引（Transaction Search API）を取得し、入金（売上）と決済手数料の
2件を投入します（返金・出金は符号で除外）。

```bash
PAYPAL_CLIENT_ID=xxx PAYPAL_SECRET=yyy SYNC_URL=http://localhost:8787 \
WORKSPACE=aizu-2026 TOKEN=合言葉 node server/connectors/paypal.js
```

| 変数 | 説明 |
|------|------|
| `PAYPAL_CLIENT_ID` / `PAYPAL_SECRET` | PayPal アプリの認証情報（必須） |
| `PAYPAL_API_BASE` | 既定 `https://api-m.paypal.com`（sandbox は `https://api-m.sandbox.paypal.com`） |

- OAuth2（client_credentials）でトークンを取得します。
- 金額はJPY前提（主要単位＝円）。`start_date` は過去約3年以内が有効です。
- 重複防止は最後の取引日時を `.paypal_cursor` に保存します。

## Gmail コネクタ（`gmail.js`）

指定した**送信元ルール**に一致するメールを Gmail から取得し、会計アプリの
取込Webhook（`/api/inbox`）へ仕訳データを投入します。開いていない間も
サーバーが定期的にメールを確認して仕訳（または下書き）を作ります。
合同会社アイズでは主に **USSオークションの精算書メール**を対象にしています。

処理の流れ：

1. 送信元ルールに一致するメールを検知
2. **金額を読み取る**：件名・本文 → 無ければ**添付PDFを復号して読み取り**（パスワード対応）
3. 計上方式：
   - 既定は**承認制**（取込画面で人が確認して計上）
   - ルールに `autopost:true` と相手勘定 `counter` を設定した送信元は、
     金額を確実に読めた明細だけ**自動計上**（`auto` フラグ）

```bash
GMAIL_CLIENT_ID=xxx GMAIL_CLIENT_SECRET=yyy GMAIL_REFRESH_TOKEN=zzz \
GMAIL_PDF_PASSWORDS=12345 \
SYNC_URL=http://localhost:8787 WORKSPACE=aizu-2026 TOKEN=合言葉 \
node server/connectors/gmail.js
```

### 認証（Google OAuth2）

1. Google Cloud Console でプロジェクトを作成 → **Gmail API** を有効化。
2. OAuth 同意画面を設定し、スコープ `https://www.googleapis.com/auth/gmail.readonly`
   （読み取り専用）を追加。
3. 「OAuth クライアント ID（デスクトップ）」を作成 → `client_id` / `client_secret` を取得。
4. その認証情報で一度だけ認可フローを実行し、**リフレッシュトークン**を取得。
   （`https://developers.google.com/oauthplayground` でも取得可能）
5. 3つの値を環境変数 `GMAIL_CLIENT_ID` / `GMAIL_CLIENT_SECRET` / `GMAIL_REFRESH_TOKEN`
   に設定します。トークンはサーバー側だけで保持し、ブラウザには置きません。

### 環境変数

| 変数 | 既定 | 説明 |
|------|------|------|
| `GMAIL_CLIENT_ID` / `GMAIL_CLIENT_SECRET` / `GMAIL_REFRESH_TOKEN` | （必須） | Google OAuth2 の認証情報 |
| `SYNC_URL` | `http://localhost:8787` | 同期サーバーのURL |
| `WORKSPACE` / `TOKEN` | （必須/任意） | 会計アプリと同じワークスペース・トークン |
| `GMAIL_RULES` | 下記の既定ルール | 取込ルール（JSON）。上書き可 |
| `GMAIL_PDF_PASSWORDS` | （任意） | 添付PDFのパスワード。カンマ区切りで複数可（例 USS会員番号 `12345`）。上から順に試します |
| `GMAIL_API_BASE` / `GOOGLE_OAUTH_BASE` | Google 本番 | テスト用に差し替え可 |

### 取込ルール（`GMAIL_RULES`）

送信元（`from`）に一致したメールを会計対象とみなし、勘定科目・税区分・
入出金方向を割り当てます。既定は USS 精算書のみ：

```json
[
  { "from": "ussnet.co.jp", "account": "500", "tax": "purchase10", "dir": "out", "label": "USSオークション精算" }
]
```

複数の送信元を並べれば、希望ナンバー・PayPay・その他固定費メールも追加できます（例）：

```json
[
  { "from": "ussnet.co.jp", "account": "500", "tax": "purchase10", "dir": "out",
    "label": "USSオークション精算", "pdfPassword": "12345", "autopost": true, "counter": "110" },
  { "from": "kibou-number@example.jp", "account": "540", "tax": "purchase10", "dir": "out",
    "label": "希望ナンバー手数料" },
  { "from": "paypay.ne.jp", "account": "540", "tax": "purchase10", "dir": "out",
    "label": "PayPay固定費" }
]
```

| フィールド | 必須 | 説明 |
|-----------|------|------|
| `from` | ✓ | 送信元アドレスの部分一致（ドメインでOK） |
| `account` | ✓ | 勘定科目コード（例 `500`＝仕入高、`540`＝支払手数料） |
| `tax` | | 税区分（`purchase10` など） |
| `dir` | | `in`（入金）or `out`（出金） |
| `label` | | 摘要に付く分類名 |
| `pdfPassword` | | この送信元の添付PDFパスワード（`GMAIL_PDF_PASSWORDS` でも可） |
| `autopost` | | `true` かつ `counter` 指定時、金額を確実に読めた明細を自動計上対象にする |
| `counter` | | 自動計上時の相手勘定（支払元の口座 `110` や 未払金 `210` など） |

### 金額の読み取り（本文＋添付PDF）

1. **件名・本文**に `¥1,250,000` や `1,250,000 円` のような通貨表記があれば金額を推定。
   年号（2026）や日付は誤検出しないよう、通貨記号かカンマ区切りを伴う数値だけを候補にします。
2. 本文に金額が無い場合、**添付PDF**を取得し、`GMAIL_PDF_PASSWORDS` / ルールの `pdfPassword`
   （空パスワードも自動で試行）で**復号してテキストから金額を読み取り**ます。
   - 復号は `pdfextract.js`（依存ゼロ・Node標準の crypto/zlib のみ）で実装。
     標準セキュリティハンドラ **RC4（R2/R3）・AESV2（R4）・AESV3（R6）** に対応。
   - ⚠️ スキャン画像だけのPDF、または数字を独自エンコーディングで埋め込むフォントの場合は
     テキストが取れないことがあります。その場合は**金額0の下書き**として投入し、
     取込画面でPDFを確認して金額を入力してください（安全側フォールバック）。
   - USSの精算書パスワードは**会員番号5桁**です。`GMAIL_PDF_PASSWORDS=12345` のように設定します。

### 計上方式（承認制 / 自動計上）

- **既定は承認制**：受信データは取込画面「📥 受信データを取得」で一覧確認 → 人が計上。
- **自動計上**：ルールに `autopost:true` と `counter`（相手勘定）を設定した送信元は、
  **金額を確実に読み取れた明細だけ** `auto` フラグ付きで投入されます。取込画面で
  「受信データを取得」した時点で、その明細は自動で仕訳計上され、残り（金額0や
  `counter` 未設定など）は従来どおり確認待ちの一覧に表示されます。
  - 例）USS精算書を「仕入高500 ／ 普通預金110」で自動計上するには
    `"autopost": true, "counter": "110"`。相手勘定を後日精算にするなら `"counter": "210"`（未払金）。

### 重複防止・定期実行

- 前回取得した最大 `internalDate` を `.gmail_cursor` に保存し、次回はそれ以降のメールのみ取得します。
- cron 例（1時間ごと）：

```cron
0 * * * * cd /path/to/app && GMAIL_CLIENT_ID=xxx GMAIL_CLIENT_SECRET=yyy GMAIL_REFRESH_TOKEN=zzz GMAIL_PDF_PASSWORDS=12345 SYNC_URL=http://localhost:8787 WORKSPACE=aizu-2026 TOKEN=合言葉 node server/connectors/gmail.js >> /var/log/kaikei-gmail.log 2>&1
```

## 共通ライブラリ（`_lib.js` / `pdfextract.js`）

- `_lib.js`：HTTP・金額換算（ゼロデシマル通貨対応）・金額推定（`guessAmount`）・
  カーソル・`/api/inbox` 投入・「売上＋手数料」生成を共通化。各コネクタが利用します。
- `pdfextract.js`：パスワード保護PDFのテキスト抽出（依存ゼロ、Node標準の crypto/zlib のみ）。
  標準セキュリティハンドラの RC4 / AESV2 / AESV3 に対応。Gmail コネクタの添付PDF読取で使用。

## 他サービスのコネクタを作るには

`stripe.js` / `square.js` を雛形に、次の3ステップで実装できます。

1. 対象APIから取引一覧を取得する（`getJson` を利用）
2. 各取引を `{date, description, amount, dir, account, tax}` に変換する
   - `dir`: `'in'`（入金/売上）または `'out'`（出金/経費）
   - `account`: 会計アプリの勘定科目コード、`tax`: 税区分（省略可）
3. `POST {SYNC_URL}/api/inbox` に `{workspace, token, items}` で投入する

Square・PayPal・GMOペイメントゲートウェイ・法人カード（UPSIDER/paild 等）も
同じ形で対応できます。
