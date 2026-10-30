import Link from "next/link";
import type { Metadata } from "next";
import {
  SPOT_REGIONS,
  SPOT_REGION_LABEL,
  SPOT_TYPE_LABEL,
  SPOT_LEVEL_LABEL,
  SURF_AREAS,
  SURF_SPOTS,
  areasInRegion,
  spotsInArea,
  compass8,
  offshoreWindLabel,
  isSpotRegion,
  SPOT_LEVEL_STYLE,
  type SurfArea,
  type SurfSpot,
} from "@/lib/spots";

export const metadata: Metadata = {
  title: "全国サーフポイントガイド",
  description:
    "日本の代表的なサーフポイントを地方・エリア別に。海岸の向き・良いうねり・オフショア・シーズン、各ポイントのライブ波予報まで見やすくまとめました。",
};

type Props = { searchParams: Promise<{ region?: string }> };

export default async function SpotsPage({ searchParams }: Props) {
  const { region } = await searchParams;
  const active = region && isSpotRegion(region) ? region : null;

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
          地方 → エリア → ポイントの順に。海の向き・うねり・風・ライブ波予報まで。
        </p>
        <div className="mt-4 flex gap-3 text-center">
          <Count n={SPOT_REGIONS.length} unit="地方" />
          <Count n={SURF_AREAS.length} unit="エリア" />
          <Count n={SURF_SPOTS.length} unit="ポイント" />
        </div>
      </div>

      <p className="mt-4 rounded-xl border border-ocean/20 bg-white px-4 py-3 text-xs text-navy/60">
        ℹ️ 一般に公開されている情報をもとにした<span className="font-medium text-navy/80">概要ガイド</span>です。
        各エリア・ポイントのページで、予報モデルによる<span className="font-medium text-navy/80">ライブの波・風・水温</span>を確認できます。
        実際のコンディション・アクセス・ローカルルールは現地でご確認ください。
      </p>

      {/* 地方の絞り込み */}
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

      {/* 地方 → エリア → ポイント */}
      <div className="mt-8 space-y-12">
        {regionsToShow.map((r) => {
          const areas = areasInRegion(r.key);
          if (areas.length === 0) return null;
          const spotCount = areas.reduce((n, a) => n + spotsInArea(a.id).length, 0);
          return (
            <section key={r.key} id={r.key}>
              <div className="flex items-baseline justify-between border-b border-navy/10 pb-2">
                <h2 className="text-lg font-semibold text-navy">{SPOT_REGION_LABEL[r.key]}</h2>
                <span className="text-xs text-navy/40">
                  {areas.length}エリア・{spotCount}ポイント
                </span>
              </div>
              <div className="mt-4 space-y-4">
                {areas.map((a) => (
                  <AreaCard key={a.id} area={a} spots={spotsInArea(a.id)} />
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

function AreaCard({ area: a, spots }: { area: SurfArea; spots: SurfSpot[] }) {
  const isHome = spots.some((s) => s.home);
  return (
    <article
      className={`overflow-hidden rounded-2xl border bg-white shadow-sm ${
        isHome ? "border-teal/50 ring-1 ring-teal/30" : "border-navy/10"
      }`}
    >
      <Link href={`/spots/area/${a.id}`} className="block p-4 transition hover:bg-foam/60">
        <div className="flex items-start justify-between gap-2">
          <div>
            <p className="text-[0.7rem] text-ocean">{a.prefecture}</p>
            <h3 className="font-semibold text-navy">{a.name}</h3>
          </div>
          <span className="shrink-0 text-xs font-medium text-ocean">エリア詳細・波予報 →</span>
        </div>
        <p className="mt-1.5 text-xs leading-relaxed text-navy/65">{a.summary}</p>
        <dl className="mt-3 grid grid-cols-2 gap-2 text-[0.7rem] sm:grid-cols-4">
          <Fact label="海の向き" value={`${compass8(a.facingDeg)}向き`} />
          <Fact label="良いうねり" value={a.goodSwell} />
          <Fact label="オフショア" value={offshoreWindLabel(a.facingDeg)} />
          <Fact label="シーズン" value={a.season} />
        </dl>
      </Link>

      <ul className="divide-y divide-navy/5 border-t border-navy/10">
        {spots.map((s) => (
          <li key={s.id}>
            <Link
              href={`/spots/${s.id}`}
              className="flex items-center gap-3 px-4 py-2.5 text-sm transition hover:bg-foam/60"
            >
              <span className="min-w-0 flex-1">
                <span className="font-medium text-navy">{s.name}</span>
                {s.home ? (
                  <span className="ml-2 rounded-full bg-teal px-2 py-0.5 text-[0.6rem] font-semibold text-navy">
                    拠点
                  </span>
                ) : null}
                <span className="ml-2 text-[0.7rem] text-navy/45">{SPOT_TYPE_LABEL[s.type]}</span>
              </span>
              <span
                className={`shrink-0 rounded-full px-2.5 py-0.5 text-[0.65rem] font-medium ${SPOT_LEVEL_STYLE[s.level]}`}
              >
                {SPOT_LEVEL_LABEL[s.level]}
              </span>
              <span className="text-ocean">›</span>
            </Link>
          </li>
        ))}
      </ul>
    </article>
  );
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg bg-foam px-2.5 py-1.5">
      <dt className="text-navy/45">{label}</dt>
      <dd className="font-medium text-navy/85">{value}</dd>
    </div>
  );
}

function Count({ n, unit }: { n: number; unit: string }) {
  return (
    <div className="flex-1 rounded-xl bg-white/10 px-2 py-2">
      <p className="text-xl font-semibold">{n}</p>
      <p className="text-[0.65rem] text-sand/80">{unit}</p>
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
