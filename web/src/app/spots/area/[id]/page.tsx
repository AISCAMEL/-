import Link from "next/link";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import {
  getAreaById,
  spotsInArea,
  areasInRegion,
  SPOT_REGION_LABEL,
  SPOT_TYPE_LABEL,
  SPOT_LEVEL_LABEL,
  SPOT_LEVEL_STYLE,
  CROWD_LABEL,
  compass8,
  offshoreWindLabel,
} from "@/lib/spots";
import { Suspense } from "react";
import { LiveForecast } from "@/components/spots/live-forecast";
import { AiWaveAdvice, AiWaveAdviceSkeleton } from "@/components/spots/ai-wave-advice";
import { JmaWarnings } from "@/components/spots/jma-warnings";

export const revalidate = 600;

type Props = { params: Promise<{ id: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { id } = await params;
  const area = getAreaById(id);
  if (!area) return { title: "サーフエリア" };
  return {
    title: `${area.name}（${area.prefecture}）のサーフポイント・波情報`,
    description: area.summary,
  };
}

export default async function AreaDetailPage({ params }: Props) {
  const { id } = await params;
  const area = getAreaById(id);
  if (!area) notFound();
  const spots = spotsInArea(area.id);
  const others = areasInRegion(area.region).filter((a) => a.id !== area.id);

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      <nav className="text-xs text-navy/50">
        <Link href="/spots" className="hover:text-ocean">全国</Link>
        <span className="mx-1">/</span>
        <Link href={`/spots?region=${area.region}`} className="hover:text-ocean">
          {SPOT_REGION_LABEL[area.region]}
        </Link>
      </nav>

      {/* Hero */}
      <div className="mt-3 rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">
          {SPOT_REGION_LABEL[area.region]}・{area.prefecture}
        </p>
        <h1 className="mt-2 text-2xl font-semibold">{area.name}エリア</h1>
        <p className="mt-3 text-sm leading-relaxed text-sand/95">{area.summary}</p>
      </div>

      {/* エリアの特徴 */}
      <dl className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Stat label="海の向き" value={`${compass8(area.facingDeg)}向き`} />
        <Stat label="良いうねり" value={area.goodSwell} />
        <Stat label="オフショア" value={offshoreWindLabel(area.facingDeg)} />
        <Stat label="シーズン" value={area.season} />
      </dl>

      <p className="mt-4 rounded-xl border border-ocean/20 bg-white px-4 py-3 text-sm leading-relaxed text-navy/75">
        💡 {area.tips}
      </p>

      {/* 気象庁の警報・注意報 */}
      <div className="mt-6">
        <JmaWarnings spotIds={spots.map((s) => s.id)} />
      </div>

      {/* AI波解説（Claude） */}
      <div className="mt-6">
        <Suspense fallback={<AiWaveAdviceSkeleton />}>
          <AiWaveAdvice placeId={area.id} />
        </Suspense>
      </div>

      {/* ライブ波予報 */}
      <div className="mt-6">
        <LiveForecast
          lat={area.lat}
          lon={area.lon}
          facingDeg={area.facingDeg}
          title={`${area.name}のいまの波・風`}
        />
      </div>

      {/* ポイント一覧 */}
      <section className="mt-8">
        <div className="flex items-baseline justify-between border-b border-navy/10 pb-2">
          <h2 className="text-lg font-semibold text-navy">このエリアのポイント</h2>
          <span className="text-xs text-navy/40">{spots.length}ポイント</span>
        </div>
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          {spots.map((s) => (
            <Link
              key={s.id}
              href={`/spots/${s.id}`}
              className={`flex flex-col rounded-2xl border bg-white p-4 shadow-sm transition hover:border-ocean/40 ${
                s.home ? "border-teal/50 ring-1 ring-teal/30" : "border-navy/10"
              }`}
            >
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="font-semibold text-navy">
                    {s.name}
                    {s.home ? (
                      <span className="ml-2 rounded-full bg-teal px-2 py-0.5 text-[0.6rem] font-semibold text-navy">
                        拠点
                      </span>
                    ) : null}
                  </p>
                  {s.reading ? <p className="text-[0.7rem] text-navy/45">{s.reading}</p> : null}
                </div>
                <span
                  className={`shrink-0 rounded-full px-2.5 py-0.5 text-[0.65rem] font-medium ${SPOT_LEVEL_STYLE[s.level]}`}
                >
                  {SPOT_LEVEL_LABEL[s.level]}
                </span>
              </div>
              <p className="mt-2 text-xs leading-relaxed text-navy/65">{s.summary}</p>
              <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.7rem] text-navy/50">
                <span>🌊 {SPOT_TYPE_LABEL[s.type]}</span>
                <span>📅 {s.bestSeason}</span>
                <span>👥 {CROWD_LABEL[s.crowd]}</span>
                <span className="ml-auto font-medium text-ocean">波予報・詳細 →</span>
              </div>
            </Link>
          ))}
        </div>
      </section>

      {/* 同じ地方の他エリア */}
      {others.length > 0 ? (
        <section className="mt-8">
          <h2 className="text-base font-semibold text-navy">
            {SPOT_REGION_LABEL[area.region]}の他のエリア
          </h2>
          <div className="mt-3 flex flex-wrap gap-2">
            {others.map((a) => (
              <Link
                key={a.id}
                href={`/spots/area/${a.id}`}
                className="rounded-full border border-navy/15 bg-white px-3.5 py-1.5 text-sm text-navy/70 transition hover:border-ocean"
              >
                {a.name}
                <span className="ml-1 text-[0.65rem] text-navy/40">{a.prefecture}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      <p className="mt-8 text-center text-xs text-navy/40">
        概要・座標は一般公開情報にもとづく目安です。実際のコンディション・アクセス・ローカルルールは現地や専門サイトでご確認ください。
      </p>
      <p className="mt-4 text-center text-xs">
        <Link href="/spots" className="text-ocean hover:underline">← 全国サーフポイントへ戻る</Link>
      </p>
    </main>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl border border-navy/10 bg-white p-3 text-center shadow-sm">
      <dt className="text-[0.65rem] text-navy/45">{label}</dt>
      <dd className="mt-1 text-sm font-semibold text-navy">{value}</dd>
    </div>
  );
}
