import type { MarketListing } from "@hub/core";
import { NotImplementedLiveError } from "../base/base-connector.js";
import type { ConnectorConfig, MarketResearchConnector, MarketSearchQuery } from "../types.js";

/**
 * 楽天市場の市場調査コネクタ。
 *
 * 実連携は楽天ウェブサービスの「楽天市場商品検索API」(IchibaItem/Search) を使用:
 *   GET https://app.rakuten.co.jp/services/api/IchibaItem/Search/20220601
 *       ?applicationId=...&keyword=...&hits=30&sort=+itemPrice
 *   → items[].item の itemName / itemPrice / itemUrl / reviewCount / reviewAverage
 *
 * 無料（applicationId の登録のみ）。mock では決定的なダミーを返す。
 */
export class RakutenConnector implements MarketResearchConnector {
  readonly id = "rakuten" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  async searchListings(query: MarketSearchQuery): Promise<MarketListing[]> {
    if (this.live) {
      // TODO: 楽天 IchibaItem/Search を fetch し item[] を MarketListing へ変換
      //   const appId = this.config.credentials?.RAKUTEN_APP_ID
      throw new NotImplementedLiveError("rakuten.searchListings");
    }
    const base = 3200;
    return Array.from({ length: query.limit ?? 5 }, (_, i) => ({
      marketId: this.id,
      title: `${query.keyword} 楽天サンプル ${i + 1}`,
      price: base + i * 250,
      url: `https://item.rakuten.co.jp/sample/${i + 1}`,
      reviewCount: 40 + i * 5,
      rating: 4.2,
    }));
  }
}
