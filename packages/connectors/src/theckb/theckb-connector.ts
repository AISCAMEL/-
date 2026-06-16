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
 * THE CKB 仕入れコネクタ。
 * THE CKB は 1688/タオバオを日本向けに集約した卸プラットフォームで、
 * 無在庫ドロップシッピング向けの API / CSV 連携を提供する想定。
 * 実連携には契約と API キー（THECKB_API_KEY）が必要。
 * mock モードでは決定的なダミー商品を返す。
 */
export class TheCkbConnector implements SupplierConnector {
  readonly id = "theckb" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  async searchProducts(query: ProductSearchQuery): Promise<SupplierProduct[]> {
    if (this.live) throw new NotImplementedLiveError("theckb.searchProducts");
    return [this.mockProduct(query.externalId ?? "CKB-0001")];
  }

  async getProduct(externalId: string): Promise<SupplierProduct> {
    if (this.live) throw new NotImplementedLiveError("theckb.getProduct");
    return this.mockProduct(externalId);
  }

  async getInventory(externalId: string): Promise<InventorySnapshot> {
    if (this.live) throw new NotImplementedLiveError("theckb.getInventory");
    return { externalId, stock: 45, cost: 32, costCurrency: "CNY", fetchedAt: new Date() };
  }

  async placeOrder(order: SupplierOrderRequest): Promise<SupplierOrderResult> {
    if (this.live) throw new NotImplementedLiveError("theckb.placeOrder");
    return {
      supplierOrderId: `ckb_mock_${order.externalProductId}_${Date.now()}`,
      accepted: true,
      cost: 32 * order.quantity,
      message: "mock accepted",
    };
  }

  async getShipment(supplierOrderId: string): Promise<ShipmentInfo> {
    if (this.live) throw new NotImplementedLiveError("theckb.getShipment");
    return { carrier: "CKB Logistics", trackingNumber: `CKB${supplierOrderId}`, shippedAt: new Date() };
  }

  private mockProduct(externalId: string): SupplierProduct {
    return {
      supplierId: this.id,
      externalId,
      title: `THE CKB サンプル商品 ${externalId}`,
      description: "モックデータ。live モードで THE CKB API に差し替え。",
      imageUrls: ["https://example.com/theckb/sample.jpg"],
      cost: 32,
      costCurrency: "CNY",
      stock: 45,
      skus: [
        { externalSkuId: `${externalId}-RED`, attributes: { color: "red" }, cost: 32, stock: 20 },
        { externalSkuId: `${externalId}-BLUE`, attributes: { color: "blue" }, cost: 32, stock: 25 },
      ],
      sourceUrl: "https://www.theckb.com/item/sample",
    };
  }
}
