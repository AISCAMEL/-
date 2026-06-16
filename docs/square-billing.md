# 決済設計（Square）

AIオペレーター24 の課金は **Square** を採用する（旧: Stripe からの変更）。
本書は実装方針のみを定義する。実装は Square アカウント・APIキー取得後に行う。

## なぜ Square か
- 実店舗の顧客（美容室・飲食・整体など）が**既に Square 端末/アカウントを使っている**ことが多く、親和性が高い。
- 請求書（Square Invoices）・サブスク（Square Subscriptions）・カード決済をAPIで扱える。

## 料金モデル（既存の方針を踏襲）
- 月額固定（Starter/Business/Pro）＋ 通話分数上限 ＋ 超過課金。
- 「月額固定」= Square **Subscriptions**（カタログのプラン）。
- 「超過分」= 当月末に確定 → Square **Invoices** で追加請求、または翌月サブスクに従量加算。
  - MVP案：月次で `usage_records` から超過分を集計し、**Square Invoice を発行**（一番シンプル）。

## 必要な環境変数（`backend/.env.example`）
```
SQUARE_ENV=sandbox            # sandbox / production
SQUARE_ACCESS_TOKEN=
SQUARE_LOCATION_ID=
SQUARE_WEBHOOK_SIGNATURE_KEY=
```
`config.square`（`backend/src/config.ts`）で参照。未設定時は課金機能オフ（現状デモはこの状態）。

## 想定する実装（後続フェーズ）
1. **顧客カード登録**：管理画面に「お支払い方法」ページ → Square Web Payments SDK でカードをトークン化 → `Customers`/`Cards` に保存。
2. **サブスク作成**：契約プランに対応する Square Catalog プランで `Subscriptions` を作成。`tenants` に `square_customer_id` / `square_subscription_id` を保持（要スキーマ追加）。
3. **超過課金**：月初バッチで前月の `usage_records` を集計 → 超過があれば `Invoices` を作成・送付。
4. **Webhook**：`/api/square/webhook` で支払い成功/失敗を受信し、`SQUARE_WEBHOOK_SIGNATURE_KEY` で署名検証 → `tenants.status`（active/suspended）や `notifications` に反映。
5. **管理画面**：支払い状況・次回請求日・履歴を表示。

## スキーマ追加（実装時）
```sql
alter table tenants add column if not exists square_customer_id text;
alter table tenants add column if not exists square_subscription_id text;
-- 既存の usage_records / notifications を請求・通知に再利用
```

## 現状
- ドキュメント・設定の足場のみ（コード未実装）。請求書プレビュー（`/usage/invoice`・PDF）と利用集計（`usage_records`）は実装済みのため、Square連携時はそれらを請求データの基礎に使える。
