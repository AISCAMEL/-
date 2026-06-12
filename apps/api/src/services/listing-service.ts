import {
  calculateSellPrice,
  canPublish,
  validateForListing,
  type PriceRule,
  type SupplierProduct,
} from "@hub/core";
import type { SalesChannelConnector, SupplierConnector } from "@hub/connectors";

/** デモ用の既定価格ルール（本番は DB の PriceRule から取得）。 */
export const DEFAULT_PRICE_RULE: PriceRule = {
  fxRateToJpy: 21,
  dutyRate: 0.1,
  intlShippingPerUnit: 300,
  domesticShipping: 500,
  platformFeeRate: 0.036,
  margin: { type: "rate", value: 0.3 },
  roundTo: 10,
  minProfit: 300,
};

export interface ImportResult {
  product: SupplierProduct;
  sellPrice: number;
  profit: number;
  publishable: boolean;
  issues: ReturnType<typeof validateForListing>;
}

/** 仕入れ商品を取り込み、価格計算と規約チェックを行う。 */
export async function importProduct(
  supplier: SupplierConnector,
  externalId: string,
  rule: PriceRule = DEFAULT_PRICE_RULE,
): Promise<ImportResult> {
  const product = await supplier.getProduct(externalId);
  const price = calculateSellPrice({ cost: product.cost, costCurrency: product.costCurrency }, rule);
  const issues = validateForListing(product);

  return {
    product,
    sellPrice: price.sellPrice,
    profit: price.profit,
    publishable: canPublish(issues) && price.meetsMinProfit,
    issues,
  };
}

/** 取り込んだ商品を販売チャネルへ出品する。 */
export async function publishToChannel(
  channel: SalesChannelConnector,
  result: ImportResult,
) {
  if (!result.publishable) {
    throw new Error(
      `出品不可: ${result.issues.map((i) => `${i.severity}:${i.code}`).join(", ") || "最低利益未達"}`,
    );
  }
  return channel.upsertListing({
    productKey: `${result.product.supplierId}:${result.product.externalId}`,
    title: result.product.title,
    description: result.product.description,
    imageUrls: result.product.imageUrls,
    price: result.sellPrice,
    stock: result.product.stock ?? 0,
  });
}
