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
 * 送料：商品ごとの shipping_fee を合算する（同一商品は1個口＝数量は掛けない）。
 * 無在庫・受注制で仕入先が別々＝別便になりうるため合算を採用。
 * 表示（クライアント）と確定（サーバ）で同じ関数を使い、値を一致させる。
 */
export function sumShipping(fees: (number | null | undefined)[]): number {
  return fees.reduce<number>(
    (sum, f) => sum + Math.max(0, Math.floor(Number(f) || 0)),
    0,
  );
}

export type CartLine = {
  id: string;
  name: string;
  price: number;
  qty: number;
  image_url?: string | null;
  /** 商品ごとの送料（表示用の概算。確定はサーバが products から再計算） */
  shipping_fee?: number | null;
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
