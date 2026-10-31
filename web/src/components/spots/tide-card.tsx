import { getTideReport, moonEmoji, TIDE_NAME_NOTE, type TideDay } from "@/lib/tide";

function fmtIn(min: number): string {
  const h = Math.floor(min / 60);
  const m = min % 60;
  return h > 0 ? `${h}時間${m > 0 ? `${m}分` : ""}後` : `${m}分後`;
}

const W = 320;
const H = 96;
const PAD = 8;

/** 今日の潮位カーブ（0〜24時）。満潮・干潮と現在時刻に印をつける */
function TideCurve({ day, nowMinutes }: { day: TideDay; nowMinutes: number }) {
  const vals = day.hourly;
  const min = Math.min(...vals, ...day.lows.map((e) => e.height));
  const max = Math.max(...vals, ...day.highs.map((e) => e.height));
  const span = Math.max(1, max - min);
  const x = (minutes: number) => PAD + (minutes / 1440) * (W - PAD * 2);
  const y = (h: number) => PAD + (1 - (h - min) / span) * (H - PAD * 2 - 12);
  const pts = vals.map((v, i) => `${x(i * 60).toFixed(1)},${y(v).toFixed(1)}`);
  const line = `M${pts.join(" L")}`;
  const area = `${line} L${x(23 * 60).toFixed(1)},${H - 12} L${x(0).toFixed(1)},${H - 12} Z`;
  const toMin = (t: string) => Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5));
  const nowH = vals[Math.min(23, Math.floor(nowMinutes / 60))];

  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="h-auto w-full" role="img" aria-label="今日の潮位グラフ">
      <defs>
        <linearGradient id="tideFill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#0e8ac7" stopOpacity="0.35" />
          <stop offset="100%" stopColor="#14c6a6" stopOpacity="0.05" />
        </linearGradient>
      </defs>
      <path d={area} fill="url(#tideFill)" />
      <path d={line} fill="none" stroke="#0e8ac7" strokeWidth="2" strokeLinejoin="round" />
      {[0, 6, 12, 18].map((h) => (
        <text key={h} x={x(h * 60)} y={H - 1} fontSize="9" fill="#0b2540" opacity="0.45">
          {h}時
        </text>
      ))}
      {day.highs.map((e) => (
        <g key={`h${e.time}`}>
          <circle cx={x(toMin(e.time))} cy={y(e.height)} r="3" fill="#0b2540" />
          <text x={x(toMin(e.time))} y={y(e.height) - 5} fontSize="9" textAnchor="middle" fill="#0b2540">
            満 {e.time}
          </text>
        </g>
      ))}
      {day.lows.map((e) => (
        <g key={`l${e.time}`}>
          <circle cx={x(toMin(e.time))} cy={y(e.height)} r="3" fill="#14c6a6" />
          <text x={x(toMin(e.time))} y={y(e.height) + 12} fontSize="9" textAnchor="middle" fill="#0b2540">
            干 {e.time}
          </text>
        </g>
      ))}
      <line x1={x(nowMinutes)} x2={x(nowMinutes)} y1={PAD} y2={H - 12} stroke="#ef4444" strokeDasharray="3 3" />
      <circle cx={x(nowMinutes)} cy={y(nowH)} r="3.5" fill="#ef4444" />
    </svg>
  );
}

/** 潮回り（気象庁 潮位表の天文潮位＋月齢からの潮名） */
export async function TideCard({ spotId, title = "潮回り" }: { spotId: string; title?: string }) {
  const t = await getTideReport(spotId);
  if (!t) return null;

  return (
    <section className="rounded-2xl border border-navy/10 bg-white p-5 shadow-sm">
      <div className="flex items-baseline justify-between gap-2">
        <h2 className="text-base font-semibold text-navy">🌊 {title}</h2>
        <span className="text-[0.65rem] text-navy/40">{t.station.name}の潮位表</span>
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-2">
        <span className="rounded-full bg-ocean px-3 py-1 text-sm font-semibold text-foam">{t.name}</span>
        <span className="text-xs text-navy/60">
          {moonEmoji(t.moonAge)} 月齢 {t.moonAge}
        </span>
      </div>
      <p className="mt-1.5 text-xs text-navy/60">{TIDE_NAME_NOTE[t.name]}</p>

      <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
        <div className="rounded-xl bg-foam p-3">
          <p className="text-[0.65rem] text-navy/45">いまの潮</p>
          <p className="mt-1 font-semibold text-navy">
            {t.now.trend === "上げ" ? "↗ 上げ潮" : t.now.trend === "下げ" ? "↘ 下げ潮" : "→ 潮止まり"}
          </p>
          <p className="text-[0.65rem] text-navy/55">潮位 {t.now.height}cm</p>
        </div>
        <div className="rounded-xl bg-foam p-3">
          <p className="text-[0.65rem] text-navy/45">次は</p>
          {t.next ? (
            <>
              <p className="mt-1 font-semibold text-navy">
                {t.next.kind} {t.next.time}
              </p>
              <p className="text-[0.65rem] text-navy/55">
                {fmtIn(t.next.inMinutes)}・{t.next.height}cm
              </p>
            </>
          ) : (
            <p className="mt-1 text-navy/60">—</p>
          )}
        </div>
      </div>

      <div className="mt-3">
        <TideCurve day={t.today} nowMinutes={t.now.minutes} />
      </div>

      <dl className="mt-2 grid grid-cols-2 gap-x-4 text-xs text-navy/75">
        <div>
          <dt className="text-[0.65rem] text-navy/45">満潮</dt>
          <dd>{t.today.highs.map((e) => `${e.time}（${e.height}cm）`).join("・") || "—"}</dd>
        </div>
        <div>
          <dt className="text-[0.65rem] text-navy/45">干潮</dt>
          <dd>{t.today.lows.map((e) => `${e.time}（${e.height}cm）`).join("・") || "—"}</dd>
        </div>
      </dl>

      <p className="mt-3 text-[0.65rem] text-navy/40">
        出典：気象庁 潮位表（天文潮位の推算値）。気圧や風による変化は含みません。潮名は月齢からの目安です。
      </p>
    </section>
  );
}
