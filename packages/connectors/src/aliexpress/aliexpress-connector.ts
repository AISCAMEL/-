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
 * AliExpress 仕入れコネクタ（無在庫ドロップシッピング向け）。
 *
 * 実連携は AliExpress Open Platform の Dropshipping/Affiliate API を使用する想定:
 *   - 商品検索: aliexpress.ds.product.list / aliexpress.affiliate.product.query
 *   - 商品詳細: aliexpress.ds.product.get
 *   - 発注: aliexpress.ds.order.create
 *   署名（app_key / app_secret による sign）が必要。
 *
 * 認証情報は config.credentials（ALIEXPRESS_APP_KEY / ALIEXPRESS_APP_SECRET 等）で受け取る。
 * mock では決定的なダミー商品を返す。
 */
export class AliExpressConnector implements SupplierConnector {
  readonly id = "aliexpress" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  private ensureLive(op: string): never {
    const key = this.config.credentials?.ALIEXPRESS_APP_KEY;
    throw new NotImplementedLiveError(
      key ? `aliexpress.${op} (Open Platform 連携は実装予定)` : `aliexpress.${op} (ALIEXPRESS_APP_KEY 未設定)`,
    );
  }

  async searchProducts(query: ProductSearchQuery): Promise<SupplierProduct[]> {
    if (this.live) this.ensureLive("searchProducts");
    return [this.mockProduct(query.externalId ?? "AE-0001")];
  }

  async getProduct(externalId: string): Promise<SupplierProduct> {
    if (this.live) this.ensureLive("getProduct");
    return this.mockProduct(externalId);
  }

  async getInventory(externalId: string): Promise<InventorySnapshot> {
    if (this.live) this.ensureLive("getInventory");
    return { externalId, stock: 200, cost: 3.2, costCurrency: "USD", fetchedAt: new Date() };
  }

  async placeOrder(order: SupplierOrderRequest): Promise<SupplierOrderResult> {
    if (this.live) this.ensureLive("placeOrder");
    return {
      supplierOrderId: `ae_mock_${order.externalProductId}_${Date.now()}`,
      accepted: true,
      cost: 3.2 * order.quantity,
      message: "mock accepted",
    };
  }

  async getShipment(supplierOrderId: string): Promise<ShipmentInfo> {
    if (this.live) this.ensureLive("getShipment");
    return { carrier: "AliExpress Standard", trackingNumber: `AE${supplierOrderId}`, shippedAt: new Date() };
  }

  private mockProduct(externalId: string): SupplierProduct {
    return {
      supplierId: this.id,
      externalId,
      title: `AliExpress サンプル猫グッズ ${externalId}`,
      description: "モックデータ。live モードで AliExpress Open Platform API に差し替え。",
      imageUrls: ["https://example.com/aliexpress/sample.jpg"],
      cost: 3.2,
      costCurrency: "USD",
      stock: 200,
      skus: [],
      sourceUrl: "https://www.aliexpress.com/item/sample.html",
    };
  }
}
