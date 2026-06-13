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
    if (this.live) return this.fetchLive(query);
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

  /** 楽天市場 商品検索API (IchibaItem/Search) を実呼び出しする。 */
  private async fetchLive(query: MarketSearchQuery): Promise<MarketListing[]> {
    const appId = this.config.credentials?.RAKUTEN_APP_ID;
    if (!appId) throw new NotImplementedLiveError("rakuten.searchListings (RAKUTEN_APP_ID 未設定)");

    const url = new URL("https://app.rakuten.co.jp/services/api/IchibaItem/Search/20220601");
    url.searchParams.set("applicationId", appId);
    url.searchParams.set("keyword", query.keyword);
    url.searchParams.set("hits", String(Math.min(query.limit ?? 30, 30)));
    url.searchParams.set("sort", "+itemPrice"); // 価格の安い順
    url.searchParams.set("formatVersion", "2");

    const res = await fetch(url);
    if (!res.ok) throw new Error(`Rakuten API error: ${res.status} ${await res.text()}`);
    const data = (await res.json()) as RakutenSearchResponse;

    return (data.Items ?? []).map((item) => ({
      marketId: this.id,
      title: item.itemName,
      price: item.itemPrice,
      url: item.itemUrl,
      reviewCount: item.reviewCount,
      rating: item.reviewAverage,
    }));
  }
}

/** formatVersion=2 のレスポンス形（必要フィールドのみ）。 */
interface RakutenSearchResponse {
  Items?: {
    itemName: string;
    itemPrice: number;
    itemUrl: string;
    reviewCount: number;
    reviewAverage: number;
  }[];
}
