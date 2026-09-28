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
取込Webhook（`/api/inbox`）へ**下書き仕訳**を投入します。開いていない間も
サーバーが定期的にメールを確認して下書きを作り、アプリの取込画面で
確認・計上する運用（**承認制**）です。合同会社アイズでは主に
**USSオークションの精算書メール**を対象にしています。

```bash
GMAIL_CLIENT_ID=xxx GMAIL_CLIENT_SECRET=yyy GMAIL_REFRESH_TOKEN=zzz \
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
| `GMAIL_API_BASE` / `GOOGLE_OAUTH_BASE` | Google 本番 | テスト用に差し替え可 |

### 取込ルール（`GMAIL_RULES`）

送信元（`from`）に一致したメールを会計対象とみなし、勘定科目・税区分・
入出金方向を割り当てます。既定は USS 精算書のみ：

```json
[
  { "from": "ussnet.co.jp", "account": "500", "tax": "purchase10", "dir": "out", "label": "USSオークション精算" }
]
```

- `from`：送信元アドレスの部分一致（ドメインでOK）
- `account`：勘定科目コード（例 `500`＝仕入高）／`tax`：税区分／`dir`：`in`（入金）or `out`（出金）
- `label`：摘要に付く分類名
- 複数ルールを並べれば、希望ナンバー・PayPay・その他固定費メールも追加できます。

### 金額の推定と承認制

- 件名・スニペットに **`¥1,250,000` や `1,250,000 円`** のような通貨表記があれば金額を推定します。
  年号（2026）や日付は誤検出しないよう、通貨記号かカンマ区切りを伴う数値だけを候補にします。
- **USS の精算書は金額が添付PDF内**にあることが多く、その場合は**金額0の下書き**として投入します。
  取込画面でPDFを確認して金額を入力してください。
- ⚠️ **USSの精算書PDFはパスワード保護**（会員番号5桁）されている場合があります。
  自動でのPDF金額読み取り（OCR）を行うには、別途パスワードの受け渡し設定が必要です。
  現状は「メールを検知 → 日付・件名・科目つきの下書きを自動作成 → 人が金額を確認して計上」までを自動化します。

### 重複防止・定期実行

- 前回取得した最大 `internalDate` を `.gmail_cursor` に保存し、次回はそれ以降のメールのみ取得します。
- cron 例（1時間ごと）：

```cron
0 * * * * cd /path/to/app && GMAIL_CLIENT_ID=xxx GMAIL_CLIENT_SECRET=yyy GMAIL_REFRESH_TOKEN=zzz SYNC_URL=http://localhost:8787 WORKSPACE=aizu-2026 TOKEN=合言葉 node server/connectors/gmail.js >> /var/log/kaikei-gmail.log 2>&1
```

## 共通ライブラリ（`_lib.js`）

HTTP・金額換算（ゼロデシマル通貨対応）・カーソル・`/api/inbox` 投入・
「売上＋手数料」生成を共通化しています。各コネクタはこれを利用します。

## 他サービスのコネクタを作るには

`stripe.js` / `square.js` を雛形に、次の3ステップで実装できます。

1. 対象APIから取引一覧を取得する（`getJson` を利用）
2. 各取引を `{date, description, amount, dir, account, tax}` に変換する
   - `dir`: `'in'`（入金/売上）または `'out'`（出金/経費）
   - `account`: 会計アプリの勘定科目コード、`tax`: 税区分（省略可）
3. `POST {SYNC_URL}/api/inbox` に `{workspace, token, items}` で投入する

Square・PayPal・GMOペイメントゲートウェイ・法人カード（UPSIDER/paild 等）も
同じ形で対応できます。
