import type {
  InventorySnapshot,
  ShipmentInfo,
  SupplierOrderRequest,
  SupplierOrderResult,
  SupplierProduct,
} from "@hub/core";
import type { ConnectorConfig, ProductSearchQuery, SupplierConnector } from "../types.js";

const ALIBABA_API = "https://openapi.alibaba.com/param2/2";

export class AlibabaConnector implements SupplierConnector {
  readonly id = "alibaba" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  private get accessToken(): string | undefined {
    return this.config.credentials?.ALIBABA_ACCESS_TOKEN;
  }

  private async apiFetch<T>(method: string, params: Record<string, string>): Promise<T> {
    if (!this.accessToken) throw new Error("ALIBABA_ACCESS_TOKEN 未設定");
    const url = new URL(`${ALIBABA_API}/${method}`);
    url.searchParams.set("access_token", this.accessToken);
    for (const [k, v] of Object.entries(params)) {
      url.searchParams.set(k, v);
    }

    const res = await fetch(url);
    if (!res.ok) {
      const body = await res.text().catch(() => "");
      throw new Error(`Alibaba API error ${res.status}: ${body}`);
    }
    const data = (await res.json()) as Record<string, unknown>;
    if (data.error_code) {
      throw new Error(`Alibaba API: ${(data.error_message as string) ?? data.error_code}`);
    }
    return data as T;
  }

  async searchProducts(query: ProductSearchQuery): Promise<SupplierProduct[]> {
    if (!this.live) return [this.mockProduct(query.externalId ?? "ALI-0001")];

    const params: Record<string, string> = {};
    if (query.keyword) params.keyword = query.keyword;
    if (query.externalId) params.productId = query.externalId;
    params.pageNo = String(query.page ?? 1);
    params.pageSize = String(query.pageSize ?? 20);

    const data = await this.apiFetch<AlibabaSearchResponse>(
      "com.alibaba.product:alibaba.product.list-1",
      params,
    );
    return (data.result?.products ?? []).map(toCoreProduct);
  }

  async getProduct(externalId: string): Promise<SupplierProduct> {
    if (!this.live) return this.mockProduct(externalId);

    const data = await this.apiFetch<AlibabaDetailResponse>(
      "com.alibaba.product:alibaba.product.get-1",
      { productId: externalId },
    );
    if (!data.result) throw new Error(`Product ${externalId} not found`);
    return toCoreProduct(data.result);
  }

  async getInventory(externalId: string): Promise<InventorySnapshot> {
    if (!this.live) {
      return { externalId, stock: 120, cost: 18.5, costCurrency: "CNY", fetchedAt: new Date() };
    }

    const product = await this.getProduct(externalId);
    return {
      externalId,
      stock: product.stock,
      cost: product.cost,
      costCurrency: "CNY",
      fetchedAt: new Date(),
    };
  }

  async placeOrder(order: SupplierOrderRequest): Promise<SupplierOrderResult> {
    if (!this.live) {
      return {
        supplierOrderId: `ali_mock_${order.externalProductId}_${Date.now()}`,
        accepted: true,
        cost: 18.5 * order.quantity,
        message: "mock accepted",
      };
    }

    const data = await this.apiFetch<AlibabaOrderResponse>(
      "com.alibaba.trade:alibaba.trade.order.create-1",
      {
        productId: order.externalProductId,
        quantity: String(order.quantity),
      },
    );
    return {
      supplierOrderId: data.result?.orderId ?? "",
      accepted: !!data.result?.orderId,
      cost: data.result?.totalAmount ?? 0,
      message: data.result?.message ?? "",
    };
  }

  async getShipment(supplierOrderId: string): Promise<ShipmentInfo> {
    if (!this.live) {
      return { carrier: "MockExpress", trackingNumber: `TRK${supplierOrderId}`, shippedAt: new Date() };
    }

    const data = await this.apiFetch<AlibabaShipmentResponse>(
      "com.alibaba.logistics:alibaba.trade.logistics.get-1",
      { orderId: supplierOrderId },
    );
    const info = data.result?.logisticsInfo;
    return {
      carrier: info?.companyName ?? "Unknown",
      trackingNumber: info?.trackingNumber ?? "",
      shippedAt: info?.shippedAt ? new Date(info.shippedAt) : new Date(),
    };
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

function toCoreProduct(p: AlibabaProduct): SupplierProduct {
  return {
    supplierId: "alibaba",
    externalId: String(p.productId),
    title: p.subject ?? "",
    description: p.description ?? "",
    imageUrls: p.imageUrls ?? (p.mainImage ? [p.mainImage] : []),
    cost: p.referencePrice ?? 0,
    costCurrency: "CNY",
    stock: p.amountOnSale ?? null,
    skus: (p.skuInfos ?? []).map((s) => ({
      externalSkuId: String(s.skuId),
      attributes: s.attributes ?? {},
      cost: s.price ?? p.referencePrice ?? 0,
      stock: s.amountOnSale ?? null,
    })),
    sourceUrl: p.productUrl ?? `https://www.alibaba.com/product-detail/${p.productId}.html`,
  };
}

interface AlibabaProduct {
  productId: number;
  subject?: string;
  description?: string;
  mainImage?: string;
  imageUrls?: string[];
  referencePrice?: number;
  amountOnSale?: number;
  productUrl?: string;
  skuInfos?: {
    skuId: number;
    attributes?: Record<string, string>;
    price?: number;
    amountOnSale?: number;
  }[];
}

interface AlibabaSearchResponse {
  result?: { products?: AlibabaProduct[] };
}

interface AlibabaDetailResponse {
  result?: AlibabaProduct;
}

interface AlibabaOrderResponse {
  result?: { orderId?: string; totalAmount?: number; message?: string };
}

interface AlibabaShipmentResponse {
  result?: {
    logisticsInfo?: {
      companyName?: string;
      trackingNumber?: string;
      shippedAt?: string;
    };
  };
}
