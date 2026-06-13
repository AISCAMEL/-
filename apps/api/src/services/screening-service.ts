import {
  scoreProduct,
  screen,
  validateForListing,
  type PriceRule,
  type ScreenedItem,
  type ScoreResult,
} from "@hub/core";
import type { MarketResearchConnector, SupplierConnector } from "@hub/connectors";
import { researchMarket } from "./research-service.js";

export interface ScreenCandidate {
  supplierId: string;
  externalId: string;
  /** 市場調査キーワード（未指定なら商品タイトルを使用）。 */
  keyword?: string;
}

export interface ScreenOptions {
  minMarginRate?: number;
  minGrade?: ScoreResult["grade"];
  limit?: number;
  rule?: PriceRule;
}

/**
 * 複数の仕入れ候補を一括で市場調査・採点し、足切り＆スコア順に並べて返す。
 * 「利益率○%以上の売れ筋だけ」を自動リスト化する用途。
 */
export async function screenCandidates(params: {
  candidates: ScreenCandidate[];
  resolveSupplier: (id: string) => SupplierConnector | undefined;
  markets: MarketResearchConnector[];
  options?: ScreenOptions;
}): Promise<ScreenedItem[]> {
  const { candidates, resolveSupplier, markets, options = {} } = params;

  const scored = await Promise.all(
    candidates.map(async (c): Promise<ScreenedItem | null> => {
      const supplier = resolveSupplier(c.supplierId);
      if (!supplier) return null;

      const product = await supplier.getProduct(c.externalId);
      const keyword = c.keyword ?? product.title;

      const research = await researchMarket({
        keyword,
        markets,
        limit: options.limit,
        supplier: { connector: supplier, externalId: c.externalId },
        rule: options.rule,
      });

      const chosen = research.scenarios[0] ?? null; // 市場中央値シナリオ
      const issues = validateForListing(product);
      const hasComplianceBlock = issues.some((i) => i.severity === "block");

      const reviews = research.market.listings
        .map((l) => l.reviewCount ?? 0)
        .filter((n) => n > 0);
      const avgReviewCount =
        reviews.length > 0 ? reviews.reduce((a, b) => a + b, 0) / reviews.length : 0;

      const score = scoreProduct({
        marginRate: chosen?.marginRate ?? 0,
        profit: chosen?.profit ?? 0,
        sampleCount: research.market.overall.sampleCount,
        avgReviewCount,
        hasComplianceBlock,
        stockStable: (product.stock ?? 0) > 0,
      });

      return {
        key: `${c.supplierId}:${c.externalId}`,
        keyword,
        landedCost: research.landedCost,
        chosen,
        marketSampleCount: research.market.overall.sampleCount,
        score,
      };
    }),
  );

  return screen(scored.filter((s): s is ScreenedItem => s !== null), {
    minMarginRate: options.minMarginRate,
    minGrade: options.minGrade,
  });
}
