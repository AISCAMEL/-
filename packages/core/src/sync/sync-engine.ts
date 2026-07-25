import type { InventorySnapshot } from "../domain/product.js";

/** 同期前の出品状態（自社が把握している現状）。 */
export interface ListingState {
  externalId: string;
  /** 現在 BASE に反映されている在庫数。 */
  currentStock: number;
  /** 現在 BASE に反映されている販売価格（JPY）。 */
  currentPrice: number;
  published: boolean;
}

/** 同期で実行すべきアクション。 */
export type SyncAction =
  | { type: "unpublish"; externalId: string; reason: "out_of_stock" }
  | { type: "republish"; externalId: string }
  | { type: "update_stock"; externalId: string; stock: number }
  | { type: "update_price"; externalId: string; price: number }
  | { type: "noop"; externalId: string };

export interface SyncContext {
  /** 仕入れ先の最新在庫。 */
  snapshot: InventorySnapshot;
  /** 自社が把握している現状の出品。 */
  listing: ListingState;
  /** 再計算した推奨販売価格（JPY）。 */
  recalculatedPrice: number;
}

/**
 * 在庫・価格の差分から実行アクションを決定する純粋関数。
 * - 在庫0 → 非公開
 * - 在庫復活 → 再公開
 * - 在庫変化 → 在庫更新
 * - 価格変化 → 価格更新
 */
export function diffListing(ctx: SyncContext): SyncAction[] {
  const { snapshot, listing, recalculatedPrice } = ctx;
  const actions: SyncAction[] = [];
  const stock = snapshot.stock ?? 0;

  if (stock <= 0) {
    if (listing.published) {
      actions.push({ type: "unpublish", externalId: snapshot.externalId, reason: "out_of_stock" });
    }
    return actions;
  }

  if (!listing.published) {
    actions.push({ type: "republish", externalId: snapshot.externalId });
  }
  if (stock !== listing.currentStock) {
    actions.push({ type: "update_stock", externalId: snapshot.externalId, stock });
  }
  if (recalculatedPrice !== listing.currentPrice) {
    actions.push({ type: "update_price", externalId: snapshot.externalId, price: recalculatedPrice });
  }

  if (actions.length === 0) {
    actions.push({ type: "noop", externalId: snapshot.externalId });
  }
  return actions;
}
