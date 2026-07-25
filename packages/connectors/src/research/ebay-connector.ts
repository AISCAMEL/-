import type { MarketListing } from "@hub/core";
import { NotImplementedLiveError } from "../base/base-connector.js";
import type { ConnectorConfig, MarketResearchConnector, MarketSearchQuery } from "../types.js";

/**
 * eBay の市場調査コネクタ（越境・海外相場用）。
 *
 * 実連携は eBay Browse API を使用する想定:
 *   GET https://api.ebay.com/buy/browse/v1/item_summary/search?q=<kw>&limit=20
 *   Authorization: Bearer <OAuth アプリトークン(client_credentials)>
 *   → itemSummaries[].{title, price.value/currency, itemWebUrl}
 *
 * OAuth アプリトークンの取得が必要（EBAY_OAUTH_TOKEN）。
 * 価格は USD 等のため、利益計算前に JPY 換算する点に注意。
 * mock では決定的なダミー（JPY換算済み想定）を返す。
 */
export class EbayConnector implements MarketResearchConnector {
  readonly id = "ebay" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  async searchListings(query: MarketSearchQuery): Promise<MarketListing[]> {
    if (this.live) {
      const token = this.config.credentials?.EBAY_OAUTH_TOKEN;
      if (!token) throw new NotImplementedLiveError("ebay.searchListings (EBAY_OAUTH_TOKEN 未設定)");
      // TODO: Browse API を fetch し itemSummaries[] を MarketListing へ変換
      //   価格は price.value(通貨) → JPY 換算してから price に格納する
      throw new NotImplementedLiveError("ebay.searchListings (Browse API 連携は実装予定)");
    }
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
}
