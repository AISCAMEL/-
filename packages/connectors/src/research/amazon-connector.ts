import type { MarketListing } from "@hub/core";
import { NotImplementedLiveError } from "../base/base-connector.js";
import type { ConnectorConfig, MarketResearchConnector, MarketSearchQuery } from "../types.js";

/**
 * Amazon の市場調査コネクタ。
 *
 * Amazon には「無料の価格検索API」が無いため、live では以下のいずれかを使う想定:
 *   - Amazon Product Advertising API (PA-API 5.0) … アソシエイト審査・売上要件あり
 *   - Keepa API … 有料。価格推移・ランキング・相場に強い（リサーチ用途に最適）
 *   - Amazon SP-API (Product Pricing) … 出品者向け・審査必要
 *
 * 認証情報は config.credentials（AMAZON_PAAPI_* / KEEPA_API_KEY 等）で受け取る。
 * mock では決定的なダミーを返す。
 */
export class AmazonConnector implements MarketResearchConnector {
  readonly id = "amazon" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  async searchListings(query: MarketSearchQuery): Promise<MarketListing[]> {
    if (this.live) {
      // TODO: Keepa もしくは PA-API を呼び出し、価格を MarketListing へ変換
      throw new NotImplementedLiveError("amazon.searchListings");
    }
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
}
