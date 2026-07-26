import type {
  Currency,
  InventorySnapshot,
  ShipmentInfo,
  SupplierOrderRequest,
  SupplierOrderResult,
  SupplierProduct,
} from "@hub/core";
import type { ConnectorConfig, ProductSearchQuery, SupplierConnector } from "../types.js";

const AE_API = "https://api-sg.aliexpress.com/sync";

export class AliExpressConnector implements SupplierConnector {
  readonly id = "aliexpress" as const;

  constructor(private readonly config: ConnectorConfig) {}

  private get live(): boolean {
    return this.config.mode === "live";
  }

  private get appKey(): string | undefined {
    return this.config.credentials?.ALIEXPRESS_APP_KEY;
  }

  private get appSecret(): string | undefined {
    return this.config.credentials?.ALIEXPRESS_APP_SECRET;
  }

  private async apiFetch<T>(method: string, params: Record<string, string>): Promise<T> {
    if (!this.appKey || !this.appSecret) {
      throw new Error("ALIEXPRESS_APP_KEY / ALIEXPRESS_APP_SECRET 未設定");
    }

    const allParams: Record<string, string> = {
      app_key: this.appKey,
      method,
      sign_method: "sha256",
      timestamp: new Date().toISOString().replace("T", " ").slice(0, 19),
      v: "2.0",
      ...params,
    };

    const sign = await this.sign(allParams);
    allParams.sign = sign;

    const url = new URL(AE_API);
    for (const [k, v] of Object.entries(allParams)) {
      url.searchParams.set(k, v);
    }

    const res = await fetch(url);
    if (!res.ok) {
      const body = await res.text().catch(() => "");
      throw new Error(`AliExpress API error ${res.status}: ${body}`);
    }
    const data = (await res.json()) as Record<string, unknown>;
    if (data.error_response) {
      const err = data.error_response as Record<string, string>;
      throw new Error(`AliExpress: ${err.msg ?? err.code}`);
    }
    return data as T;
  }

