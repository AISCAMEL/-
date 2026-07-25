import type { ChannelOrder } from "@hub/core";
import type {
  ChannelListing,
  ConnectorConfig,
  ListingInput,
  SalesChannelConnector,
  TrackingInput,
} from "../types.js";

const BASE_API = "https://api.thebase.in/1";

/**
 * BASE 販売チャネルコネクタ。
 *
 * 実連携は BASE Developers API（OAuth2.0）を使用:
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
  private accessToken: string | undefined;

  constructor(private config: ConnectorConfig) {
    this.accessToken = config.credentials?.BASE_ACCESS_TOKEN;
  }

  private get live(): boolean {
    return this.config.mode === "live";
  }

  activateLive(accessToken: string) {
    this.accessToken = accessToken;
    this.config = { ...this.config, mode: "live" };
  }

  private async api(path: string, opts?: RequestInit): Promise<any> {
    if (!this.accessToken) throw new Error("BASE access token is not set");
    const res = await fetch(`${BASE_API}${path}`, {
      ...opts,
      headers: {
        Authorization: `Bearer ${this.accessToken}`,
        ...opts?.headers,
      },
    });
    const body = (await res.json()) as Record<string, unknown>;
    if (!res.ok) {
      throw new Error(
        `BASE API ${path} ${res.status}: ${String(body.error_description || body.error || JSON.stringify(body))}`,
      );
    }
    return body;
  }

  async upsertListing(listing: ListingInput): Promise<ChannelListing> {
    if (this.live) {
      const form = new URLSearchParams();
      form.set("title", listing.title);
      form.set("price", String(Math.round(listing.price)));
      form.set("stock", String(Math.round(listing.stock)));
      form.set("visible", listing.stock > 0 ? "1" : "0");
      form.set("identifier", listing.productKey);
      if (listing.description) form.set("detail", listing.description);
      const firstImg = listing.imageUrls[0];
      if (firstImg) form.set("img1_origin", firstImg);

      const data = await this.api("/items/add", {
        method: "POST",
        body: form,
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
      });

      return {
        channelId: this.id,
        externalListingId: String(data.item?.item_id ?? "unknown"),
        published: listing.stock > 0,
      };
    }
    this.seq += 1;
    return {
      channelId: this.id,
      externalListingId: `base_mock_${listing.productKey}_${this.seq}`,
      published: listing.stock > 0,
    };
  }

  async updateInventory(externalListingId: string, qty: number): Promise<void> {
    if (this.live) {
      const form = new URLSearchParams();
      form.set("item_id", externalListingId);
      form.set("stock", String(Math.round(qty)));
      await this.api("/items/edit", {
        method: "POST",
        body: form,
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
      });
      return;
    }
    void externalListingId;
    void qty;
  }

  async updatePrice(externalListingId: string, price: number): Promise<void> {
    if (this.live) {
      const form = new URLSearchParams();
      form.set("item_id", externalListingId);
      form.set("price", String(Math.round(price)));
      await this.api("/items/edit", {
        method: "POST",
        body: form,
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
      });
      return;
    }
    void externalListingId;
    void price;
  }

  async setPublished(externalListingId: string, published: boolean): Promise<void> {
    if (this.live) {
      const form = new URLSearchParams();
      form.set("item_id", externalListingId);
      form.set("visible", published ? "1" : "0");
      await this.api("/items/edit", {
        method: "POST",
        body: form,
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
      });
      return;
    }
    void externalListingId;
    void published;
  }

  async fetchOrders(since: Date): Promise<ChannelOrder[]> {
    if (this.live) {
      const start = since.toISOString().split("T")[0];
      const data = await this.api(`/orders?start_created=${start}`);
      return (data.orders ?? []).map((o: any) => ({
        channelId: this.id,
        externalOrderId: String(o.unique_key ?? o.order_id),
        status: "received" as const,
        buyer: {
          name: `${o.last_name ?? ""} ${o.first_name ?? ""}`.trim() || "N/A",
          postalCode: o.zip_code,
          address: o.address,
          phone: o.tel,
        },
        items: (o.order_items ?? []).map((item: any) => ({
          externalItemId: String(item.item_id),
          sku: item.item_id ? String(item.item_id) : "",
          quantity: item.amount ?? 1,
          unitPrice: item.price ?? 0,
        })),
        total: o.total ?? 0,
        orderedAt: new Date(o.ordered ?? o.created),
      }));
    }
    void since;
    return [];
  }

  async pushTracking(externalOrderId: string, tracking: TrackingInput): Promise<void> {
    if (this.live) {
      const form = new URLSearchParams();
      form.set("order_item_id", externalOrderId);
      form.set("status", "shipped");
      if (tracking.trackingNumber) {
        form.set("tracking_number", tracking.trackingNumber);
      }
      await this.api("/orders/edit_status", {
        method: "POST",
        body: form,
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
      });
      return;
    }
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
