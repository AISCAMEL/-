import Link from "next/link";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import {
  SURF_SPOTS,
  getSpotById,
  SPOT_REGION_LABEL,
  SPOT_TYPE_LABEL,
  SPOT_LEVEL_LABEL,
} from "@/lib/spots";

type Props = { params: Promise<{ id: string }> };

export function generateStaticParams() {
  return SURF_SPOTS.map((s) => ({ id: s.id }));
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { id } = await params;
  const spot = getSpotById(id);
  if (!spot) return { title: "サーフポイント" };
  return {
    title: `${spot.name}（${spot.prefecture}）`,
    description: spot.summary,
  };
}

export default async function SpotDetailPage({ params }: Props) {
  const { id } = await params;
  const spot = getSpotById(id);
  if (!spot) notFound();

  // 同じエリアの他ポイント
  const siblings = SURF_SPOTS.filter(
    (s) => s.region === spot.region && s.id !== spot.id,
  ).slice(0, 6);

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      {/* パンくず */}
      <nav className="text-xs text-navy/50">
        <Link href="/spots" className="hover:text-ocean">
          全国サーフポイント
        </Link>
        <span className="mx-1">/</span>
        <Link href={`/spots?region=${spot.region}`} className="hover:text-ocean">
          {SPOT_REGION_LABEL[spot.region]}
        </Link>
      </nav>

      {/* Hero */}
      <div className="mt-3 rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">
          {SPOT_REGION_LABEL[spot.region]}・{spot.prefecture}
        </p>
        <h1 className="mt-2 flex flex-wrap items-center gap-2 text-2xl font-semibold">
          {spot.name}
          {spot.home ? (
            <span className="rounded-full bg-teal px-2.5 py-0.5 text-[0.7rem] font-semibold text-navy">
              拠点
            </span>
          ) : null}
        </h1>
        {spot.reading ? (
          <p className="mt-1 text-sm text-sand/80">{spot.reading}</p>
        ) : null}
      </div>

      {/* 基本情報 */}
      <dl className="mt-6 grid grid-cols-3 gap-3">
        <Stat label="タイプ" value={`🌊 ${SPOT_TYPE_LABEL[spot.type]}`} />
        <Stat label="レベル" value={SPOT_LEVEL_LABEL[spot.level]} />
        <Stat label="ベストシーズン" value={spot.bestSeason} />
      </dl>

      {/* 概要 */}
      <section className="mt-6 rounded-2xl border border-navy/10 bg-white p-5 shadow-sm">
        <h2 className="text-base font-semibold text-navy">ポイント概要</h2>
        <p className="mt-2 text-sm leading-relaxed text-navy/75">{spot.summary}</p>
        {spot.details ? (
          <p className="mt-3 text-sm leading-relaxed text-navy/75">{spot.details}</p>
        ) : null}
      </section>

      {/* 拠点なら波情報・関連導線 */}
      {spot.home ? (
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <Link
            href="/waves"
            className="flex items-center justify-between rounded-2xl border border-teal/30 bg-teal/10 px-5 py-4 transition hover:bg-teal/20"
          >
            <div>
              <p className="text-sm font-semibold text-navy">今日の波を見る</p>
              <p className="mt-0.5 text-xs text-navy/60">ライブ波情報に対応</p>
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
      {siblings.length > 0 ? (
        <section className="mt-8">
          <h2 className="text-base font-semibold text-navy">
            {SPOT_REGION_LABEL[spot.region]}の他のポイント
          </h2>
          <div className="mt-3 flex flex-wrap gap-2">
            {siblings.map((s) => (
              <Link
                key={s.id}
                href={`/spots/${s.id}`}
                className="rounded-full border border-navy/15 bg-white px-3.5 py-1.5 text-sm text-navy/70 transition hover:border-ocean"
              >
                {s.name}
                <span className="ml-1 text-[0.65rem] text-navy/40">{s.prefecture}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      <p className="mt-8 text-center text-xs text-navy/40">
        概要は一般公開情報にもとづくガイドです。実際のコンディション・アクセス・ローカルルールは現地や専門サイトでご確認ください。
      </p>

      <p className="mt-4 text-center text-xs">
        <Link href="/spots" className="text-ocean hover:underline">
          ← 全国サーフポイントへ戻る
        </Link>
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
