import { z } from "zod";
import type { Currency, SupplierId } from "./ids.js";

/** 仕入れ先から取得した生の商品（コネクタが返す形）。 */
export const SupplierProductSchema = z.object({
  supplierId: z.custom<SupplierId>(),
  externalId: z.string(),
  title: z.string(),
  description: z.string().optional(),
  imageUrls: z.array(z.string().url()),
  /** 仕入原価（仕入れ先の通貨建て）。 */
  cost: z.number().nonnegative(),
  costCurrency: z.custom<Currency>(),
  /** 在庫数（不明な場合は null）。 */
  stock: z.number().int().nonnegative().nullable(),
  skus: z
    .array(
      z.object({
        externalSkuId: z.string(),
        attributes: z.record(z.string()),
        cost: z.number().nonnegative(),
        stock: z.number().int().nonnegative().nullable(),
      }),
    )
    .default([]),
  sourceUrl: z.string().url().optional(),
});

export type SupplierProduct = z.infer<typeof SupplierProductSchema>;

/** 在庫スナップショット（同期用の軽量データ）。 */
export interface InventorySnapshot {
  externalId: string;
  stock: number | null;
  cost: number;
  costCurrency: Currency;
  fetchedAt: Date;
}
