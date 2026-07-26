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
export interface ScreenResult {
  items: ScreenedItem[];
  errors: number;
  scoredCount: number;
}

export async function screenCandidates(params: {
  candidates: ScreenCandidate[];
  resolveSupplier: (id: string) => SupplierConnector | undefined;
  markets: MarketResearchConnector[];
  options?: ScreenOptions;
}): Promise<ScreenResult> {
  const { candidates, resolveSupplier, markets, options = {} } = params;

  const scored = await Promise.all(
    candidates.map(async (c): Promise<ScreenedItem | null> => {
      try {
        const supplier = resolveSupplier(c.supplierId);
        if (!supplier) {
          console.warn(`[screen] supplier "${c.supplierId}" not found`);
          return null;
        }

        const product = await supplier.getProduct(c.externalId);
        const keyword = c.keyword ?? product.title;

        const research = await researchMarket({
          keyword,
          markets,
          limit: options.limit,
          supplier: { connector: supplier, externalId: c.externalId },
          rule: options.rule,
        });

        console.log(
          `[screen] "${keyword}": listings=${research.market.overall.sampleCount} landedCost=${research.landedCost} scenarios=${research.scenarios.length}`,
        );

        const chosen = research.scenarios[0] ?? null;
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

        const sorted = [...research.market.listings].sort(
          (a, b) => Math.abs(a.price - research.market.overall.median) - Math.abs(b.price - research.market.overall.median),
        );
        const topListings = sorted.slice(0, 5).map((l) => ({
          title: l.title,
          price: l.price,
          url: l.url,
          marketId: l.marketId,
        }));

        return {
          key: `${c.supplierId}:${c.externalId}`,
          keyword,
          landedCost: research.landedCost,
          chosen,
          marketSampleCount: research.market.overall.sampleCount,
          score,
          topListings,
        };
      } catch (err) {
        console.error(`[screen] FAIL ${c.supplierId}:${c.externalId}:`, err);
        return null;
      }
    }),
  );

  const valid = scored.filter((s): s is ScreenedItem => s !== null);
  console.log(
    `[screen] ${candidates.length} candidates → ${valid.length} scored, filter: minMargin=${options.minMarginRate ?? 0} minGrade=${options.minGrade ?? "any"}`,
  );
  for (const v of valid) {
    console.log(
      `[screen]   ${v.keyword}: grade=${v.score.grade} score=${v.score.score} margin=${v.chosen?.marginRate ?? "N/A"} samples=${v.marketSampleCount}`,
    );
  }

  const ranked = screen(valid, {
    minMarginRate: options.minMarginRate,
    minGrade: options.minGrade,
  });
  const errors = candidates.length - valid.length;
  console.log(`[screen] after filter: ${ranked.length} items passed, ${errors} errors`);
  return { items: ranked, errors, scoredCount: valid.length };
}
