import {
  calculateSellPrice,
  diffListing,
  type InventorySnapshot,
  type ListingState,
  type SyncAction,
} from "@hub/core";
import { DEFAULT_PRICE_RULE } from "./listing-service.js";

/** 同期対象の管理出品＋「現在の仕入れ先の実態」。 */
interface ManagedListing {
  externalId: string;
  title: string;
  /** BASE に現在反映されている状態。 */
  listing: ListingState;
  /** いま仕入れ先APIを叩いたら返る値（live では supplier.getInventory）。 */
  supplierNow: { stock: number | null; costCNY: number };
}

/**
 * デモ用シード。実運用では DB の Listing と supplier.getInventory から組み立てる。
 * 在庫切れ / 値上がり / 在庫変動 / 再入荷 / 変化なし / 値下がり の代表ケースを用意。
 */
function seedListings(): ManagedListing[] {
  return [
    // 在庫0 → 自動非公開
    { externalId: "CKB-0001", title: "猫じゃらし 電動 自動回転", listing: { externalId: "CKB-0001", currentStock: 12, currentPrice: 2980, published: true }, supplierNow: { stock: 0, costCNY: 48 } },
    // 値上がり → 価格更新
    { externalId: "CKB-0002", title: "キャットタワー 据え置き 大型", listing: { externalId: "CKB-0002", currentStock: 8, currentPrice: 7980, published: true }, supplierNow: { stock: 8, costCNY: 320 } },
    // 在庫変動 → 在庫更新
    { externalId: "CKB-0003", title: "猫 爪とぎ ダンボール 2個セット", listing: { externalId: "CKB-0003", currentStock: 40, currentPrice: 1800, published: true }, supplierNow: { stock: 15, costCNY: 33 } },
    // 再入荷（非公開→公開）
    { externalId: "CKB-0004", title: "自動給餌器 タイマー式", listing: { externalId: "CKB-0004", currentStock: 0, currentPrice: 5480, published: false }, supplierNow: { stock: 30, costCNY: 150 } },
    // 変化なし（cost 30 → sellPrice 2020）
    { externalId: "CKB-0005", title: "猫 ベッド ふわふわ ドーム型", listing: { externalId: "CKB-0005", currentStock: 50, currentPrice: 2020, published: true }, supplierNow: { stock: 50, costCNY: 30 } },
    // 値下がり → 価格更新
    { externalId: "CKB-0006", title: "猫 トンネル 折りたたみ", listing: { externalId: "CKB-0006", currentStock: 25, currentPrice: 1980, published: true }, supplierNow: { stock: 25, costCNY: 18 } },
  ];
}

export interface SyncRowResult {
  externalId: string;
  title: string;
  supplierStock: number | null;
  oldPrice: number;
  newPrice: number;
  actions: SyncAction[];
}

export interface SyncRunResult {
  ranAt: string;
  results: SyncRowResult[];
  summary: { unpublished: number; republished: number; priceUpdates: number; stockUpdates: number; noChange: number };
}

/** 直近の同期結果（手動・自動どちらも記録）。 */
let lastRun: SyncRunResult | null = null;
export function getLastRun(): SyncRunResult | null {
  return lastRun;
}

/**
 * 在庫・価格同期を1回実行する。
 * 仕入れ先の在庫/価格を取得 → 価格再計算 → 差分アクションを算出。
 * live では各アクションを channel connector に適用し DB と SyncLog に保存する。
 */
export function runSync(): SyncRunResult {
  const summary = { unpublished: 0, republished: 0, priceUpdates: 0, stockUpdates: 0, noChange: 0 };

  const results = seedListings().map((m): SyncRowResult => {
    const snapshot: InventorySnapshot = {
      externalId: m.externalId,
      stock: m.supplierNow.stock,
      cost: m.supplierNow.costCNY,
      costCurrency: "CNY",
      fetchedAt: new Date(),
    };
    const newPrice = calculateSellPrice(
      { cost: m.supplierNow.costCNY, costCurrency: "CNY" },
      DEFAULT_PRICE_RULE,
    ).sellPrice;

    const actions = diffListing({ snapshot, listing: m.listing, recalculatedPrice: newPrice });

    let changed = false;
    for (const a of actions) {
      if (a.type === "unpublish") (summary.unpublished++, (changed = true));
      else if (a.type === "republish") (summary.republished++, (changed = true));
      else if (a.type === "update_price") (summary.priceUpdates++, (changed = true));
      else if (a.type === "update_stock") (summary.stockUpdates++, (changed = true));
    }
    // 在庫0で既に非公開のケースは actions 空 → 変化なし扱い
    if (!changed) summary.noChange++;

    return {
      externalId: m.externalId,
      title: m.title,
      supplierStock: m.supplierNow.stock,
      oldPrice: m.listing.currentPrice,
      newPrice,
      actions,
    };
  });

  lastRun = { ranAt: new Date().toISOString(), results, summary };
  return lastRun;
}
