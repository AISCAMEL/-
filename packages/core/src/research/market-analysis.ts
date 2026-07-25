/** 市場（売値の調査先）。 */
export type MarketId = "amazon" | "rakuten" | "yahoo" | "ebay";

/** 市場で見つかった1件の出品（売値）。 */
export interface MarketListing {
  marketId: MarketId;
  title: string;
  /** 売値（JPY・送料込みの実売価格を想定）。 */
  price: number;
  url?: string;
  reviewCount?: number;
  rating?: number;
}

/** 市場価格の統計。 */
export interface MarketStats {
  scope: MarketId | "all";
  sampleCount: number;
  min: number;
  max: number;
  median: number;
  average: number;
}

const median = (sorted: number[]): number => {
  if (sorted.length === 0) return 0;
  const mid = Math.floor(sorted.length / 2);
  return sorted.length % 2 === 0 ? (sorted[mid - 1]! + sorted[mid]!) / 2 : sorted[mid]!;
};

/** 出品群から市場統計を算出する。 */
export function summarize(listings: MarketListing[], scope: MarketStats["scope"]): MarketStats {
  const prices = listings.map((l) => l.price).sort((a, b) => a - b);
  if (prices.length === 0) {
    return { scope, sampleCount: 0, min: 0, max: 0, median: 0, average: 0 };
  }
  const sum = prices.reduce((a, b) => a + b, 0);
  return {
    scope,
    sampleCount: prices.length,
    min: prices[0]!,
    max: prices[prices.length - 1]!,
    median: Math.round(median(prices)),
    average: Math.round(sum / prices.length),
  };
}

/** 市場別＋全体の統計をまとめて返す（出品のあった市場のみ集計）。 */
export function analyzeMarkets(listings: MarketListing[]): {
  byMarket: Record<MarketId, MarketStats>;
  overall: MarketStats;
} {
  const ids = [...new Set(listings.map((l) => l.marketId))];
  const byMarket = {} as Record<MarketId, MarketStats>;
  for (const id of ids) {
    byMarket[id] = summarize(
      listings.filter((l) => l.marketId === id),
      id,
    );
  }
  return { byMarket, overall: summarize(listings, "all") };
}

/** ある売値で売った場合の採算。 */
export interface Profitability {
  /** シナリオ名（例: 市場中央値 / 最安値 / 最安値-5%）。 */
  label: string;
  sellPrice: number;
  landedCost: number;
  platformFee: number;
  profit: number;
  /** 利益率 = 利益 / 売値。 */
  marginRate: number;
  /** 投資利益率 ROI = 利益 / 着地原価。 */
  roi: number;
}

/** 指定の売値での採算を計算する。 */
export function profitabilityAt(
  label: string,
  sellPrice: number,
  landedCost: number,
  platformFeeRate: number,
): Profitability {
  const platformFee = Math.round(sellPrice * platformFeeRate);
  const profit = Math.round(sellPrice - platformFee - landedCost);
  return {
    label,
    sellPrice: Math.round(sellPrice),
    landedCost: Math.round(landedCost),
    platformFee,
    profit,
    marginRate: sellPrice > 0 ? Number((profit / sellPrice).toFixed(4)) : 0,
    roi: landedCost > 0 ? Number((profit / landedCost).toFixed(4)) : 0,
  };
}

/**
 * 市場統計と着地原価から、代表的な売値シナリオの採算を一括算出する。
 * - 市場中央値で売った場合
 * - 市場最安値で売った場合
 * - 最安値を少し下回って（undercut）売った場合
 */
export function buildProfitScenarios(
  overall: MarketStats,
  landedCost: number,
  platformFeeRate: number,
  undercutRate = 0.05,
): Profitability[] {
  if (overall.sampleCount === 0) return [];
  return [
    profitabilityAt("市場中央値", overall.median, landedCost, platformFeeRate),
    profitabilityAt("市場最安値", overall.min, landedCost, platformFeeRate),
    profitabilityAt(
      `最安値-${Math.round(undercutRate * 100)}%`,
      overall.min * (1 - undercutRate),
      landedCost,
      platformFeeRate,
    ),
  ];
}
