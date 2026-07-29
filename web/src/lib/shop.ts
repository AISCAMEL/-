export type ProductCategory = "board" | "wetsuit" | "accessory" | "apparel";

export const PRODUCT_CATEGORIES: { key: ProductCategory; label: string }[] = [
  { key: "board", label: "ボード" },
  { key: "wetsuit", label: "ウェット" },
  { key: "accessory", label: "小物" },
  { key: "apparel", label: "アパレル" },
];

export const PRODUCT_CATEGORY_LABEL: Record<ProductCategory, string> = Object.fromEntries(
  PRODUCT_CATEGORIES.map((c) => [c.key, c.label]),
) as Record<ProductCategory, string>;

export function isProductCategory(v: string): v is ProductCategory {
  return PRODUCT_CATEGORIES.some((c) => c.key === v);
}

export function yen(n: number): string {
  return `¥${n.toLocaleString("ja-JP")}`;
}

/**
 * 送料（簡易・フラット）。カートが空でなければ一律この額。
 * 表示（クライアント）と確定（サーバ）で同じ値を使うため一元管理する。
 * ※ products.shipping_fee（商品ごとの送料）は現状未使用。将来ポリシーを
 *   決めるときにここを商品別ロジックへ差し替える。
 */
export const FLAT_SHIPPING_FEE = 800;

export function shippingFor(itemCount: number): number {
  return itemCount > 0 ? FLAT_SHIPPING_FEE : 0;
}

export type CartLine = {
  id: string;
  name: string;
  price: number;
  qty: number;
  image_url?: string | null;
};

const KEY = "iwasawa_cart";

export function readCart(): CartLine[] {
  if (typeof window === "undefined") return [];
  try {
    return JSON.parse(localStorage.getItem(KEY) || "[]");
  } catch {
    return [];
  }
}

export function writeCart(lines: CartLine[]) {
  if (typeof window === "undefined") return;
  localStorage.setItem(KEY, JSON.stringify(lines));
  window.dispatchEvent(new Event("cart-changed"));
}

export function addToCart(line: CartLine) {
  const cart = readCart();
  const found = cart.find((l) => l.id === line.id);
  if (found) found.qty += line.qty;
  else cart.push(line);
  writeCart(cart);
}
