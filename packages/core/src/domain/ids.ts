/** プラットフォーム識別子。Connector の id と一致させる。 */
export type SupplierId = "alibaba" | "theckb" | "aliexpress";
export type ChannelId = "base";

/** 通貨コード（ISO 4217 のうち本システムで扱うもの）。 */
export type Currency = "JPY" | "CNY" | "USD";

/** 出品状態。 */
export type ListingStatus = "draft" | "published" | "unpublished" | "error";

/** 受注状態。 */
export type OrderStatus =
  | "received"
  | "fulfilling"
  | "ordered_to_supplier"
  | "shipped"
  | "completed"
  | "cancelled";
