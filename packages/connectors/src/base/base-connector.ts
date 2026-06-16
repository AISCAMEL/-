import type { ChannelOrder } from "@hub/core";
import type {
  ChannelListing,
  ConnectorConfig,
  ListingInput,
  SalesChannelConnector,
  TrackingInput,
} from "../types.js";

/**
 * BASE 販売チャネルコネクタ。
 *
 * 実連携は BASE Developers API（OAuth2.0）を使用する想定:
 *   - 商品: POST /1/items/add, POST /1/items/edit
 *   - 在庫: items の stock フィールド更新
 *   - 注文: GET /1/orders
 *   - 公開制御: visible フラグ
 *
 * mock モードでは外部呼び出しを行わず、決定的なダミー応答を返す。
 */
export class BaseConnector implements SalesChannelConnector {
  readonly id = "base" as const;
  private seq = 0;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  async upsertListing(listing: ListingInput): Promise<ChannelListing> {
    if (this.live) {
      // TODO: BASE Items API (add/edit) を呼び出す
      throw new NotImplementedLiveError("base.upsertListing");
    }
    this.seq += 1;
    return {
      channelId: this.id,
      externalListingId: `base_mock_${listing.productKey}_${this.seq}`,
      published: listing.stock > 0,
    };
  }

  async updateInventory(externalListingId: string, qty: number): Promise<void> {
    if (this.live) throw new NotImplementedLiveError("base.updateInventory");
    void externalListingId;
    void qty;
  }

  async updatePrice(externalListingId: string, price: number): Promise<void> {
    if (this.live) throw new NotImplementedLiveError("base.updatePrice");
    void externalListingId;
    void price;
  }

  async setPublished(externalListingId: string, published: boolean): Promise<void> {
    if (this.live) throw new NotImplementedLiveError("base.setPublished");
    void externalListingId;
    void published;
  }

  async fetchOrders(since: Date): Promise<ChannelOrder[]> {
    if (this.live) throw new NotImplementedLiveError("base.fetchOrders");
    void since;
    return [];
  }

  async pushTracking(externalOrderId: string, tracking: TrackingInput): Promise<void> {
    if (this.live) throw new NotImplementedLiveError("base.pushTracking");
    void externalOrderId;
    void tracking;
  }
}

export class NotImplementedLiveError extends Error {
  constructor(op: string) {
    super(`live モード未実装: ${op}（API審査/認証の設定後に実装）`);
    this.name = "NotImplementedLiveError";
  }
}