  private async sign(params: Record<string, string>): Promise<string> {
    const sorted = Object.keys(params).sort();
    const raw = this.appSecret + sorted.map((k) => k + params[k]).join("") + this.appSecret;
    const encoder = new TextEncoder();
    const keyData = encoder.encode(this.appSecret!);
    const msgData = encoder.encode(raw);
    const key = await crypto.subtle.importKey("raw", keyData, { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
    const sig = await crypto.subtle.sign("HMAC", key, msgData);
    return Array.from(new Uint8Array(sig)).map((b) => b.toString(16).padStart(2, "0")).join("").toUpperCase();
  }

  async searchProducts(query: ProductSearchQuery): Promise<SupplierProduct[]> {
    if (!this.live) return [this.mockProduct(query.externalId ?? "AE-0001")];

    const params: Record<string, string> = {
      target_currency: "USD",
      target_language: "ja",
      page_no: String(query.page ?? 1),
      page_size: String(query.pageSize ?? 20),
    };
    if (query.keyword) params.keywords = query.keyword;

    const data = await this.apiFetch<AeSearchResponse>(
      "aliexpress.ds.product.list",
      params,
    );
    return (data.aliexpress_ds_product_list_response?.result?.products ?? []).map(toCoreProduct);
  }

  async getProduct(externalId: string): Promise<SupplierProduct> {
    if (!this.live) return this.mockProduct(externalId);

    const data = await this.apiFetch<AeDetailResponse>(
      "aliexpress.ds.product.get",
      { product_id: externalId, target_currency: "USD", target_language: "ja" },
    );
    const p = data.aliexpress_ds_product_get_response?.result;
    if (!p) throw new Error(`Product ${externalId} not found`);
    return toCoreProduct(p);
  }

  async getInventory(externalId: string): Promise<InventorySnapshot> {
    if (!this.live) {
      return { externalId, stock: 200, cost: 3.2, costCurrency: "USD", fetchedAt: new Date() };
    }

    const product = await this.getProduct(externalId);
    return {
      externalId,
      stock: product.stock,
      cost: product.cost,
      costCurrency: "USD",
      fetchedAt: new Date(),
    };
  }

  async placeOrder(order: SupplierOrderRequest): Promise<SupplierOrderResult> {
    if (!this.live) {
      return {
        supplierOrderId: `ae_mock_${order.externalProductId}_${Date.now()}`,
        accepted: true,
        cost: 3.2 * order.quantity,
        message: "mock accepted",
      };
    }

    const data = await this.apiFetch<AeOrderResponse>(
      "aliexpress.ds.order.create",
      {
        product_id: order.externalProductId,
        product_cnt: String(order.quantity),
        logistics_address: JSON.stringify(order.shipTo),
      },
    );
    const result = data.aliexpress_ds_order_create_response?.result;
    return {
      supplierOrderId: result?.order_id ?? "",
      accepted: result?.is_success ?? false,
      cost: result?.total_cost ?? 0,
      message: result?.error_msg ?? "",
    };
  }

  async getShipment(supplierOrderId: string): Promise<ShipmentInfo> {
    if (!this.live) {
      return { carrier: "AliExpress Standard", trackingNumber: `AE${supplierOrderId}`, shippedAt: new Date() };
    }

    const data = await this.apiFetch<AeShipmentResponse>(
      "aliexpress.ds.tracking.get",
      { order_id: supplierOrderId },
    );
    const info = data.aliexpress_ds_tracking_get_response?.result;
    return {
      carrier: info?.logistics_company ?? "AliExpress",
      trackingNumber: info?.tracking_number ?? "",
      shippedAt: info?.shipped_at ? new Date(info.shipped_at) : new Date(),
    };
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

function toCoreProduct(p: AeProduct): SupplierProduct {
  return {
    supplierId: "aliexpress",
    externalId: String(p.product_id),
    title: p.product_title ?? "",
    description: p.product_title ?? "",
    imageUrls: p.product_main_image_url ? [p.product_main_image_url, ...(p.product_small_image_urls ?? [])] : [],
    cost: parseFloat(p.target_sale_price ?? p.target_original_price ?? "0"),
    costCurrency: (p.target_sale_price_currency ?? "USD") as Currency,
    stock: p.product_stock ?? null,
    skus: (p.ae_item_sku_info_dtos ?? []).map((s) => ({
      externalSkuId: s.sku_id ?? "",
      attributes: s.sku_attr ? { spec: s.sku_attr } : {} as Record<string, string>,
      cost: parseFloat(s.offer_sale_price ?? "0"),
      stock: s.sku_stock ?? null,
    })),
    sourceUrl: p.product_detail_url ?? `https://www.aliexpress.com/item/${p.product_id}.html`,
  };
}

interface AeProduct {
  product_id: number;
  product_title?: string;
  product_main_image_url?: string;
  product_small_image_urls?: string[];
  target_sale_price?: string;
  target_original_price?: string;
  target_sale_price_currency?: string;
  product_stock?: number;
  product_detail_url?: string;
  ae_item_sku_info_dtos?: {
    sku_id?: string;
    sku_attr?: string;
    offer_sale_price?: string;
    sku_stock?: number;
  }[];
}

interface AeSearchResponse {
  aliexpress_ds_product_list_response?: {
    result?: { products?: AeProduct[] };
  };
}

interface AeDetailResponse {
  aliexpress_ds_product_get_response?: {
    result?: AeProduct;
  };
}

interface AeOrderResponse {
  aliexpress_ds_order_create_response?: {
    result?: {
      order_id?: string;
      is_success?: boolean;
      total_cost?: number;
      error_msg?: string;
    };
  };
}

interface AeShipmentResponse {
  aliexpress_ds_tracking_get_response?: {
    result?: {
      logistics_company?: string;
      tracking_number?: string;
      shipped_at?: string;
    };
  };
}
