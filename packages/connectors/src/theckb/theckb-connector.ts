import type {
  InventorySnapshot,
  ShipmentInfo,
  SupplierOrderRequest,
  SupplierOrderResult,
  SupplierProduct,
} from "@hub/core";
import type { ConnectorConfig, ProductSearchQuery, SupplierConnector } from "../types.js";

export class TheCkbConnector implements SupplierConnector {
  readonly id = "theckb" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  private get apiBase(): string {
    return this.config.credentials?.THECKB_API_BASE_URL ?? "https://api.theckb.com";
  }

  private get apiKey(): string | undefined {
    return this.config.credentials?.THECKB_API_KEY;
  }

  private async apiFetch<T>(path: string, opts?: RequestInit): Promise<T> {
    if (!this.apiKey) throw new Error("THECKB_API_KEY 未設定");
    const res = await fetch(`${this.apiBase}${path}`, {
      ...opts,
      headers: {
        "Authorization": `Bearer ${this.apiKey}`,
        "Content-Type": "application/json",
        ...opts?.headers,
      },
    });
    if (!res.ok) {
      const body = await res.text().catch(() => "");
      throw new Error(`THE CKB API error ${res.status}: ${body}`);
    }
    return res.json() as Promise<T>;
  }

  async searchProducts(query: ProductSearchQuery): Promise<SupplierProduct[]> {
    if (!this.live) return [this.mockProduct(query.externalId ?? "CKB-0001")];

    const params = new URLSearchParams();
    if (query.keyword) params.set("keyword", query.keyword);
    if (query.externalId) params.set("item_code", query.externalId);
    params.set("page", String(query.page ?? 1));
    params.set("per_page", String(query.pageSize ?? 20));

    const data = await this.apiFetch<CkbSearchResponse>(`/v1/items?${params}`);
    return (data.items ?? []).map(toCoreProduct);
  }

  async getProduct(externalId: string): Promise<SupplierProduct> {
    if (!this.live) return this.mockProduct(externalId);

    const data = await this.apiFetch<{ item: CkbItem }>(`/v1/items/${encodeURIComponent(externalId)}`);
    return toCoreProduct(data.item);
  }

  async getInventory(externalId: string): Promise<InventorySnapshot> {
    if (!this.live) {
      return { externalId, stock: 45, cost: 32, costCurrency: "CNY", fetchedAt: new Date() };
    }

    const data = await this.apiFetch<{ item: CkbItem }>(`/v1/items/${encodeURIComponent(externalId)}`);
    return {
      externalId,
      stock: data.item.stock ?? null,
      cost: data.item.price,
      costCurrency: "CNY",
      fetchedAt: new Date(),
    };
  }

  async placeOrder(order: SupplierOrderRequest): Promise<SupplierOrderResult> {
    if (!this.live) {
      return {
        supplierOrderId: `ckb_mock_${order.externalProductId}_${Date.now()}`,
        accepted: true,
        cost: 32 * order.quantity,
        message: "mock accepted",
      };
    }

    const data = await this.apiFetch<CkbOrderResponse>("/v1/orders", {
      method: "POST",
      body: JSON.stringify({
        item_code: order.externalProductId,
        quantity: order.quantity,
        shipping_address: order.shipTo,
      }),
    });
    return {
      supplierOrderId: data.order_id,
      accepted: data.status === "accepted",
      cost: data.total_cost,
      message: data.message ?? "",
    };
  }

  async getShipment(supplierOrderId: string): Promise<ShipmentInfo> {
    if (!this.live) {
      return { carrier: "CKB Logistics", trackingNumber: `CKB${supplierOrderId}`, shippedAt: new Date() };
    }

    const data = await this.apiFetch<CkbShipmentResponse>(
      `/v1/orders/${encodeURIComponent(supplierOrderId)}/shipment`,
    );
    return {
      carrier: data.carrier,
      trackingNumber: data.tracking_number,
      shippedAt: new Date(data.shipped_at),
    };
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

function toCoreProduct(item: CkbItem): SupplierProduct {
  return {
    supplierId: "theckb",
    externalId: item.item_code,
    title: item.title,
    description: item.description ?? "",
    imageUrls: item.images ?? [],
    cost: item.price,
    costCurrency: "CNY",
    stock: item.stock ?? null,
    skus: (item.variations ?? []).map((v) => ({
      externalSkuId: v.sku_code,
      attributes: v.attributes ?? {},
      cost: v.price ?? item.price,
      stock: v.stock ?? null,
    })),
    sourceUrl: item.url ?? `https://www.theckb.com/item/${item.item_code}`,
  };
}

interface CkbItem {
  item_code: string;
  title: string;
  description?: string;
  price: number;
  stock?: number;
  images?: string[];
  url?: string;
  variations?: {
    sku_code: string;
    attributes?: Record<string, string>;
    price?: number;
    stock?: number;
  }[];
}

interface CkbSearchResponse {
  items?: CkbItem[];
  total?: number;
}

interface CkbOrderResponse {
  order_id: string;
  status: string;
  total_cost: number;
  message?: string;
}

interface CkbShipmentResponse {
  carrier: string;
  tracking_number: string;
  shipped_at: string;
}
