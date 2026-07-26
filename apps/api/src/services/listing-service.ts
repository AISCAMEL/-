import {
  calculateSellPrice,
  canPublish,
  validateForListing,
  type PriceRule,
  type SupplierProduct,
} from "@hub/core";
import type { SalesChannelConnector, SupplierConnector } from "@hub/connectors";
import { productRepo, listingRepo, priceRuleRepo } from "@hub/db";

/** DB の PriceRule が無い場合のフォールバック。 */
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

async function loadPriceRule(): Promise<PriceRule> {
  const dbRule = await priceRuleRepo.getDefaultPriceRule();
  return dbRule ? priceRuleRepo.toCoreRule(dbRule) : DEFAULT_PRICE_RULE;
}

export interface ImportResult {
  product: SupplierProduct;
  sellPrice: number;
  profit: number;
  publishable: boolean;
  issues: ReturnType<typeof validateForListing>;
}

export async function importProduct(
  supplier: SupplierConnector,
  externalId: string,
  rule?: PriceRule,
): Promise<ImportResult> {
  const effectiveRule = rule ?? (await loadPriceRule());
  const product = await supplier.getProduct(externalId);
  const price = calculateSellPrice({ cost: product.cost, costCurrency: product.costCurrency }, effectiveRule);
  const issues = validateForListing(product);

  await productRepo.importProduct({
    supplierKind: product.supplierId,
    externalId: product.externalId,
    title: product.title,
    description: product.description,
    imageUrls: product.imageUrls,
    costCNY: product.cost,
    stock: product.stock ?? undefined,
    sellPrice: price.sellPrice,
  });

  return {
    product,
    sellPrice: price.sellPrice,
    profit: price.profit,
    publishable: canPublish(issues) && price.meetsMinProfit,
    issues,
  };
}

export async function publishToChannel(
  channel: SalesChannelConnector,
  result: ImportResult,
) {
  if (!result.publishable) {
    throw new Error(
      `出品不可: ${result.issues.map((i) => `${i.severity}:${i.code}`).join(", ") || "最低利益未達"}`,
    );
  }

  const listing = await channel.upsertListing({
    productKey: `${result.product.supplierId}:${result.product.externalId}`,
    title: result.product.title,
    description: result.product.description,
    imageUrls: result.product.imageUrls,
    price: result.sellPrice,
    stock: result.product.stock ?? 0,
  });

  const dbProducts = await productRepo.listProducts({ take: 1 });
  const dbProduct = dbProducts.items.find(
    (p) =>
      p.sourceProduct.externalId === result.product.externalId &&
      p.sourceProduct.supplier.kind === result.product.supplierId,
  );
  if (dbProduct) {
    await listingRepo.upsertListing({
      productId: dbProduct.id,
      channelKind: channel.id,
      externalListingId: listing.externalListingId,
      publishedPrice: result.sellPrice,
      publishedStock: result.product.stock ?? 0,
    });
  }

  return listing;
}
