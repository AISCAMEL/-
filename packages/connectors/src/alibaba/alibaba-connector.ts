import type {
  InventorySnapshot,
  ShipmentInfo,
  SupplierOrderRequest,
  SupplierOrderResult,
  SupplierProduct,
} from "@hub/core";
import { NotImplementedLiveError } from "../base/base-connector.js";
import type { ConnectorConfig, ProductSearchQuery, SupplierConnector } from "../types.js";

/**
 * Alibaba.com 仕入れコネクタ。
 * 実連携は Alibaba.com Open Platform API（要アプリ審査）を使用する想定。
 * mock モードでは決定的なダミー商品を返す。
 */
export class AlibabaConnector implements SupplierConnector {
  readonly id = "alibaba" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  async searchProducts(query: ProductSearchQuery): Promise<SupplierProduct[]> {
    if (this.live) throw new NotImplementedLiveError("alibaba.searchProducts");
    return [this.mockProduct(query.externalId ?? "ALI-0001")];
  }

  async getProduct(externalId: string): Promise<SupplierProduct> {
    if (this.live) throw new NotImplementedLiveError("alibaba.getProduct");
    return this.mockProduct(externalId);
  }

  async getInventory(externalId: string): Promise<InventorySnapshot> {
    if (this.live) throw new NotImplementedLiveError("alibaba.getInventory");
    return { externalId, stock: 120, cost: 18.5, costCurrency: "CNY", fetchedAt: new Date() };
  }

  async placeOrder(order: SupplierOrderRequest): Promise<SupplierOrderResult> {
    if (this.live) throw new NotImplementedLiveError("alibaba.placeOrder");
    return {
      supplierOrderId: `ali_mock_${order.externalProductId}_${Date.now()}`,
      accepted: true,
      cost: 18.5 * order.quantity,
      message: "mock accepted",
    };
  }

  async getShipment(supplierOrderId: string): Promise<ShipmentInfo> {
    if (this.live) throw new NotImplementedLiveError("alibaba.getShipment");
    return { carrier: "MockExpress", trackingNumber: `TRK${supplierOrderId}`, shippedAt: new Date() };
  }

  private mockProduct(externalId: string): SupplierProduct {
    return {
      supplierId: this.id,
      externalId,
      title: `Alibaba サンプル商品 ${externalId}`,
      description: "モックデータ。live モードで実APIに差し替え。",
      imageUrls: ["https://example.com/alibaba/sample.jpg"],
      cost: 18.5,
      costCurrency: "CNY",
      stock: 120,
      skus: [],
      sourceUrl: "https://www.alibaba.com/product-detail/sample.html",
    };
  }
}
