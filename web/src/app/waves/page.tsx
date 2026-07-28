import Link from "next/link";
import type { Metadata } from "next";
import { CommunityHeader } from "@/components/community/community-header";
import { createClient } from "@/lib/supabase/server";
import {
  getWaveReport,
  waveSizeLabel,
  windLabel,
  compass,
  weatherLabel,
  wetsuitHint,
  vibe,
  hhmm,
  type WaveHour,
} from "@/lib/waves";
import { DEMO, demoWaveReport, demoLocalWaves } from "@/lib/demo";
import { AdBanner } from "@/components/ads/ad-banner";

export const metadata: Metadata = {
  title: "波情報｜IWASAWA SURF BASE",
  description: "岩沢海岸の天気・波・風・水温と、ローカルの声。",
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
  const report = DEMO ? demoWaveReport : await getWaveReport();

  let localWaves: LocalWave[] = [];
  if (DEMO) {
    localWaves = demoLocalWaves.map((w) => ({
      id: w.id,
      body: w.body,
      created_at: "2026-06-16T00:00:00+09:00",
      author: { display_name: w.author },
    }));
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("posts")
      .select("id, body, created_at, author:members!posts_author_id_fkey(display_name)")
      .eq("status", "published")
      .eq("category", "waves")
      .order("created_at", { ascending: false })
      .limit(5);
    localWaves = (data ?? []) as unknown as LocalWave[];
  }

  const w = report ? weatherLabel(report.now.weatherCode) : null;

  return (
    <div className="min-h-screen bg-foam">
      <CommunityHeader />
      <main className="mx-auto max-w-3xl px-4 py-8">
        <h1 className="text-2xl font-semibold text-navy">岩沢海岸の波</h1>
        <p className="mt-1 text-sm text-navy/60">
          天気・波・水温（Open-Meteo）と、地元のひと言。「今日入れる？」に答えます。
        </p>

        {report ? (
          <>
            <section className="mt-6 rounded-2xl bg-ocean-gradient p-6 text-foam">
              <div className="flex items-start justify-between">
                <div>
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
                </div>
                {w ? (
                  <div className="text-right">
                    <div className="text-2xl">{w.icon}</div>
                    <div className="text-xs text-sand/80">{w.text}</div>
                  </div>
                ) : null}
              </div>

              <div className="mt-4 grid grid-cols-4 gap-2 text-sm">
                <Stat label="うねり" value={compass(report.now.waveDirection)} />
                <Stat
                  label="風"
                  value={`${compass(report.now.windDirection)}・${windLabel(report.now.windSpeed)}`}
                />
                <Stat
                  label="気温"
                  value={report.now.temperature != null ? `${Math.round(report.now.temperature)}℃` : "—"}
                />
                <Stat
                  label="水温"
                  value={report.now.waterTemp != null ? `${Math.round(report.now.waterTemp)}℃` : "—"}
                />
              </div>

              <p className="mt-4 rounded-xl bg-white/10 px-4 py-2.5 text-sm">
                {vibe(report.now).text}
              </p>
              {report.now.waterTemp != null ? (
                <p className="mt-2 text-xs text-sand/80">
                  🩱 ウェット目安：{wetsuitHint(report.now.waterTemp)}
                </p>
              ) : null}
            </section>

            {report.today.length > 0 ? (
              <section className="mt-6">
                <h2 className="text-sm font-semibold text-navy/70">今日の波の推移</h2>
                <div className="mt-3 rounded-2xl border border-navy/10 bg-white p-4">
                  <HourlyBars hours={report.today} />
                </div>
              </section>
            ) : null}

            <section className="mt-6">
              <h2 className="text-sm font-semibold text-navy/70">これからの数日</h2>
              <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                {report.days.map((d, i) => {
                  const dw = weatherLabel(d.weatherCode);
                  return (
                    <div
                      key={d.date}
                      className={`rounded-2xl border bg-white p-4 text-center ${
                        i === 0 ? "border-ocean/40" : "border-navy/10"
                      }`}
                    >
                      <p className="text-xs text-navy/50">
                        {i === 0 ? "今日" : dayLabel(d.date)}
                      </p>
                      <p className="mt-1 text-2xl">{dw.icon}</p>
                      <p className="mt-1 text-sm font-semibold text-navy">
                        {waveSizeLabel(d.waveHeightMax)}
                      </p>
                      {d.waveHeightMax != null ? (
                        <p className="text-xs text-navy/50">最大 {d.waveHeightMax.toFixed(1)}m</p>
                      ) : null}
                      <div className="mt-2 space-y-0.5 text-[0.7rem] text-navy/50">
                        {d.tempMax != null ? (
                          <p>🌡 {Math.round(d.tempMin ?? 0)}〜{Math.round(d.tempMax)}℃</p>
                        ) : null}
                        {d.precipProb != null ? <p>☔️ {d.precipProb}%</p> : null}
                        {d.windMax != null ? <p>💨 最大{Math.round(d.windMax)}km/h</p> : null}
                        {d.sunrise && d.sunset ? (
                          <p>🌅 {hhmm(d.sunrise)}／🌇 {hhmm(d.sunset)}</p>
                        ) : null}
                      </div>
                    </div>
                  );
                })}
              </div>
              <p className="mt-2 text-right text-xs text-navy/40">
                データ：Open-Meteo（30分ごとに更新）
              </p>
            </section>
          </>
        ) : (
          <section className="mt-6 rounded-2xl border border-dashed border-navy/20 bg-white p-8 text-center text-sm text-navy/60">
            天気・波データを取得できませんでした（API制限などの可能性）。
            <br />
            下の「ローカルの声」を参考にしてください。
          </section>
        )}

        {/* スポンサー広告枠 */}
        <div className="mt-8">
          <AdBanner placement="waves" />
        </div>

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
              localWaves.map((wv) => (
                <Link
                  key={wv.id}
                  href={`/community/posts/${wv.id}`}
                  className="block rounded-xl border border-navy/10 bg-white p-4 transition hover:border-ocean/40"
                >
                  <p className="line-clamp-2 text-sm text-navy/80">{wv.body}</p>
                  <p className="mt-2 text-xs text-navy/40">
                    {wv.author?.display_name ?? "Local"}
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
    <div className="rounded-xl bg-white/10 px-2.5 py-2">
      <p className="text-[0.7rem] text-sand/70">{label}</p>
      <p className="mt-0.5 text-sm font-medium">{value}</p>
    </div>
  );
}

/** 今日の波高を、3時間ごとの簡易バーで表示 */
function HourlyBars({ hours }: { hours: WaveHour[] }) {
  const sampled = hours.filter((_, i) => i % 3 === 0).slice(0, 8);
  const max = Math.max(0.6, ...sampled.map((h) => h.waveHeight ?? 0));
  return (
    <div className="flex items-end justify-between gap-1.5" style={{ height: 96 }}>
      {sampled.map((h) => {
        const val = h.waveHeight ?? 0;
        const pct = Math.max(6, Math.round((val / max) * 100));
        return (
          <div key={h.time} className="flex flex-1 flex-col items-center gap-1">
            <span className="text-[0.6rem] text-navy/50">{val.toFixed(1)}</span>
            <div
              className="w-full rounded-t bg-ocean/70"
              style={{ height: `${pct}%` }}
              title={`${hhmm(h.time)} ${val.toFixed(1)}m`}
            />
            <span className="text-[0.6rem] text-navy/40">{hhmm(h.time).slice(0, 2)}時</span>
          </div>
        );
      })}
    </div>
  );
}
