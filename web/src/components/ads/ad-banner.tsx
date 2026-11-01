/* eslint-disable @next/next/no-img-element */
import Link from "next/link";
import { createClient as createSupabaseClient } from "@supabase/supabase-js";
import { DEMO, demoAds } from "@/lib/demo";
import { AD_ASPECT, AD_SLOTS, type AdPlacement } from "@/lib/ads";

type Ad = {
  id: string;
  advertiser: string;
  title?: string | null;
  image_url: string;
  href: string;
  placement: string;
  starts_at?: string | null;
  ends_at?: string | null;
};

/**
 * 掲載中の広告を取得（ログイン状態に依存しない公開データなので cookie を使わず、5分キャッシュ）。
 * ページのキャッシュ（ISR）を壊さないよう、サーバー用の通常クライアントは使わない。
 */
async function fetchAds(placements: string[]): Promise<Ad[]> {
  if (DEMO) {
    return demoAds.filter((a) => placements.includes(a.placement));
  }
  try {
    const supabase = createSupabaseClient(
      process.env.NEXT_PUBLIC_SUPABASE_URL!,
      process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!,
      {
        auth: { persistSession: false },
        global: { fetch: (input, init) => fetch(input, { ...init, next: { revalidate: 300 } }) },
      },
    );
    const { data } = await supabase
      .from("ad_banners")
      .select("id, advertiser, title, image_url, href, placement, starts_at, ends_at")
      .eq("is_active", true)
      .in("placement", placements)
      .order("sort_order", { ascending: true })
      .limit(20);
    const now = Date.now();
    return ((data ?? []) as Ad[]).filter(
      (a) =>
        (!a.starts_at || Date.parse(a.starts_at) <= now) && (!a.ends_at || Date.parse(a.ends_at) > now),
    );
  } catch {
    return [];
  }
}

/** 同じ枠に複数の広告主がいるときは、表示ごとにランダムで入れ替える */
function pickOne(ads: Ad[]): Ad {
  return ads[Math.floor(Math.random() * ads.length)];
}

/** クリック計測を通してから広告主サイトへ（デモ時はそのまま） */
function adHref(ad: Ad): string {
  return DEMO ? ad.href : `/api/ads/click?id=${encodeURIComponent(ad.id)}`;
}

function AdLabel() {
  return (
    <span className="absolute right-2 top-2 z-10 rounded bg-navy/60 px-1.5 py-0.5 text-[0.6rem] font-medium tracking-wide text-white">
      PR
    </span>
  );
}

function AdCard({ ad, className = "" }: { ad: Ad; className?: string }) {
  return (
    <a
      href={adHref(ad)}
      target="_blank"
      rel="noopener sponsored"
      className={`group relative block overflow-hidden rounded-2xl border border-navy/10 bg-white shadow-sm ${className}`}
      aria-label={`広告：${ad.advertiser}`}
    >
      <AdLabel />
      <img
        src={ad.image_url}
        alt={ad.title ?? ad.advertiser}
        loading="lazy"
        className={`w-full ${AD_ASPECT} object-cover transition group-hover:opacity-95`}
      />
    </a>
  );
}

/** 空き枠（広告募集中）。営業用に、掲載案内ページへ誘導する */
function VacantSlot({
  placement,
  compact = false,
  dark = false,
}: {
  placement: AdPlacement;
  compact?: boolean;
  dark?: boolean;
}) {
  const slot = AD_SLOTS.find((s) => s.key === placement);
  return (
    <Link
      href={`/ads#slot-${placement}`}
      className={`flex ${AD_ASPECT} w-full flex-col items-center justify-center rounded-2xl border-2 border-dashed p-3 text-center transition ${
        dark
          ? "border-white/20 bg-white/5 hover:border-teal/60 hover:bg-white/10"
          : "border-ocean/30 bg-white/70 hover:border-ocean/60 hover:bg-white"
      }`}
    >
      <span
        className={`rounded-full px-2.5 py-0.5 text-[0.65rem] font-semibold ${
          dark ? "bg-teal/15 text-teal" : "bg-ocean/10 text-ocean"
        }`}
      >
        スポンサー募集中
      </span>
      {compact ? null : (
        <>
          <span className="mt-2 text-sm font-semibold text-navy">この場所にバナー広告を掲載できます</span>
          <span className="mt-1 text-[0.7rem] text-navy/55">
            {slot ? `${slot.label}枠・推奨 ${slot.size}px` : "推奨 1600×680px"}
          </span>
        </>
      )}
      <span
        className={`${compact ? "mt-1.5" : "mt-2"} text-[0.7rem] font-medium ${dark ? "text-sand/80" : "text-ocean"}`}
      >
        広告掲載のご案内 →
      </span>
    </Link>
  );
}

/**
 * スポンサー広告枠（横長バナー1枠）。掲載中の広告からランダムに1件。
 * 掲載がなければ「募集中」の枠を出す（vacant={false} で非表示）。
 */
export async function AdBanner({
  placement,
  vacant = true,
  className = "",
}: {
  placement: AdPlacement;
  vacant?: boolean;
  className?: string;
}) {
  const ads = await fetchAds([placement, "all"]);
  if (ads.length === 0) {
    return vacant ? (
      <div className={className}>
        <VacantSlot placement={placement} />
      </div>
    ) : null;
  }
  const ad = pickOne(ads);
  return (
    <div className={className}>
      <AdCard ad={ad} />
    </div>
  );
}

/** フッターのスポンサー欄（最大4社。空きは「募集中」） */
export async function SponsorStrip() {
  const ads = (await fetchAds(["footer"])).slice(0, 4);
  const vacancies = Math.max(0, (ads.length < 2 ? 2 : 4) - ads.length);
  return (
    <div className="mx-auto max-w-4xl px-6 pt-10">
      <p className="text-center text-[0.65rem] font-semibold tracking-[0.35em] text-teal">SPONSORS</p>
      <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {ads.map((a) => (
          <AdCard key={a.id} ad={a} className="rounded-xl" />
        ))}
        {Array.from({ length: vacancies }).map((_, i) => (
          <VacantSlot key={i} placement="footer" compact dark />
        ))}
      </div>
    </div>
  );
}
