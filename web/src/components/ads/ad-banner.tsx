/* eslint-disable @next/next/no-img-element */
import { createClient } from "@/lib/supabase/server";
import { DEMO, demoAds } from "@/lib/demo";

type Ad = {
  id: string;
  advertiser: string;
  image_url: string;
  href: string;
};

/**
 * スポンサー広告枠。placement ごとに掲載中の広告を1件表示する。
 * 必ず「PR」表示・新規タブで業者サイトへ。
 */
export async function AdBanner({
  placement,
}: {
  placement: "feed" | "waves" | "blog";
}) {
  let ad: Ad | null = null;

  if (DEMO) {
    ad =
      demoAds.find((a) => a.placement === placement || a.placement === "all") ??
      null;
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("ad_banners")
      .select("id, advertiser, image_url, href")
      .eq("is_active", true)
      .in("placement", [placement, "all"])
      .order("sort_order", { ascending: true })
      .limit(1);
    ad = ((data ?? []) as unknown as Ad[])[0] ?? null;
  }

  if (!ad) return null;

  return (
    <a
      href={ad.href}
      target="_blank"
      rel="noreferrer sponsored"
      className="group relative block overflow-hidden rounded-2xl border border-navy/10 bg-white shadow-sm"
      aria-label={`広告：${ad.advertiser}`}
    >
      <span className="absolute right-2 top-2 z-10 rounded bg-navy/60 px-1.5 py-0.5 text-[0.6rem] font-medium tracking-wide text-white">
        PR
      </span>
      <img
        src={ad.image_url}
        alt={ad.advertiser}
        className="w-full object-cover transition group-hover:opacity-95"
      />
    </a>
  );
}
