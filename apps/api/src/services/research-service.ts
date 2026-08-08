import {
  analyzeMarkets,
  buildProfitScenarios,
  computeLandedCost,
  type MarketListing,
  type MarketStats,
  type PriceRule,
  type Profitability,
} from "@hub/core";
import type { MarketResearchConnector, SupplierConnector } from "@hub/connectors";
import { DEFAULT_PRICE_RULE } from "./listing-service.js";

export interface ResearchResult {
  keyword: string;
  /** 仕入れ側の着地原価（JPY）。仕入れ商品を指定した場合のみ。 */
  landedCost: number | null;
  market: {
    byMarket: Record<string, MarketStats>;
    overall: MarketStats;
    listings: MarketListing[];
  };
  /** 売値シナリオごとの利益・利益率・ROI。 */
  scenarios: Profitability[];
}

/**
 * Amazon・楽天で売値を調査し、仕入れ値（着地原価）と突き合わせて
 * 利益・利益率・ROI を算出する。
 */
export async function researchMarket(params: {
  keyword: string;
  markets: MarketResearchConnector[];
  limit?: number;
  /** 仕入れ原価の取得元（任意）。指定すると利益計算まで行う。 */
  supplier?: { connector: SupplierConnector; externalId: string };
  rule?: PriceRule;
}): Promise<ResearchResult> {
  const rule = params.rule ?? DEFAULT_PRICE_RULE;

  // 1) 市場の売値を収集（1つの市場が失敗しても他の結果を使う）
  const results = await Promise.allSettled(
    params.markets.map((m) => m.searchListings({ keyword: params.keyword, limit: params.limit })),
  );
  for (const r of results) {
    if (r.status === "rejected") console.warn(`[research] market error: ${r.reason}`);
  }
  const listings = results
    .filter((r): r is PromiseFulfilledResult<MarketListing[]> => r.status === "fulfilled")
    .flatMap((r) => r.value);
  const { byMarket, overall } = analyzeMarkets(listings);

  // 2) 仕入れ値から着地原価を算出（指定があれば）
  let landedCost: number | null = null;
  if (params.supplier) {
    const product = await params.supplier.connector.getProduct(params.supplier.externalId);
    landedCost = Math.round(
      computeLandedCost({ cost: product.cost, costCurrency: product.costCurrency }, rule),
    );
  }

  // 3) 売値シナリオごとの採算
  const scenarios =
    landedCost !== null ? buildProfitScenarios(overall, landedCost, rule.platformFeeRate) : [];

  return {
    keyword: params.keyword,
    landedCost,
    market: { byMarket, overall, listings },
    scenarios,
  };
}
