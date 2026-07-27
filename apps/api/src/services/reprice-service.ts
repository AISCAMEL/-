import { calculateSellPrice } from "@hub/core";
import { dbEnabled, prisma, priceRuleRepo } from "@hub/db";
import { fetchCnyToJpy } from "./fx-service.js";
import { pushAlert } from "./alert-service.js";

interface RepriceResult {
  total: number;
  updated: number;
  skipped: number;
  fxRate: number;
  details: { id: string; title: string; oldPrice: number; newPrice: number }[];
}

export async function repriceAll(): Promise<RepriceResult> {
  if (!dbEnabled) {
    return { total: 0, updated: 0, skipped: 0, fxRate: 0, details: [] };
  }

  const rate = await fetchCnyToJpy();

  const dbRule = await priceRuleRepo.getDefaultPriceRule();
  if (!dbRule) {
    return { total: 0, updated: 0, skipped: 0, fxRate: rate, details: [] };
  }

  const rule = {
    fxRateToJpy: rate,
    dutyRate: Number(dbRule.dutyRate),
    intlShippingPerUnit: Number(dbRule.intlShippingPerUnit),
    domesticShipping: Number(dbRule.domesticShipping),
    platformFeeRate: Number(dbRule.platformFeeRate),
    margin: { type: dbRule.marginType as "rate" | "fixed", value: Number(dbRule.marginValue) },
    roundTo: dbRule.roundTo,
    minProfit: Number(dbRule.minProfit),
  };

  const products = await prisma.product.findMany({
    include: { sourceProduct: true },
  });

  const details: RepriceResult["details"] = [];
  let updated = 0;
  let skipped = 0;

  for (const p of products) {
    const cost = Number(p.sourceProduct.cost);
    const currency = p.sourceProduct.costCurrency as "CNY" | "USD" | "JPY";
    const result = calculateSellPrice({ cost, costCurrency: currency }, rule);
    const oldPrice = Number(p.sellPrice);
    const newPrice = result.sellPrice;

    if (Math.abs(oldPrice - newPrice) < 1) {
      skipped++;
      continue;
    }

    await prisma.product.update({
      where: { id: p.id },
      data: { sellPrice: newPrice },
    });

    details.push({ id: p.id, title: p.title, oldPrice, newPrice });
    updated++;
  }

  if (updated > 0) {
    pushAlert({
      type: "reprice",
      title: "自動価格改定",
      message: `為替 ¥${rate.toFixed(2)}/CNY で ${updated}件の売値を更新しました`,
      severity: "info",
    });
  }

  return { total: products.length, updated, skipped, fxRate: rate, details };
}
