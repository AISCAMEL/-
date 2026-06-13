import Link from "next/link";
import type { Metadata } from "next";
import { CommunityHeader } from "@/components/community/community-header";
import { createClient } from "@/lib/supabase/server";
import {
  getWaveReport,
  waveSizeLabel,
  windLabel,
  compass,
  vibe,
} from "@/lib/waves";

export const metadata: Metadata = {
  title: "波情報｜IWASAWA SURF BASE",
  description: "岩沢海岸の波・風・気温と、ローカルの声。",
};

type LocalWave = {
  id: string;
  body: string;
  created_at: string;
  author: { display_name: string | null } | null;
};

function dayLabel(iso: string) {
  const d = new Date(iso);
  return d.toLocaleDateString("ja-JP", { month: "numeric", day: "numeric", weekday: "short" });
}

export default async function WavesPage() {
  const report = await getWaveReport();

  // ローカルの声（waves カテゴリの投稿）
  const supabase = await createClient();
  const { data } = await supabase
    .from("posts")
    .select("id, body, created_at, author:members!posts_author_id_fkey(display_name)")
    .eq("status", "published")
    .eq("category", "waves")
    .order("created_at", { ascending: false })
    .limit(5);
  const localWaves = (data ?? []) as unknown as LocalWave[];

  return (
    <div className="min-h-screen bg-foam">
      <CommunityHeader />
      <main className="mx-auto max-w-3xl px-4 py-8">
        <h1 className="text-2xl font-semibold text-navy">岩沢海岸の波</h1>
        <p className="mt-1 text-sm text-navy/60">
          数字（Open-Meteo）と、地元のひと言。両方で「今日入れる？」に答えます。
        </p>

        {/* 現況 */}
        {report ? (
          <>
            <section className="mt-6 rounded-2xl bg-ocean-gradient p-6 text-foam">
              <p className="text-sm text-sand/80">いまのコンディション</p>
              <div className="mt-2 flex items-end gap-3">
                <span className="text-3xl font-semibold">
                  {waveSizeLabel(report.now.waveHeight)}
                </span>
                {report.now.waveHeight != null ? (
                  <span className="text-sand/80">
                    {report.now.waveHeight.toFixed(1)}m
                    {report.now.wavePeriod != null
                      ? `・周期${report.now.wavePeriod.toFixed(0)}s`
                      : ""}
                  </span>
                ) : null}
              </div>
              <div className="mt-4 grid grid-cols-3 gap-3 text-sm">
                <Stat label="うねり向き" value={compass(report.now.waveDirection)} />
                <Stat
                  label="風"
                  value={`${compass(report.now.windDirection)}・${windLabel(report.now.windSpeed)}`}
                />
                <Stat
                  label="気温"
                  value={
                    report.now.temperature != null
                      ? `${Math.round(report.now.temperature)}℃`
                      : "—"
                  }
                />
              </div>
              <p className="mt-4 rounded-xl bg-white/10 px-4 py-2.5 text-sm">
                {vibe(report.now).text}
              </p>
            </section>

            {/* 数日予報 */}
            <section className="mt-6">
              <h2 className="text-sm font-semibold text-navy/70">これからの数日</h2>
              <div className="mt-3 grid grid-cols-3 gap-3">
                {report.days.map((d) => (
                  <div
                    key={d.date}
                    className="rounded-2xl border border-navy/10 bg-white p-4 text-center"
                  >
                    <p className="text-xs text-navy/50">{dayLabel(d.date)}</p>
                    <p className="mt-2 text-sm font-semibold text-navy">
                      {waveSizeLabel(d.waveHeightMax)}
                    </p>
                    {d.waveHeightMax != null ? (
                      <p className="text-xs text-navy/50">
                        最大 {d.waveHeightMax.toFixed(1)}m
                      </p>
                    ) : null}
                    {d.tempMax != null ? (
                      <p className="mt-1 text-xs text-navy/40">
                        {Math.round(d.tempMin ?? 0)}〜{Math.round(d.tempMax)}℃
                      </p>
                    ) : null}
                  </div>
                ))}
              </div>
              <p className="mt-2 text-right text-xs text-navy/40">
                データ：Open-Meteo（30分ごとに更新）
              </p>
            </section>
          </>
        ) : (
          <section className="mt-6 rounded-2xl border border-dashed border-navy/20 bg-white p-8 text-center text-sm text-navy/60">
            波データを取得できませんでした（API制限などの可能性）。
            <br />
            下の「ローカルの声」を参考にしてください。
          </section>
        )}

        {/* ローカルの声 */}
        <section className="mt-10">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-navy/70">ローカルの声</h2>
            <Link href="/community?category=waves" className="text-xs text-ocean hover:underline">
              すべて見る →
            </Link>
          </div>
          <div className="mt-3 space-y-3">
            {localWaves.length === 0 ? (
              <p className="rounded-xl border border-navy/10 bg-white p-4 text-sm text-navy/50">
                まだローカルの投稿がありません。Local メンバーの「今日の雰囲気」をお待ちください🌊
              </p>
            ) : (
              localWaves.map((w) => (
                <Link
                  key={w.id}
                  href={`/community/posts/${w.id}`}
                  className="block rounded-xl border border-navy/10 bg-white p-4 transition hover:border-ocean/40"
                >
                  <p className="line-clamp-2 text-sm text-navy/80">{w.body}</p>
                  <p className="mt-2 text-xs text-navy/40">
                    {w.author?.display_name ?? "Local"}
                  </p>
                </Link>
              ))
            )}
          </div>
        </section>
      </main>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl bg-white/10 px-3 py-2">
      <p className="text-[0.7rem] text-sand/70">{label}</p>
      <p className="mt-0.5 font-medium">{value}</p>
    </div>
  );
}
