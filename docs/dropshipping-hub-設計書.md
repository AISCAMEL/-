# 中国輸入 無在庫販売 ハブシステム アーキテクチャ設計書

**バージョン:** 0.1（初版・骨組み）
**最終更新:** 2026-06-12
**対象:** BASE（販売） × Alibaba.com / THE CKB（仕入れ）を連携する自動化ハブ

---

## 1. 目的とゴール

中国輸入の **無在庫販売（ドロップシッピング）** を自動化する中核システム（Hub）を構築する。

- **販売チャネル:** BASE（将来的に Shopify / 楽天 等へ拡張可能な設計）
- **仕入れ先:** Alibaba.com、THE CKB（1688/タオバオを日本向けに集約した卸プラットフォーム）
- **コンセプト:** 仕入れ→出品→価格/在庫同期→受注→自動発注→追跡 までを一元管理し、無在庫で運用する

### ゴール（自動化対象）

| # | 業務 | 自動化内容 |
|---|------|-----------|
| 1 | 商品リサーチ・取り込み | 仕入れ先APIから商品情報（画像/価格/在庫/SKU）を取得 |
| 2 | 価格計算 | 為替・送料・各種手数料・利益率を加味した販売価格を自動算出 |
| 3 | 出品 | BASE へ商品を自動登録／更新 |
| 4 | 在庫・価格同期 | 仕入れ先の在庫・価格を定期監視し、BASE 側を自動更新（欠品時は自動非公開） |
| 5 | 受注処理 | BASE の注文を取り込み、仕入れ先へ自動発注（無在庫フロー） |
| 6 | 物流追跡 | 追跡番号を取り込み、顧客へ連携 |
| 7 | 損益管理 | 仕入原価・売上・手数料・利益をダッシュボードで可視化 |

---

## 2. 重要な前提・リスク（必読）

> 設計はすべて **公式API中心・規約準拠** を原則とする。スクレイピングや非公式手段は採用しない。

| 項目 | 状況 | 対応方針 |
|------|------|---------|
| **BASE API** | 公式の Developers API あり（OAuth2.0） | 正規連携。商品/注文/在庫エンドポイントを利用 |
| **Alibaba.com Open Platform** | 公式 API あり（要審査・アプリ申請） | 正規連携。審査前はモック実装で開発を進める |
| **THE CKB** | API / CSV 連携あり（要契約・キー発行） | 正規連携。仕様確定までは Connector インターフェースで抽象化 |
| **無在庫転売の規約** | BASE・仕入れ先の規約に抵触する場合がある | 出品前チェック、禁止カテゴリ・ブランド品フィルタを実装 |
| **法令** | 関税・輸入規制・景表法・特商法・PSE/技適等 | 出品時バリデーションでガード（後述 §9） |

**※ API の正式な利用権限（アプリ審査・契約・キー発行）が前提。** 取得前はモック/スタブで開発・テストを進められる設計とする。

---

## 3. システム全体構成

```
                ┌──────────────────────────────────────────┐
                │        管理ダッシュボード (apps/web)        │
                │  商品一覧 / 在庫・価格 / 受注 / 損益 / 設定  │
                └───────────────────┬──────────────────────┘
                                    │ REST / 認証
                ┌───────────────────▼──────────────────────┐
                │            Hub API (apps/api)             │
                │  products / orders / suppliers / sync     │
                └───┬─────────────┬──────────────┬─────────┘
                    │             │              │
          ┌─────────▼───┐  ┌──────▼──────┐  ┌────▼────────┐
          │ 価格エンジン │  │ 同期エンジン │  │ 受注エンジン │   (packages/core)
          │  pricing    │  │   sync      │  │   ordering  │
          └─────────────┘  └─────────────┘  └─────────────┘
                    │             │              │
                ┌───▼─────────────▼──────────────▼───┐
                │       Connector 層 (packages/connectors)
                │  ┌──────────┐ ┌─────────┐ ┌────────┐ │
                │  │  BASE    │ │ Alibaba │ │ THE CKB│ │
                │  │ (販売)   │ │ (仕入)  │ │ (仕入) │ │
                │  └──────────┘ └─────────┘ └────────┘ │
                └────────────────────┬───────────────┘
                                     │
                          ┌──────────▼──────────┐
                          │   DB (packages/db)   │
                          │   PostgreSQL/Prisma  │
                          └──────────┬──────────┘
                                     │
                          ┌──────────▼──────────┐
                          │  ジョブ基盤 (queue)  │
                          │  定期同期/再試行     │
                          └─────────────────────┘
```

