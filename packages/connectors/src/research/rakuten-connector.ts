import type { MarketListing } from "@hub/core";
import { NotImplementedLiveError } from "../base/base-connector.js";
import type { ConnectorConfig, MarketResearchConnector, MarketSearchQuery } from "../types.js";

/**
 * 楽天市場の市場調査コネクタ。
 *
 * 実連携は楽天ウェブサービスの「楽天市場商品検索API」(IchibaItem/Search) を使用:
 *   GET https://app.rakuten.co.jp/services/api/IchibaItem/Search/20220601
 *       ?applicationId=...&keyword=...&hits=30&sort=standard
 *   → items[].item の itemName / itemPrice / itemUrl / reviewCount / reviewAverage
 *
 * 無料（applicationId の登録のみ）。mock では決定的なダミーを返す。
 */
export class RakutenConnector implements MarketResearchConnector {
  readonly id = "rakuten" as const;
  private queue: Promise<void> = Promise.resolve();

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  async searchListings(query: MarketSearchQuery): Promise<MarketListing[]> {
    if (this.live) return this.enqueue(query);
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

  // 楽天APIは1秒1リクエスト制限 — 直列キューで守る
  private enqueue(query: MarketSearchQuery): Promise<MarketListing[]> {
    const prev = this.queue;
    let resolve!: () => void;
    this.queue = new Promise<void>((r) => { resolve = r; });
    return prev.then(async () => {
      try {
        return await this.fetchLive(query);
      } finally {
        await new Promise<void>((r) => setTimeout(r, 700));
        resolve();
      }
    });
  }

  private async fetchLive(query: MarketSearchQuery): Promise<MarketListing[]> {
    const appId = this.config.credentials?.RAKUTEN_APP_ID;
    if (!appId) throw new NotImplementedLiveError("rakuten.searchListings (RAKUTEN_APP_ID 未設定)");
    const accessKey = this.config.credentials?.RAKUTEN_ACCESS_KEY;

    const url = new URL("https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20220601");
    url.searchParams.set("applicationId", appId);
    url.searchParams.set("keyword", query.keyword);
    url.searchParams.set("hits", String(Math.min(query.limit ?? 30, 30)));
    url.searchParams.set("sort", "standard");
    url.searchParams.set("formatVersion", "2");

    const headers: Record<string, string> = {};
    if (accessKey) {
      headers["accessKey"] = accessKey;
    }

    const res = await fetch(url, { headers });
    if (!res.ok) {
      const body = await res.text().catch(() => "");
      throw new Error(`Rakuten API error ${res.status}: ${body}`);
    }
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
