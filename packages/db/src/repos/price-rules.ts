import { prisma } from "../index.js";
import type { PriceRule as PriceRuleModel } from "@prisma/client";

export async function getDefaultPriceRule() {
  return prisma.priceRule.findUnique({ where: { id: "default" } });
}

export async function getPriceRule(id: string) {
  return prisma.priceRule.findUnique({ where: { id } });
}

export async function listPriceRules() {
  return prisma.priceRule.findMany({ orderBy: { createdAt: "desc" } });
}

export function toCoreRule(rule: PriceRuleModel) {
  return {
    fxRateToJpy: Number(rule.fxRateToJpy),
    dutyRate: Number(rule.dutyRate),
    intlShippingPerUnit: Number(rule.intlShippingPerUnit),
    domesticShipping: Number(rule.domesticShipping),
    platformFeeRate: Number(rule.platformFeeRate),
    margin: {
      type: rule.marginType as "rate" | "fixed",
      value: Number(rule.marginValue),
    },
    roundTo: rule.roundTo,
    minProfit: Number(rule.minProfit),
  };
}