### レイヤ責務

- **apps/web** — 運用者向けダッシュボード（Next.js）。
- **apps/api** — Hub API。外部公開する自作 REST API。認証・オーケストレーション担当。
- **packages/core** — ドメインモデルとビジネスロジック（価格・同期・受注）。外部I/Oを持たない純粋ロジック。
- **packages/connectors** — 外部プラットフォーム差異を吸収する変換層。共通インターフェースに準拠。
- **packages/db** — Prisma スキーマとリポジトリ。

---

## 4. Connector 抽象化（拡張性の核）

仕入れ先・販売先を増やしても中核を変えないため、共通インターフェースで抽象化する。

```ts
// 仕入れ先（Alibaba / THE CKB が実装）
interface SupplierConnector {
  readonly id: SupplierId;
  searchProducts(query): Promise<SupplierProduct[]>;
  getProduct(externalId): Promise<SupplierProduct>;
  getInventory(externalId): Promise<InventorySnapshot>;
  placeOrder(order): Promise<SupplierOrderResult>;   // 自動発注
  getShipment(supplierOrderId): Promise<ShipmentInfo>;
}

// 販売チャネル（BASE が実装、将来 Shopify 等）
interface SalesChannelConnector {
  readonly id: ChannelId;
  upsertListing(listing): Promise<ChannelListing>;   // 出品/更新
  updateInventory(listingId, qty): Promise<void>;
  updatePrice(listingId, price): Promise<void>;
  setPublished(listingId, published): Promise<void>; // 欠品時の非公開
  fetchOrders(since): Promise<ChannelOrder[]>;
  pushTracking(orderId, tracking): Promise<void>;
}
```

→ 新規プラットフォーム対応 = 新しい Connector を1つ実装するだけ。

---

## 5. データモデル（概要）

詳細は `packages/db/prisma/schema.prisma` を参照。主要エンティティ：

| エンティティ | 役割 |
|------------|------|
| `Supplier` | 仕入れ先マスタ（alibaba / theckb） |
| `SalesChannel` | 販売チャネルマスタ（base） |
| `SourceProduct` | 仕入れ先の商品（原価・SKU・在庫の元データ） |
| `Product` | 自社管理の正規化商品（販売価格・出品状態） |
| `Listing` | チャネルごとの出品（BASE の item_id 等を保持） |
| `Order` | 受注（BASE 注文） |
| `OrderItem` | 受注明細 |
| `SupplierOrder` | 仕入れ先への自動発注 |
| `PriceRule` | 価格計算ルール（為替・利益率・手数料） |
| `SyncLog` | 同期・自動化の実行ログ |

---

## 6. 自動化フロー

### 6.1 出品フロー
```
仕入れ商品取得(Supplier) → 価格計算(PriceRule) → 規約/法令バリデーション
→ Product 正規化 → BASE 出品(Listing) → 状態保存
```

### 6.2 在庫・価格同期（定期ジョブ・例: 1時間毎）
```
各 SourceProduct の在庫/価格を取得
→ 変化検知
  ├ 在庫0   → BASE 自動非公開
  ├ 価格変動 → PriceRule 再計算 → BASE 価格更新
  └ 在庫復活 → BASE 再公開
→ SyncLog 記録
```

