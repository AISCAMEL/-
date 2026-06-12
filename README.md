# Dropshipping Hub — 中国輸入 無在庫販売 ハブシステム

BASE（販売）× Alibaba.com / THE CKB（仕入れ）を連携し、中国輸入の**無在庫ドロップシッピング**を自動化する中核システム（モノレポ・骨組み）。

> 📄 設計の全体像は [`docs/dropshipping-hub-設計書.md`](docs/dropshipping-hub-設計書.md) を参照。

## できること（自動化の対象）

- 仕入れ先（Alibaba / THE CKB）から商品取得 → BASE へ自動出品
- 為替・送料・手数料・利益を加味した**価格自動計算**
- 在庫・価格の定期同期（欠品時の**自動非公開**）
- BASE 受注 → 仕入れ先へ**自動発注**（無在庫フロー）
- 規約・法令の**出品前バリデーション**
- 損益の可視化

## 構成（モノレポ）

```
apps/
  api/         Hub API（Fastify）— products / suppliers / publish
  web/         ダッシュボード（Next.js・骨組み）
packages/
  core/        ドメイン / 価格計算 / 同期 / 規約チェック（純粋ロジック）
  connectors/  BASE / Alibaba / THE CKB コネクタ（mock | live 切替）
  db/          Prisma スキーマ（PostgreSQL）
docs/          設計書
```

## はじめに

```bash
pnpm install
cp .env.example .env          # API キー等を設定（未設定でも mock で動作）

# Hub API（mock モードで起動）
pnpm dev:api                  # http://localhost:3001/health

# ダッシュボード
pnpm dev:web                  # http://localhost:3000

# テスト / 型チェック
pnpm test
pnpm typecheck
```

### mock / live モード

`.env` の `CONNECTOR_MODE` で切り替え。

- `mock`（既定）: 外部APIを叩かず決定的なダミーで動作。設計・開発・テスト用。
- `live`: 実API連携。各プラットフォームの**審査・契約・キー発行が前提**（取得後にコネクタの live 分岐を実装）。

## API クイック例（mock）

```bash
# 市場調査（Amazon・楽天で売値を調べ、仕入れ値と突き合わせて利益率/ROIを算出）
curl -X POST localhost:3001/research \
  -H 'content-type: application/json' \
  -d '{"keyword":"ワイヤレスイヤホン","markets":["amazon","rakuten"],"supplierId":"theckb","externalId":"CKB-0001"}'
#  → 市場の min/median/max ＋「市場中央値/最安値/最安値-5%」での利益・利益率・ROI

# 仕入れ商品の取り込み（価格計算＋規約チェック）
curl -X POST localhost:3001/products/import \
  -H 'content-type: application/json' \
  -d '{"supplierId":"theckb","externalId":"CKB-0001"}'

# BASE へ出品（mock）
curl -X POST localhost:3001/products/publish \
  -H 'content-type: application/json' \
  -d '{"supplierId":"theckb","externalId":"CKB-0001","channelId":"base"}'
```

## 重要な前提・注意

- 本リポジトリは**設計＋骨組み（フェーズ0）**。実API連携は審査・契約後に実装。
- 無在庫転売は各プラットフォーム/法令の規約に抵触する場合があるため、**公式API中心・規約準拠**を原則とする（`packages/core/compliance`）。
- ロードマップは設計書 §12 を参照。
