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

## 他サービスのコネクタを作るには

`stripe.js` を雛形に、次の3ステップで実装できます。

1. 対象APIから取引一覧を取得する（`getJson` を利用）
2. 各取引を `{date, description, amount, dir, account, tax}` に変換する
   - `dir`: `'in'`（入金/売上）または `'out'`（出金/経費）
   - `account`: 会計アプリの勘定科目コード、`tax`: 税区分（省略可）
3. `POST {SYNC_URL}/api/inbox` に `{workspace, token, items}` で投入する

Square・PayPal・GMOペイメントゲートウェイ・法人カード（UPSIDER/paild 等）も
同じ形で対応できます。
