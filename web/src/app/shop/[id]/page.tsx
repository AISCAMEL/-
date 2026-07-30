/* eslint-disable @next/next/no-img-element */
import Link from "next/link";
import { notFound } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { PRODUCT_CATEGORY_LABEL, yen, type ProductCategory } from "@/lib/shop";
import { AddToCart } from "@/components/shop/add-to-cart";
import { DEMO, demoProducts } from "@/lib/demo";

type Props = { params: Promise<{ id: string }> };

type Product = {
  id: string;
  name: string;
  description: string | null;
  category: ProductCategory;
  price: number;
  shipping_fee: number;
  lead_time_text: string;
  image_url: string | null;
};

export default async function ProductDetail({ params }: Props) {
  const { id } = await params;

  let p: Product | null = null;
  if (DEMO) {
    p = (demoProducts.find((x) => x.id === id) as unknown as Product) ?? null;
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("products")
      .select("id, name, description, category, price, shipping_fee, lead_time_text, image_url")
      .eq("id", id)
      .single();
    p = (data as unknown as Product) ?? null;
  }
  if (!p) notFound();

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      <Link href="/shop" className="text-sm text-ocean hover:underline">
        ← ショップへ
      </Link>

      <div className="mt-4 grid gap-6 md:grid-cols-2">
        <img
          src={p.image_url ?? ""}
          alt={p.name}
          className="aspect-square w-full rounded-2xl border border-navy/10 object-cover"
        />

        <div>
          <span className="text-xs text-ocean">{PRODUCT_CATEGORY_LABEL[p.category]}</span>
          <h1 className="mt-1 text-xl font-semibold text-navy">{p.name}</h1>
          <p className="mt-3 text-2xl font-semibold text-navy">{yen(p.price)}</p>
          <p className="text-xs text-navy/50">税込・送料 {yen(p.shipping_fee)}</p>

          {p.description ? (
            <p className="mt-4 whitespace-pre-wrap text-sm text-navy/75">{p.description}</p>
          ) : null}

          {/* 納期（具体・即納表現なし） */}
          <div className="mt-4 rounded-xl bg-white p-4 text-sm">
            <p className="font-medium text-navy">お届けについて</p>
            <p className="mt-1 text-navy/70">🚚 {p.lead_time_text}</p>
            <p className="mt-2 text-xs text-navy/50">
              受注制のため、ご注文後に仕入先在庫を確認し、確保でき次第に受注確定します。
              欠品時は代替のご提案または返金いたします（「在庫あり」を示すものではありません）。
            </p>
          </div>

          <div className="mt-6">
            <AddToCart
              line={{ id: p.id, name: p.name, price: p.price, qty: 1, image_url: p.image_url, shipping_fee: p.shipping_fee }}
            />
          </div>

          <p className="mt-4 text-xs text-navy/40">
            <Link href="/legal/tokushoho" className="text-ocean hover:underline">特定商取引法表記</Link>
            {" ・ "}
            <Link href="/legal/terms" className="text-ocean hover:underline">返品・キャンセル条件</Link>
            {" "}をご確認ください。
          </p>
        </div>
      </div>
    </main>
  );
}
