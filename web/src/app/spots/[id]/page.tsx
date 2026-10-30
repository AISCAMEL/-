import Link from "next/link";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import {
  getSpotById,
  getAreaById,
  spotsInArea,
  SPOT_REGION_LABEL,
  SPOT_TYPE_LABEL,
  SPOT_LEVEL_LABEL,
  BOTTOM_LABEL,
  CROWD_LABEL,
  bottomOf,
  cautionsFor,
  compass8,
  facingOf,
  offshoreWindLabel,
} from "@/lib/spots";
import { Suspense } from "react";
import { LiveForecast } from "@/components/spots/live-forecast";
import { AiWaveAdvice, AiWaveAdviceSkeleton } from "@/components/spots/ai-wave-advice";
import { JmaWarnings } from "@/components/spots/jma-warnings";

// 警報・注意報（10分）と波予報（30分）に合わせて10分ごとに再生成（ビルド時に全ポイント分を一斉取得しない）
export const revalidate = 600;

type Props = { params: Promise<{ id: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { id } = await params;
  const spot = getSpotById(id);
  if (!spot) return { title: "サーフポイント" };
  return {
    title: `${spot.name}（${spot.prefecture}）の波情報・ポイント概要`,
    description: spot.summary,
  };
}

export default async function SpotDetailPage({ params }: Props) {
  const { id } = await params;
  const spot = getSpotById(id);
  if (!spot) notFound();
  const area = getAreaById(spot.area);
  const facing = facingOf(spot, area);
  const siblings = spotsInArea(spot.area).filter((s) => s.id !== spot.id);
  const cautions = cautionsFor(spot);

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      {/* パンくず */}
      <nav className="flex flex-wrap items-center text-xs text-navy/50">
        <Link href="/spots" className="hover:text-ocean">全国</Link>
        <span className="mx-1">/</span>
        <Link href={`/spots?region=${spot.region}`} className="hover:text-ocean">
          {SPOT_REGION_LABEL[spot.region]}
        </Link>
        {area ? (
          <>
            <span className="mx-1">/</span>
            <Link href={`/spots/area/${area.id}`} className="hover:text-ocean">
              {area.name}
            </Link>
          </>
        ) : null}
      </nav>

      {/* Hero */}
      <div className="mt-3 rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">
          {area?.name ?? SPOT_REGION_LABEL[spot.region]}・{spot.prefecture}
        </p>
        <h1 className="mt-2 flex flex-wrap items-center gap-2 text-2xl font-semibold">
          {spot.name}
          {spot.home ? (
            <span className="rounded-full bg-teal px-2.5 py-0.5 text-[0.7rem] font-semibold text-navy">
              拠点
            </span>
          ) : null}
        </h1>
        {spot.reading ? <p className="mt-1 text-sm text-sand/80">{spot.reading}</p> : null}
        <p className="mt-3 text-sm leading-relaxed text-sand/95">{spot.summary}</p>
      </div>

      {/* 基本情報 */}
      <dl className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <Stat label="タイプ" value={`🌊 ${SPOT_TYPE_LABEL[spot.type]}`} />
        <Stat label="レベル" value={SPOT_LEVEL_LABEL[spot.level]} />
        <Stat label="ベストシーズン" value={spot.bestSeason} />
        <Stat label="海底" value={BOTTOM_LABEL[bottomOf(spot)]} />
        <Stat label="混雑度" value={CROWD_LABEL[spot.crowd]} />
        <Stat label="海の向き" value={`${compass8(facing)}向き`} />
      </dl>

      {/* 気象庁の警報・注意報 */}
      <div className="mt-6">
        <JmaWarnings spotIds={[spot.id]} />
      </div>

      {/* AI波解説（Claude） */}
      <div className="mt-6">
        <Suspense fallback={<AiWaveAdviceSkeleton />}>
          <AiWaveAdvice placeId={spot.id} />
        </Suspense>
      </div>

      {/* ライブ波予報 */}
      <div className="mt-6">
        <LiveForecast lat={spot.lat} lon={spot.lon} facingDeg={facing} />
      </div>

      {/* 波が良くなる条件 */}
      <section className="mt-6 rounded-2xl border border-navy/10 bg-white p-5 shadow-sm">
        <h2 className="text-base font-semibold text-navy">波が良くなる条件</h2>
        <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
          <Cond label="良いうねり" value={area?.goodSwell ?? "—"} />
          <Cond label="オフショア（面が整う風）" value={offshoreWindLabel(facing)} />
          <Cond label="エリアのシーズン" value={area?.season ?? spot.bestSeason} />
        </dl>
        {area ? (
          <p className="mt-3 rounded-xl bg-foam px-3 py-2 text-xs leading-relaxed text-navy/70">
            💡 {area.tips}
          </p>
        ) : null}
      </section>

      {/* 概要 */}
      {spot.details ? (
        <section className="mt-6 rounded-2xl border border-navy/10 bg-white p-5 shadow-sm">
          <h2 className="text-base font-semibold text-navy">ポイント概要</h2>
          <p className="mt-2 text-sm leading-relaxed text-navy/75">{spot.details}</p>
        </section>
      ) : null}

      {/* 注意点 */}
      {cautions.length > 0 ? (
        <section className="mt-6 rounded-2xl border border-ocean/20 bg-white p-5 shadow-sm">
          <h2 className="text-base font-semibold text-navy">入る前の注意点</h2>
          <ul className="mt-2 space-y-1.5 text-sm text-navy/75">
            {cautions.map((c) => (
              <li key={c} className="flex gap-2">
                <span className="text-ocean">⚠︎</span>
                <span>{c}</span>
              </li>
            ))}
          </ul>
          <p className="mt-3 text-xs">
            <Link href="/rules" className="text-ocean hover:underline">海のルールを確認する →</Link>
          </p>
        </section>
      ) : null}

      {/* 拠点なら関連導線 */}
      {spot.home ? (
        <div className="mt-6 grid gap-3 sm:grid-cols-2">
          <Link
            href="/waves"
            className="flex items-center justify-between rounded-2xl border border-teal/30 bg-teal/10 px-5 py-4 transition hover:bg-teal/20"
          >
            <div>
              <p className="text-sm font-semibold text-navy">岩沢の波情報（詳しく）</p>
              <p className="mt-0.5 text-xs text-navy/60">時間帯別の波・ローカルの声</p>
            </div>
            <span className="text-teal">→</span>
          </Link>
          <Link
            href="/partners"
            className="flex items-center justify-between rounded-2xl border border-ocean/20 bg-white px-5 py-4 transition hover:border-ocean/40"
          >
            <div>
              <p className="text-sm font-semibold text-navy">岩沢まわり</p>
              <p className="mt-0.5 text-xs text-navy/60">レンタカー・宿泊・食べる</p>
            </div>
            <span className="text-ocean">→</span>
          </Link>
        </div>
      ) : null}

      {/* 同じエリア */}
      {area && siblings.length > 0 ? (
        <section className="mt-8">
          <h2 className="text-base font-semibold text-navy">{area.name}の他のポイント</h2>
          <div className="mt-3 flex flex-wrap gap-2">
            {siblings.map((s) => (
              <Link
                key={s.id}
                href={`/spots/${s.id}`}
                className="rounded-full border border-navy/15 bg-white px-3.5 py-1.5 text-sm text-navy/70 transition hover:border-ocean"
              >
                {s.name}
                <span className="ml-1 text-[0.65rem] text-navy/40">{SPOT_LEVEL_LABEL[s.level]}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      <p className="mt-8 text-center text-xs text-navy/40">
        概要・座標は一般公開情報にもとづく目安です。実際のコンディション・アクセス・ローカルルールは現地や専門サイトでご確認ください。
      </p>

      <p className="mt-4 flex justify-center gap-4 text-xs">
        {area ? (
          <Link href={`/spots/area/${area.id}`} className="text-ocean hover:underline">
            ← {area.name}エリアへ
          </Link>
        ) : null}
        <Link href="/spots" className="text-ocean hover:underline">全国サーフポイントへ</Link>
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

function Cond({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl bg-foam px-3 py-2">
      <dt className="text-[0.65rem] text-navy/45">{label}</dt>
      <dd className="mt-0.5 font-medium text-navy">{value}</dd>
    </div>
  );
}
