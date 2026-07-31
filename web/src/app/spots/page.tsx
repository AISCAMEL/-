import Link from "next/link";
import type { Metadata } from "next";
import {
  SPOT_REGIONS,
  SPOT_REGION_LABEL,
  SPOT_TYPE_LABEL,
  SPOT_LEVEL_LABEL,
  SURF_SPOTS,
  isSpotRegion,
  type SpotLevel,
  type SurfSpot,
} from "@/lib/spots";

export const metadata: Metadata = {
  title: "全国サーフポイントガイド",
  description:
    "日本の代表的なサーフポイントをエリア別に。一般に公開されている情報をもとに、初心者から上級者まで見やすくまとめました。",
};

/** レベル別のバッジ色 */
const LEVEL_STYLE: Record<SpotLevel, string> = {
  beginner: "bg-teal/15 text-teal",
  intermediate: "bg-ocean/15 text-ocean",
  advanced: "bg-navy/10 text-navy",
};

type Props = { searchParams: Promise<{ region?: string }> };

export default async function SpotsPage({ searchParams }: Props) {
  const { region } = await searchParams;
  const active = region && isSpotRegion(region) ? region : null;

  // 表示するエリア（絞り込み時は1エリアのみ）
  const regionsToShow = active
    ? SPOT_REGIONS.filter((r) => r.key === active)
    : SPOT_REGIONS;

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      {/* Hero */}
      <div className="rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">SURF POINTS OF JAPAN</p>
        <h1 className="mt-2 text-2xl font-semibold">全国サーフポイントガイド</h1>
        <p className="mt-1 text-sm text-sand/90">
          日本の海を、もっと知る。エリア別に、代表的なポイントを見やすく。
        </p>
      </div>

      <p className="mt-4 rounded-xl border border-ocean/20 bg-white px-4 py-3 text-xs text-navy/60">
        ℹ️ 一般に公開されている情報をもとにした<span className="font-medium text-navy/80">概要ガイド</span>です。
        実際のコンディション・アクセス・ローカルルールは現地や専門サイトでご確認ください。
        ライブの波情報は現在{" "}
        <Link href="/waves" className="text-ocean hover:underline">岩沢海岸</Link>{" "}
        に対応しています。
      </p>

      {/* エリア絞り込み */}
      <nav className="mt-6 flex flex-wrap gap-2">
        <Chip label="すべて" href="/spots" active={!active} />
        {SPOT_REGIONS.map((r) => (
          <Chip
            key={r.key}
            label={r.label}
            href={`/spots?region=${r.key}`}
            active={active === r.key}
          />
        ))}
      </nav>

      {/* エリア別セクション */}
      <div className="mt-8 space-y-10">
        {regionsToShow.map((r) => {
          const spots = SURF_SPOTS.filter((s) => s.region === r.key);
          if (spots.length === 0) return null;
          return (
            <section key={r.key}>
              <div className="flex items-baseline justify-between border-b border-navy/10 pb-2">
                <h2 className="text-lg font-semibold text-navy">
                  {SPOT_REGION_LABEL[r.key]}
                </h2>
                <span className="text-xs text-navy/40">{spots.length}スポット</span>
              </div>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                {spots.map((s) => (
                  <SpotCard key={s.id} spot={s} levelStyle={LEVEL_STYLE[s.level]} />
                ))}
              </div>
            </section>
          );
        })}
      </div>

      <p className="mt-10 text-center text-xs text-navy/40">
        掲載してほしいポイントのご要望は{" "}
        <Link href="/contact" className="text-ocean hover:underline">お問い合わせ</Link>{" "}
        まで。
      </p>
    </main>
  );
}

function SpotCard({ spot: s, levelStyle }: { spot: SurfSpot; levelStyle: string }) {
  return (
    <div
      className={`flex flex-col rounded-2xl border bg-white p-4 shadow-sm transition hover:border-ocean/40 ${
        s.home ? "border-teal/50 ring-1 ring-teal/30" : "border-navy/10"
      }`}
    >
      <div className="flex items-start justify-between gap-2">
        <div>
          <div className="flex items-center gap-2">
            <p className="font-semibold text-navy">{s.name}</p>
            {s.home ? (
              <span className="rounded-full bg-teal px-2 py-0.5 text-[0.6rem] font-semibold text-navy">
                拠点
              </span>
            ) : null}
          </div>
          <p className="text-[0.7rem] text-ocean">{s.prefecture}</p>
        </div>
        <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-[0.65rem] font-medium ${levelStyle}`}>
          {SPOT_LEVEL_LABEL[s.level]}
        </span>
      </div>

      <p className="mt-2 text-xs leading-relaxed text-navy/65">{s.summary}</p>

      <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[0.7rem] text-navy/50">
        <span>🌊 {SPOT_TYPE_LABEL[s.type]}</span>
        <span>📅 ベスト：{s.bestSeason}</span>
      </div>

      {s.home ? (
        <Link
          href="/waves"
          className="mt-3 inline-flex items-center gap-1 text-xs font-medium text-ocean hover:underline"
        >
          今日の波を見る →
        </Link>
      ) : null}
    </div>
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
