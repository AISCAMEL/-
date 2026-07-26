import type { MarketListing } from "@hub/core";
import type { ConnectorConfig, MarketResearchConnector, MarketSearchQuery } from "../types.js";

const EBAY_BROWSE_API = "https://api.ebay.com/buy/browse/v1";

export class EbayConnector implements MarketResearchConnector {
  readonly id = "ebay" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  async searchListings(query: MarketSearchQuery): Promise<MarketListing[]> {
    if (this.live) return this.fetchLive(query);
    const base = 3600;
    return Array.from({ length: query.limit ?? 5 }, (_, i) => ({
      marketId: this.id,
      title: `${query.keyword} eBayサンプル ${i + 1}`,
      price: base + i * 350,
      url: `https://www.ebay.com/itm/SAMPLE${i + 1}`,
      reviewCount: 0,
      rating: 0,
    }));
  }

  private async fetchLive(query: MarketSearchQuery): Promise<MarketListing[]> {
    const token = this.config.credentials?.EBAY_OAUTH_TOKEN;
    if (!token) throw new Error("EBAY_OAUTH_TOKEN 未設定");

    const url = new URL(`${EBAY_BROWSE_API}/item_summary/search`);
    url.searchParams.set("q", query.keyword);
    url.searchParams.set("limit", String(Math.min(query.limit ?? 20, 50)));

    const res = await fetch(url, {
      headers: {
        "Authorization": `Bearer ${token}`,
        "X-EBAY-C-MARKETPLACE-ID": "EBAY_US",
        "Content-Type": "application/json",
      },
    });
    if (!res.ok) {
      const body = await res.text().catch(() => "");
      throw new Error(`eBay API error ${res.status}: ${body}`);
    }

    const data = (await res.json()) as EbaySearchResponse;
    return (data.itemSummaries ?? []).map((item) => ({
      marketId: this.id,
      title: item.title,
      price: parseFloat(item.price?.value ?? "0"),
      url: item.itemWebUrl,
      reviewCount: 0,
      rating: 0,
    }));
  }
}

interface EbaySearchResponse {
  itemSummaries?: {
    title: string;
    price?: { value: string; currency: string };
    itemWebUrl: string;
  }[];
}