### 6.3 受注 → 自動発注フロー（無在庫の核）
```
BASE 新規注文を取り込み(Order)
→ 利益・在庫の最終チェック
→ 仕入れ先へ自動発注(SupplierOrder)   ← 任意で手動承認ステップ可
→ 追跡番号取得 → BASE/顧客へ連携
→ 損益確定
```

---

## 7. 価格計算エンジン

販売価格 = 原価系コスト ＋ 利益、を構成可能なルールで算出。

```
販売価格 = ROUND_UP(
   (仕入原価_元 × 為替レート × (1 + 関税率))
 + 国際送料配賦
 + 国内送料
 + プラットフォーム手数料率での割戻し
 + 目標利益（率 or 固定）
)
```

- 為替レートは日次取得（外部レートAPI、`PriceRule` にバッファ込みで保持）。
- 端数処理・最低利益ガード・上限価格を設定可能。
- ルールは商品/カテゴリ/チャネル単位で上書き可能。

---

## 8. API エンドポイント（Hub API・抜粋）

| メソッド | パス | 説明 |
|---------|------|------|
| `GET` | `/health` | ヘルスチェック |
| `GET` | `/suppliers` | 仕入れ先一覧 |
| `GET` | `/suppliers/:id/products` | 仕入れ商品検索 |
| `POST` | `/products/import` | 仕入れ商品を取り込み・正規化 |
| `GET` | `/products` | 自社商品一覧 |
| `POST` | `/products/:id/publish` | BASE へ出品 |
| `POST` | `/sync/run` | 在庫・価格同期を手動実行 |
| `GET` | `/orders` | 受注一覧 |
| `POST` | `/orders/:id/fulfill` | 仕入れ先へ自動発注 |
| `GET` | `/dashboard/pnl` | 損益サマリ |

---

## 9. 規約・法令バリデーション（出品前チェック）

出品をブロックする条件の例（`packages/core` で実装）：

- ブランド品・偽ブランドの疑い（キーワード/ブランド辞書）
- 輸入規制・禁止品目（医薬品・食品・電池単体・武器類 等）
- 技適/PSE が必要な電気製品（無認証品の出品防止）
- 景表法・特商法に反する表記
- 各プラットフォームの禁止カテゴリ

---

## 10. 技術スタック

| 領域 | 採用 |
|------|------|
| 言語 | TypeScript |
| パッケージ管理 | pnpm workspace（モノレポ） |
| Hub API | Fastify |
| ダッシュボード | Next.js（App Router） |
| DB | PostgreSQL ＋ Prisma |
| ジョブ/キュー | BullMQ（Redis）想定 |
| バリデーション | Zod |
| テスト | Vitest |

---

## 11. ディレクトリ構成

```
.
├── apps/
│   ├── api/          Hub API（Fastify）
│   └── web/          ダッシュボード（Next.js・骨組み）
├── packages/
│   ├── core/         ドメイン/価格/同期ロジック
│   ├── connectors/   BASE / Alibaba / THE CKB コネクタ
│   └── db/           Prisma スキーマ
└── docs/             設計書
```

---

## 12. ロードマップ

| フェーズ | 内容 | 状態 |
|---------|------|------|
| 0 | 設計・骨組み（本リポジトリ） | ✅ 本コミット |
| 1 | DB マイグレーション・基本CRUD・モック Connector でE2E | 次段階 |
| 2 | BASE 正規連携（OAuth・出品・受注取込） | API審査後 |
| 3 | Alibaba / THE CKB 正規連携（商品取得・自動発注） | 契約・審査後 |
| 4 | 価格/在庫の定期同期ジョブ、損益ダッシュボード | |
| 5 | 規約/法令バリデーション強化・運用監視 | |

---

## 13. 環境変数（`.env.example` 参照）

API キー類はすべて環境変数で管理し、リポジトリにコミットしない。
