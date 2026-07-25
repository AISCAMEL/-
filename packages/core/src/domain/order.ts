import type { ChannelId, OrderStatus, SupplierId } from "./ids.js";

/** 販売チャネルから取り込んだ注文。 */
export interface ChannelOrder {
  channelId: ChannelId;
  externalOrderId: string;
  status: OrderStatus;
  buyer: {
    name: string;
    postalCode?: string;
    address?: string;
    phone?: string;
  };
  items: ChannelOrderItem[];
  /** 売上合計（JPY）。 */
  total: number;
  orderedAt: Date;
}

export interface ChannelOrderItem {
  externalItemId: string;
  /** 紐づく自社 Product の SKU。 */
  sku: string;
  quantity: number;
  /** 販売単価（JPY）。 */
  unitPrice: number;
}

/** 仕入れ先への自動発注リクエスト。 */
export interface SupplierOrderRequest {
  supplierId: SupplierId;
  externalProductId: string;
  externalSkuId?: string;
  quantity: number;
  /** 配送先（顧客直送 or 中継倉庫）。 */
  shipTo: {
    name: string;
    postalCode?: string;
    address?: string;
    phone?: string;
  };
}

export interface SupplierOrderResult {
  supplierOrderId: string;
  accepted: boolean;
  /** 仕入確定金額（仕入れ先通貨）。 */
  cost?: number;
  message?: string;
}

export interface ShipmentInfo {
  carrier?: string;
  trackingNumber?: string;
  shippedAt?: Date;
}
