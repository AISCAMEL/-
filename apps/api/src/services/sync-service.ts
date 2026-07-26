import {
  calculateSellPrice,
  diffListing,
  type InventorySnapshot,
  type ListingState,
  type SyncAction,
} from "@hub/core";
import { dbEnabled, listingRepo, priceRuleRepo, syncLogRepo } from "@hub/db";
import { DEFAULT_PRICE_RULE } from "./listing-service.js";
import { pushAlert } from "./alert-service.js";

interface ManagedListing {
  externalId: string;
  title: string;
  listing: ListingState;
  supplierNow: { stock: number | null; costCNY: number };
}

function seedListings(): ManagedListing[] {
  return [
    { externalId: "CKB-0001", title: "猫じゃらし 電動 自動回転", listing: { externalId: "CKB-0001", currentStock: 12, currentPrice: 2980, published: true }, supplierNow: { stock: 0, costCNY: 48 } },
    { externalId: "CKB-0002", title: "キャットタワー 据え置き 大型", listing: { externalId: "CKB-0002", currentStock: 8, currentPrice: 7980, published: true }, supplierNow: { stock: 8, costCNY: 320 } },
    { externalId: "CKB-0003", title: "猫 爪とぎ ダンボール 2個セット", listing: { externalId: "CKB-0003", currentStock: 40, currentPrice: 1800, published: true }, supplierNow: { stock: 15, costCNY: 33 } },
    { externalId: "CKB-0004", title: "自動給餌器 タイマー式", listing: { externalId: "CKB-0004", currentStock: 0, currentPrice: 5480, published: false }, supplierNow: { stock: 30, costCNY: 150 } },
    { externalId: "CKB-0005", title: "猫 ベッド ふわふわ ドーム型", listing: { externalId: "CKB-0005", currentStock: 50, currentPrice: 2020, published: true }, supplierNow: { stock: 50, costCNY: 30 } },
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

let lastRun: SyncRunResult | null = null;
export function getLastRun(): SyncRunResult | null {
  return lastRun;
}

export async function runSync(): Promise<SyncRunResult> {
  const rule = dbEnabled
    ? await priceRuleRepo.getDefaultPriceRule().then((r) => (r ? priceRuleRepo.toCoreRule(r) : DEFAULT_PRICE_RULE))
    : DEFAULT_PRICE_RULE;

  const dbListings = dbEnabled ? await listingRepo.listListingsForSync() : [];

  let managed: ManagedListing[];
  if (dbListings.length > 0) {
    managed = dbListings.map((l) => ({
      externalId: l.product.sourceProduct.externalId,
      title: l.product.title,
      listing: {
        externalId: l.product.sourceProduct.externalId,
        currentStock: l.publishedStock ?? 0,
        currentPrice: Number(l.publishedPrice ?? 0),
        published: l.status === "published",
      },
      supplierNow: {
        stock: l.product.sourceProduct.stock,
        costCNY: Number(l.product.sourceProduct.cost),
      },
    }));
  } else {
    managed = seedListings();
  }

  const summary = { unpublished: 0, republished: 0, priceUpdates: 0, stockUpdates: 0, noChange: 0 };

  const results = managed.map((m): SyncRowResult => {
    const snapshot: InventorySnapshot = {
      externalId: m.externalId,
      stock: m.supplierNow.stock,
      cost: m.supplierNow.costCNY,
      costCurrency: "CNY",
      fetchedAt: new Date(),
    };
    const newPrice = calculateSellPrice(
      { cost: m.supplierNow.costCNY, costCurrency: "CNY" },
      rule,
    ).sellPrice;

    const actions = diffListing({ snapshot, listing: m.listing, recalculatedPrice: newPrice });

    let changed = false;
    for (const a of actions) {
      if (a.type === "unpublish") {
        summary.unpublished++;
        changed = true;
        pushAlert({
          type: "out_of_stock",
          title: "在庫切れ",
          message: `「${m.title}」(${m.externalId}) の在庫が 0 になったため非公開にしました`,
          severity: "warning",
        });
      } else if (a.type === "republish") (summary.republished++, (changed = true));
      else if (a.type === "update_price") (summary.priceUpdates++, (changed = true));
      else if (a.type === "update_stock") (summary.stockUpdates++, (changed = true));
    }
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

  if (dbEnabled) await syncLogRepo.writeSyncLog({
    kind: "inventory",
    action: "sync_run",
    success: true,
    message: `${results.length} listings synced: ${summary.unpublished} unpub, ${summary.republished} repub, ${summary.priceUpdates} price, ${summary.stockUpdates} stock`,
  });

  return lastRun;
}
