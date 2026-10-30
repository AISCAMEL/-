import {
  getWaveReport,
  compass,
  waveSizeLabel,
  windLabel,
  weatherLabel,
  wetsuitHint,
} from "@/lib/waves";
import { windRelation, WIND_RELATION_META } from "@/lib/spots";

const WEEKDAY = ["日", "月", "火", "水", "木", "金", "土"];

function dayLabel(date: string, i: number): string {
  if (i === 0) return "今日";
  if (i === 1) return "明日";
  const d = new Date(`${date}T00:00:00+09:00`);
  return `${d.getMonth() + 1}/${d.getDate()}（${WEEKDAY[d.getDay()]}）`;
}

function fmt(n: number | null, digits = 1): string {
  return n == null ? "—" : n.toFixed(digits);
}

/**
 * 指定座標のライブ波予報（Open-Meteo）。
 * 取得できない場合（ネットワーク制限・APIダウン）は案内文にフォールバック。
 */
export async function LiveForecast({
  lat,
  lon,
  facingDeg,
  title = "いまの波・風（予報モデル）",
}: {
  lat: number;
  lon: number;
  facingDeg: number;
  title?: string;
}) {
  const report = await getWaveReport({ lat, lon });

  if (!report) {
    return (
      <section className="rounded-2xl border border-navy/10 bg-white p-5 shadow-sm">
        <h2 className="text-base font-semibold text-navy">{title}</h2>
        <p className="mt-2 text-sm text-navy/60">
          波予報を取得できませんでした。少し時間をおいて再読み込みしてください。
        </p>
      </section>
    );
  }

  const { now, days } = report;
  const relation = now.windDirection == null ? null : windRelation(now.windDirection, facingDeg);
  const rel = relation ? WIND_RELATION_META[relation] : null;
  const weather = weatherLabel(now.weatherCode);

  return (
    <section className="rounded-2xl border border-navy/10 bg-white p-5 shadow-sm">
      <div className="flex items-baseline justify-between gap-2">
        <h2 className="text-base font-semibold text-navy">{title}</h2>
        <span className="text-[0.65rem] text-navy/40">30分ごとに更新</span>
      </div>

      <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Tile label="波のサイズ" value={waveSizeLabel(now.waveHeight)} sub={`${fmt(now.waveHeight)} m`} />
        <Tile
          label="周期・うねり"
          value={`${fmt(now.wavePeriod, 0)} 秒`}
          sub={`${compass(now.waveDirection)}から`}
        />
        <Tile
          label="風"
          value={`${compass(now.windDirection)} ${windLabel(now.windSpeed)}`}
          sub={`${fmt(now.windSpeed == null ? null : now.windSpeed / 3.6)} m/s`}
        />
        <Tile
          label="水温"
          value={`${fmt(now.waterTemp)} ℃`}
          sub={wetsuitHint(now.waterTemp)}
        />
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
        {rel ? (
          <span className={`rounded-full px-2.5 py-1 font-medium ${rel.style}`}>
            {rel.label}：{rel.note}
          </span>
        ) : null}
        <span className="rounded-full bg-foam px-2.5 py-1 text-navy/70">
          {weather.icon} {weather.text}・気温 {fmt(now.temperature, 0)}℃
        </span>
      </div>

      {days.length > 0 ? (
        <div className="mt-4 overflow-x-auto">
          <table className="w-full min-w-[22rem] text-left text-xs">
            <thead className="text-navy/45">
              <tr>
                <th className="py-1.5 font-normal">日付</th>
                <th className="py-1.5 font-normal">最大波高</th>
                <th className="py-1.5 font-normal">うねり</th>
                <th className="py-1.5 font-normal">天気</th>
                <th className="py-1.5 font-normal">最大風速</th>
              </tr>
            </thead>
            <tbody className="text-navy/80">
              {days.slice(0, 4).map((d, i) => {
                const w = weatherLabel(d.weatherCode);
                return (
                  <tr key={d.date} className="border-t border-navy/5">
                    <td className="py-1.5 font-medium text-navy">{dayLabel(d.date, i)}</td>
                    <td className="py-1.5">
                      {waveSizeLabel(d.waveHeightMax)}
                      <span className="ml-1 text-navy/40">{fmt(d.waveHeightMax)}m</span>
                    </td>
                    <td className="py-1.5">{compass(d.waveDirection)}</td>
                    <td className="py-1.5">{w.icon}</td>
                    <td className="py-1.5">
                      {fmt(d.windMax == null ? null : d.windMax / 3.6, 0)} m/s
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}

      <p className="mt-3 text-[0.65rem] text-navy/40">
        出典：Open-Meteo（沖合の予報モデル値）。実際の波は地形・潮で変わるため、現地で必ず確認してください。
      </p>
    </section>
  );
}

function Tile({ label, value, sub }: { label: string; value: string; sub: string }) {
  return (
    <div className="rounded-xl bg-foam p-3">
      <p className="text-[0.65rem] text-navy/45">{label}</p>
      <p className="mt-1 text-sm font-semibold text-navy">{value}</p>
      <p className="mt-0.5 text-[0.65rem] text-navy/55">{sub}</p>
    </div>
  );
}
