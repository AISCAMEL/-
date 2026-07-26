import type { SupplierConnector } from "@hub/connectors";
import { dbEnabled, orderRepo, productRepo } from "@hub/db";
import { pushAlert } from "./alert-service.js";

export interface FulfillmentResult {
  orderId: string;
  status: "ordered" | "skipped" | "error";
  supplierOrderId?: string;
  message: string;
}

export async function fulfillOrder(
  orderId: string,
  getSupplier: (id: string) => SupplierConnector | undefined,
): Promise<FulfillmentResult> {
  if (!dbEnabled) {
    return { orderId, status: "skipped", message: "DB未接続のため発注できません" };
  }

  const orders = await orderRepo.listOrders({ take: 100 });
  const order = orders.items.find((o) => o.externalOrderId === orderId || o.id === orderId);
  if (!order) {
    return { orderId, status: "error", message: "注文が見つかりません" };
  }

  if (order.status !== "received") {
    return { orderId, status: "skipped", message: `ステータスが「${order.status}」のため発注スキップ` };
  }

  if (order.supplierOrders.length > 0) {
    return { orderId, status: "skipped", message: "既に仕入れ先に発注済みです" };
  }

  const firstItem = order.items[0];
  if (!firstItem) {
    return { orderId, status: "error", message: "注文に商品がありません" };
  }

  const products = await productRepo.listProducts({ take: 100 });
  const product = products.items.find((p) =>
    p.sourceProduct.externalId === firstItem.externalItemId ||
    p.title === firstItem.sku,
  );

  if (!product) {
    return { orderId, status: "error", message: `商品「${firstItem.sku}」が見つかりません` };
  }

  const supplierId = product.sourceProduct.supplier.kind;
  const externalId = product.sourceProduct.externalId;
  const supplier = getSupplier(supplierId);
  if (!supplier) {
    return { orderId, status: "error", message: `仕入れ先「${supplierId}」のコネクタが見つかりません` };
  }

  try {
    const result = await supplier.placeOrder({
      supplierId: supplierId as "theckb" | "alibaba" | "aliexpress",
      externalProductId: externalId,
      quantity: firstItem.quantity,
      shipTo: {
        name: order.buyerName,
        postalCode: order.buyerPostalCode ?? undefined,
        address: order.buyerAddress ?? undefined,
        phone: order.buyerPhone ?? undefined,
      },
    });

    if (result.accepted) {
      await orderRepo.updateOrderStatus(order.id, "fulfilling");
      pushAlert({
        type: "hot_product",
        title: "自動発注完了",
        message: `注文 ${order.externalOrderId} → ${supplierId} に発注しました (${result.supplierOrderId})`,
        severity: "info",
      });
      return {
        orderId,
        status: "ordered",
        supplierOrderId: result.supplierOrderId,
        message: `${supplierId} に発注完了 (原価: ¥${result.cost})`,
      };
    }

    return { orderId, status: "error", message: `仕入れ先が受付拒否: ${result.message}` };
  } catch (e) {
    return { orderId, status: "error", message: `発注エラー: ${e instanceof Error ? e.message : String(e)}` };
  }
}

export async function autoFulfillAll(
  getSupplier: (id: string) => SupplierConnector | undefined,
): Promise<{ results: FulfillmentResult[]; summary: { ordered: number; skipped: number; errors: number } }> {
  if (!dbEnabled) {
    return { results: [], summary: { ordered: 0, skipped: 0, errors: 0 } };
  }

  const { items: orders } = await orderRepo.listOrders({ take: 100 });
  const pending = orders.filter((o) => o.status === "received" && o.supplierOrders.length === 0);

  const results: FulfillmentResult[] = [];
  for (const order of pending) {
    const result = await fulfillOrder(order.externalOrderId, getSupplier);
    results.push(result);
  }

  return {
    results,
    summary: {
      ordered: results.filter((r) => r.status === "ordered").length,
      skipped: results.filter((r) => r.status === "skipped").length,
      errors: results.filter((r) => r.status === "error").length,
    },
  };
}
