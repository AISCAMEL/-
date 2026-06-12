import type {
  ChannelId,
  ChannelOrder,
  InventorySnapshot,
  MarketId,
  MarketListing,
  ShipmentInfo,
  SupplierId,
  SupplierOrderRequest,
  SupplierOrderResult,
  SupplierProduct,
} from "@hub/core";

export interface ProductSearchQuery {
  keyword?: string;
  externalId?: string;
  page?: number;
  pageSize?: number;
}

/** 仕入れ先コネクタ共通インターフェース（Alibaba / THE CKB が実装）。 */
export interface SupplierConnector {
  readonly id: SupplierId;
  searchProducts(query: ProductSearchQuery): Promise<SupplierProduct[]>;
  getProduct(externalId: string): Promise<SupplierProduct>;
  getInventory(externalId: string): Promise<InventorySnapshot>;
  /** 無在庫フローの自動発注。 */
  placeOrder(order: SupplierOrderRequest): Promise<SupplierOrderResult>;
  getShipment(supplierOrderId: string): Promise<ShipmentInfo>;
}

export interface ListingInput {
  /** 自社 Product 由来の一意キー。 */
  productKey: string;
  title: string;
  description?: string;
  imageUrls: string[];
  price: number;
  stock: number;
}

export interface ChannelListing {
  channelId: ChannelId;
  /** チャネル側の出品ID（BASE の item_id 等）。 */
  externalListingId: string;
  published: boolean;
}

export interface TrackingInput {
  carrier?: string;
  trackingNumber: string;
}

/** 販売チャネルコネクタ共通インターフェース（BASE が実装）。 */
export interface SalesChannelConnector {
  readonly id: ChannelId;
  upsertListing(listing: ListingInput): Promise<ChannelListing>;
  updateInventory(externalListingId: string, qty: number): Promise<void>;
  updatePrice(externalListingId: string, price: number): Promise<void>;
  setPublished(externalListingId: string, published: boolean): Promise<void>;
  fetchOrders(since: Date): Promise<ChannelOrder[]>;
  pushTracking(externalOrderId: string, tracking: TrackingInput): Promise<void>;
}

export interface MarketSearchQuery {
  keyword: string;
  /** 取得件数の上限。 */
  limit?: number;
}

/** 市場調査コネクタ（Amazon / 楽天 が実装）。売値の調査に使う。 */
export interface MarketResearchConnector {
  readonly id: MarketId;
  searchListings(query: MarketSearchQuery): Promise<MarketListing[]>;
}

/** コネクタ動作モード。live は実APIを叩く。 */
export type ConnectorMode = "mock" | "live";

export interface ConnectorConfig {
  mode: ConnectorMode;
  /** live モードで使用する認証情報。 */
  credentials?: Record<string, string | undefined>;
}
