import Link from "next/link";
import { getWaveReport, waveSizeLabel, compass, weatherLabel, wetsuitHint, vibe } from "@/lib/waves";
import { getTideReport } from "@/lib/tide";
import { getJmaWarnings, jmaAreasForSpots } from "@/lib/jma";
import { getSpotById, facingOf, getAreaById, windRelation, WIND_RELATION_META } from "@/lib/spots";
import { SITE } from "@/lib/site";

function hm(iso: string): string {
  const m = iso.match(/T(\d{2}):(\d{2})/);
  return m ? `${Number(m[1])}:${m[2]}` : "";
}

/** トップ用：拠点（岩沢海岸）の「今日」をひと目で。波・風・潮・警報 */
export async function TodayIwasawa() {
  const spot = getSpotById("iwasawa");
  if (!spot) return null;
  const facing = facingOf(spot, getAreaById(spot.area));
  const [report, tide, warnings] = await Promise.all([
    getWaveReport({ lat: spot.lat, lon: spot.lon }),
    getTideReport(spot.id),
    getJmaWarnings(jmaAreasForSpots([spot.id])),
  ]);

  const marine = (warnings ?? []).flatMap((w) => w.warnings.filter((x) => x.marine));
  const serious = marine.some((x) => x.level >= 30);
  const rel =
    report?.now.windDirection == null ? null : WIND_RELATION_META[windRelation(report.now.windDirection, facing)];
  const w = report ? weatherLabel(report.now.weatherCode) : null;

  return (
    <section className="rounded-2xl border border-navy/10 bg-white p-5 shadow-lg">
      <div className="flex items-baseline justify-between gap-2">
        <h2 className="text-lg font-semibold text-navy">今日の{SITE.region.beachShort}</h2>
        {report?.now.time ? <span className="text-xs text-navy/45">{hm(report.now.time)} 更新</span> : null}
      </div>

      {marine.length > 0 ? (
        <p
          className={`mt-3 rounded-xl px-3 py-2 text-sm font-medium ${
            serious ? "bg-red-50 text-red-800" : "bg-amber-50 text-amber-800"
          }`}
        >
          ⚠️ {marine.map((x) => x.name).join("・")}が発表中。
          {serious ? "今日は海に入らないでください。" : "初心者の方は入水を控えましょう。"}
        </p>
      ) : warnings ? (
        <p className="mt-3 text-xs font-medium text-teal">✅ 気象庁の警報・注意報：発表なし</p>
      ) : null}

      {report ? (
        <>
          <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <Tile label="波" value={waveSizeLabel(report.now.waveHeight)} sub={`${report.now.waveHeight?.toFixed(1) ?? "—"}m・${report.now.wavePeriod?.toFixed(0) ?? "—"}秒`} />
            <Tile
              label="風"
              value={rel ? rel.label : compass(report.now.windDirection)}
              sub={`${compass(report.now.windDirection)} ${report.now.windSpeed == null ? "—" : (report.now.windSpeed / 3.6).toFixed(0)}m/s`}
            />
            <Tile
              label="潮"
              value={tide ? `${tide.name}・${tide.now.trend === "上げ" ? "上げ" : tide.now.trend === "下げ" ? "下げ" : "止まり"}` : "—"}
              sub={tide?.next ? `次の${tide.next.kind} ${tide.next.time}` : ""}
            />
            <Tile
              label="水温・天気"
              value={`${report.now.waterTemp?.toFixed(0) ?? "—"}℃ ${w?.icon ?? ""}`}
              sub={wetsuitHint(report.now.waterTemp)}
            />
          </div>
          <p className="mt-3 text-sm text-navy/80">{serious ? "警報が出ています。海には近づかないでください。" : vibe(report.now).text}</p>
        </>
      ) : (
        <p className="mt-3 text-sm text-navy/60">いまの波予報を取得できませんでした。詳しい波情報ページでご確認ください。</p>
      )}

      <div className="mt-4 flex flex-wrap gap-2 text-sm">
        <Link href="/waves" className="rounded-full bg-ocean px-4 py-2 font-medium text-foam transition hover:bg-navy">
          詳しい波情報 →
        </Link>
        <Link
          href="/spots/iwasawa"
          className="rounded-full border border-ocean/30 px-4 py-2 font-medium text-ocean transition hover:bg-ocean/10"
        >
          AI波解説・潮回り
        </Link>
      </div>
    </section>
  );
}

function Tile({ label, value, sub }: { label: string; value: string; sub: string }) {
  return (
    <div className="rounded-xl bg-foam p-3">
      <p className="text-[0.65rem] text-navy/45">{label}</p>
      <p className="mt-0.5 text-sm font-semibold text-navy">{value}</p>
      <p className="mt-0.5 text-[0.65rem] text-navy/55">{sub}</p>
    </div>
  );
}

export function TodayIwasawaSkeleton() {
  return (
    <section className="animate-pulse rounded-2xl border border-navy/10 bg-white p-5 shadow-lg">
      <p className="text-lg font-semibold text-navy">今日の{SITE.region.beachShort}</p>
      <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
        {[0, 1, 2, 3].map((i) => (
          <div key={i} className="h-16 rounded-xl bg-foam" />
        ))}
      </div>
    </section>
  );
}
