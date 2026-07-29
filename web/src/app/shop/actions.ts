"use server";

import { createClient } from "@/lib/supabase/server";
import { DEMO, demoProducts } from "@/lib/demo";
import { shippingFor } from "@/lib/shop";

export type PlaceOrderInput = {
  items: { id: string; qty: number }[];
  name: string;
  email: string;
  addr?: string;
};

export type PlaceOrderResult = { ok: true } | { ok: false; error: string };

/**
 * 注文を確定する（受注制）。
 *
 * セキュリティ上の要点：金額・商品名はクライアントから受け取らず、必ず
 * サーバ側の真実（本番=products テーブル / デモ=デモデータ）から取り直して
 * 再計算する。これにより「total=0」「価格改ざん」の不正注文を防ぐ。
 * 受け取るのは商品IDと数量、連絡先だけ。
 */
export async function placeOrder(input: PlaceOrderInput): Promise<PlaceOrderResult> {
  const name = (input.name ?? "").trim();
  const email = (input.email ?? "").trim();
  if (!name || !email) {
    return { ok: false, error: "お名前とメールアドレスをご記入ください。" };
  }

  // 数量を正規化（1〜99）し、同一商品IDはまとめる
  const wanted = new Map<string, number>();
  for (const it of input.items ?? []) {
    const id = String(it?.id ?? "");
    const qty = Math.max(1, Math.min(99, Math.floor(Number(it?.qty) || 0)));
    if (!id) continue;
    wanted.set(id, (wanted.get(id) ?? 0) + qty);
  }
  if (wanted.size === 0) {
    return { ok: false, error: "カートが空です。" };
  }

  const ids = [...wanted.keys()];

  // 価格・名称はサーバの真実から取得（クライアント値は使わない）
  type Src = { id: string; name: string; price: number };
  let sources: Src[];
  if (DEMO) {
    sources = demoProducts
      .filter((p) => wanted.has(p.id))
      .map((p) => ({ id: p.id, name: p.name, price: p.price }));
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("products")
      .select("id, name, price")
      .in("id", ids)
      .eq("status", "active");
    sources = (data ?? []) as Src[];
  }

  // 販売中の商品として取り直せなかったものがあれば中断（在庫状況変化・不正ID）
  if (sources.length !== wanted.size) {
    return {
      ok: false,
      error: "在庫状況が変わった商品があります。カートを見直してください。",
    };
  }

  const items = sources.map((s) => {
    const qty = wanted.get(s.id) as number;
    const price = Math.max(0, Math.floor(Number(s.price) || 0));
    return { product_id: s.id, name: s.name, price, qty };
  });
  const subtotal = items.reduce((sum, it) => sum + it.price * it.qty, 0);
  const shipping = shippingFor(items.length);
  const total = subtotal + shipping;

  // デモモードは DB 書き込みなし（体験のみ）
  if (DEMO) return { ok: true };

  const supabase = await createClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();

  const { error } = await supabase.from("orders").insert({
    buyer_id: user?.id ?? null,
    contact_name: name,
    contact_email: email,
    shipping_addr: (input.addr ?? "").trim() || null,
    items,
    subtotal,
    shipping_fee: shipping,
    total,
    status: "pending",
  });
  if (error) {
    return {
      ok: false,
      error: "注文の送信に失敗しました。時間をおいて再度お試しください。",
    };
  }
  return { ok: true };
}
