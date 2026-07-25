import type { MarketListing } from "@hub/core";
import { NotImplementedLiveError } from "../base/base-connector.js";
import type { ConnectorConfig, MarketResearchConnector, MarketSearchQuery } from "../types.js";

/** Amazon.co.jp の Keepa ドメインID。 */
const KEEPA_DOMAIN_JP = 5;

/**
 * Keepa の stats.current 配列インデックス（必要分のみ）。
 * https://keepa.com/#!discuss/t/product-object/116
 */
const KEEPA = { AMAZON: 0, NEW: 1, RATING: 16, COUNT_REVIEWS: 17, BUY_BOX: 18 } as const;

/**
 * Amazon の市場調査コネクタ。
 *
 * Amazon には「無料の価格検索API」が無いため、live では Keepa API（有料）を使用する。
 *   1) /search  … キーワード → ASIN リスト
 *   2) /product … ASIN群 → 現在価格・レビュー数・評価
 * PA-API 5.0 / SP-API を使う場合の差込口も将来追加可能。
 *
 * 認証情報は config.credentials.KEEPA_API_KEY。mock では決定的なダミーを返す。
 */
export class AmazonConnector implements MarketResearchConnector {
  readonly id = "amazon" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  async searchListings(query: MarketSearchQuery): Promise<MarketListing[]> {
    if (this.live) return this.fetchLiveViaKeepa(query);
    const base = 3400;
    return Array.from({ length: query.limit ?? 5 }, (_, i) => ({
      marketId: this.id,
      title: `${query.keyword} Amazonサンプル ${i + 1}`,
      price: base + i * 300,
      url: `https://www.amazon.co.jp/dp/SAMPLE${i + 1}`,
      reviewCount: 120 + i * 10,
      rating: 4.4,
    }));
  }

  /** Keepa API でキーワード検索 → 現在価格を取得する。 */
  private async fetchLiveViaKeepa(query: MarketSearchQuery): Promise<MarketListing[]> {
    const key = this.config.credentials?.KEEPA_API_KEY;
    if (!key) throw new NotImplementedLiveError("amazon.searchListings (KEEPA_API_KEY 未設定)");

    const limit = Math.min(query.limit ?? 10, 20);

    // 1) キーワード → ASIN リスト
    const searchUrl = new URL("https://api.keepa.com/search");
    searchUrl.searchParams.set("key", key);
    searchUrl.searchParams.set("domain", String(KEEPA_DOMAIN_JP));
    searchUrl.searchParams.set("type", "product");
    searchUrl.searchParams.set("term", query.keyword);
    const sres = await fetch(searchUrl);
    if (!sres.ok) throw new Error(`Keepa search error: ${sres.status} ${await sres.text()}`);
    const sdata = (await sres.json()) as { asinList?: string[] };
    const asins = (sdata.asinList ?? []).slice(0, limit);
    if (asins.length === 0) return [];

    // 2) ASIN群 → 現在価格・レビュー
    const prodUrl = new URL("https://api.keepa.com/product");
    prodUrl.searchParams.set("key", key);
    prodUrl.searchParams.set("domain", String(KEEPA_DOMAIN_JP));
    prodUrl.searchParams.set("asin", asins.join(","));
    prodUrl.searchParams.set("stats", "1");
    const pres = await fetch(prodUrl);
    if (!pres.ok) throw new Error(`Keepa product error: ${pres.status} ${await pres.text()}`);
    const pdata = (await pres.json()) as { products?: KeepaProduct[] };

    return (pdata.products ?? [])
      .map((p): MarketListing | null => {
        const price = pickPrice(p.stats?.current);
        if (price == null) return null;
        const rating = valueOrNull(p.stats?.current?.[KEEPA.RATING]);
        const reviews = valueOrNull(p.stats?.current?.[KEEPA.COUNT_REVIEWS]);
        return {
          marketId: this.id,
          title: p.title ?? p.asin,
          // JP(domain 5) は円そのまま。Keepa の価格は -1 が「データなし」。
          price,
          url: `https://www.amazon.co.jp/dp/${p.asin}`,
          reviewCount: reviews ?? 0,
          rating: rating != null ? rating / 10 : 0, // Keepa の評価は 0〜50
        };
      })
      .filter((x): x is MarketListing => x !== null);
  }
}

interface KeepaProduct {
  asin: string;
  title?: string;
  stats?: { current?: number[] };
}

/** -1（データなし）でない正の値のみ返す。 */
function valueOrNull(v: number | undefined): number | null {
  return v != null && v >= 0 ? v : null;
}

/** New → Buy Box → Amazon の順で有効な現在価格を選ぶ。 */
function pickPrice(current?: number[]): number | null {
  if (!current) return null;
  return (
    valueOrNull(current[KEEPA.NEW]) ??
    valueOrNull(current[KEEPA.BUY_BOX]) ??
    valueOrNull(current[KEEPA.AMAZON])
  );
}
