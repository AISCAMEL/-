/* eslint-disable @next/next/no-img-element */
import Link from "next/link";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import {
  PRODUCT_CATEGORIES,
  PRODUCT_CATEGORY_LABEL,
  yen,
  isProductCategory,
  type ProductCategory,
} from "@/lib/shop";
import { DEMO, demoProducts } from "@/lib/demo";

export const metadata: Metadata = {
  title: "オンラインショップ｜IWASAWA SURF BASE",
  description: "ボード・ウェット・小物。海の入口につながるギアを、受注制でお届け。",
};

type Row = {
  id: string;
  name: string;
  category: ProductCategory;
  price: number;
  image_url: string | null;
  lead_time_text: string;
};

type Props = { searchParams: Promise<{ category?: string }> };

export default async function ShopPage({ searchParams }: Props) {
  const { category } = await searchParams;
  const active = category && isProductCategory(category) ? category : null;

  let products: Row[] = [];
  if (DEMO) {
    products = (active
      ? demoProducts.filter((p) => p.category === active)
      : demoProducts) as unknown as Row[];
  } else {
    const supabase = await createClient();
    let q = supabase
      .from("products")
      .select("id, name, category, price, image_url, lead_time_text")
      .eq("status", "active")
      .order("sort_order", { ascending: true })
      .limit(60);
    if (active) q = q.eq("category", active);
    const { data } = await q;
    products = (data ?? []) as unknown as Row[];
  }

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      <div className="rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">ONLINE SHOP</p>
        <h1 className="mt-2 text-2xl font-semibold">ギアを、海の入口に。</h1>
        <p className="mt-1 text-sm text-sand/90">
          ボード・ウェット・小物。受注制でお届けします。
        </p>
      </div>

      {/* 道具→ルール→プロ指導→海 の導線 */}
      <Link
        href="/rules"
        className="mt-4 flex items-center justify-between rounded-2xl border border-teal/30 bg-teal/10 px-5 py-4 transition hover:bg-teal/20"
      >
        <div>
          <p className="text-sm font-semibold text-navy">道具が揃ったら、海のルールも。</p>
          <p className="mt-0.5 text-xs text-navy/60">
            知って、プロに習って、海に入る。安全のための第一歩。
          </p>
        </div>
        <span className="shrink-0 text-teal">→</span>
      </Link>

      {/* 無在庫の明示（誤認防止） */}
      <p className="mt-4 rounded-xl border border-ocean/20 bg-white px-4 py-3 text-xs text-navy/60">
        ℹ️ 当ショップは受注制です。ご注文後に仕入先の在庫を確認し、確保でき次第に受注確定・発送します。
        「在庫あり」を示すものではありません。納期は各商品ページに具体的に記載しています。
      </p>

      <nav className="mt-6 flex flex-wrap gap-2">
        <Chip label="すべて" href="/shop" active={!active} />
        {PRODUCT_CATEGORIES.map((c) => (
          <Chip key={c.key} label={c.label} href={`/shop?category=${c.key}`} active={active === c.key} />
        ))}
      </nav>

      <div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
        {products.map((p) => (
          <Link
            key={p.id}
            href={`/shop/${p.id}`}
            className="overflow-hidden rounded-2xl border border-navy/10 bg-white shadow-sm transition hover:border-ocean/40"
          >
            <img src={p.image_url ?? ""} alt={p.name} className="aspect-square w-full object-cover" />
            <div className="p-3">
              <span className="text-[0.65rem] text-ocean">
                {PRODUCT_CATEGORY_LABEL[p.category]}
              </span>
              <p className="mt-0.5 text-sm font-medium text-navy line-clamp-1">{p.name}</p>
              <p className="mt-1 font-semibold text-navy">{yen(p.price)}</p>
              <p className="mt-1 text-[0.65rem] text-navy/40">{p.lead_time_text}</p>
            </div>
          </Link>
        ))}
      </div>

      <p className="mt-8 text-center text-xs text-navy/40">
        価格は税込・送料別です。
        <Link href="/legal/tokushoho" className="text-ocean hover:underline">特定商取引法表記</Link>
        ・返品条件をご確認ください。決済はクレジットカード等（カード情報は当社で保持しません）。
      </p>
    </main>
  );
}

function Chip({ label, href, active }: { label: string; href: string; active: boolean }) {
  return (
    <Link
      href={href}
      className={`rounded-full border px-3.5 py-1.5 text-sm transition ${
        active
          ? "border-ocean bg-ocean text-foam"
          : "border-navy/15 bg-white text-navy/70 hover:border-ocean"
      }`}
    >
      {label}
    </Link>
  );
}
