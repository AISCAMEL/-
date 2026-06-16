import type { MarketListing } from "@hub/core";
import { NotImplementedLiveError } from "../base/base-connector.js";
import type { ConnectorConfig, MarketResearchConnector, MarketSearchQuery } from "../types.js";

/**
 * Yahoo!ショッピングの市場調査コネクタ。
 *
 * 実連携は Yahoo!ショッピング 商品検索API(v3) を使用:
 *   GET https://shopping.yahooapis.jp/ShoppingWebService/V3/itemSearch
 *       ?appid=<YAHOO_APP_ID>&query=<kw>&results=20&sort=+price
 *   → hits[].{name, price, url, review.count, review.rate}
 *
 * 無料（YAHOO_APP_ID/Client ID の登録のみ）。mock では決定的なダミーを返す。
 */
export class YahooConnector implements MarketResearchConnector {
  readonly id = "yahoo" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  async searchListings(query: MarketSearchQuery): Promise<MarketListing[]> {
    if (this.live) return this.fetchLive(query);
    const base = 3050;
    return Array.from({ length: query.limit ?? 5 }, (_, i) => ({
      marketId: this.id,
      title: `${query.keyword} Yahooサンプル ${i + 1}`,
      price: base + i * 280,
      url: `https://store.shopping.yahoo.co.jp/sample/${i + 1}`,
      reviewCount: 30 + i * 6,
      rating: 4.3,
    }));
  }

  /** Yahoo!ショッピング 商品検索API(v3) を実呼び出しする。 */
  private async fetchLive(query: MarketSearchQuery): Promise<MarketListing[]> {
    const appId = this.config.credentials?.YAHOO_APP_ID;
    if (!appId) throw new NotImplementedLiveError("yahoo.searchListings (YAHOO_APP_ID 未設定)");

    const url = new URL("https://shopping.yahooapis.jp/ShoppingWebService/V3/itemSearch");
    url.searchParams.set("appid", appId);
    url.searchParams.set("query", query.keyword);
    url.searchParams.set("results", String(Math.min(query.limit ?? 20, 50)));
    url.searchParams.set("sort", "+price");

    const res = await fetch(url);
    if (!res.ok) throw new Error(`Yahoo API error: ${res.status} ${await res.text()}`);
    const data = (await res.json()) as YahooSearchResponse;

    return (data.hits ?? []).map((hit) => ({
      marketId: this.id,
      title: hit.name,
      price: hit.price,
      url: hit.url,
      reviewCount: hit.review?.count ?? 0,
      rating: hit.review?.rate ?? 0,
    }));
  }
}

interface YahooSearchResponse {
  hits?: {
    name: string;
    price: number;
    url: string;
    review?: { count: number; rate: number };
  }[];
}
