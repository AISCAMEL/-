import { getJmaWarnings, jmaAreasForSpots, jmaWarningUrl, type JmaWarning } from "@/lib/jma";

const LEVEL_STYLE: Record<JmaWarning["level"], string> = {
  20: "bg-amber-100 text-amber-800 border-amber-300",
  30: "bg-red-100 text-red-800 border-red-300",
  40: "bg-purple-100 text-purple-800 border-purple-300",
  50: "bg-neutral-900 text-white border-neutral-900",
};

function time(iso: string | null): string {
  if (!iso) return "";
  const m = iso.match(/^\d{4}-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
  return m ? `${Number(m[1])}/${Number(m[2])} ${m[3]}:${m[4]}` : "";
}

/**
 * 気象庁の警報・注意報（ポイントの市町村ごと）。
 * 海に関わるもの（波浪・強風・高潮・雷・濃霧）を目立たせる。取得失敗時は何も出さない。
 */
export async function JmaWarnings({ spotIds }: { spotIds: string[] }) {
  const areas = jmaAreasForSpots(spotIds);
  const list = await getJmaWarnings(areas);
  if (!list || list.length === 0) return null;

  const active = list.filter((w) => w.warnings.length > 0);
  const latest = list.map((w) => w.reportDatetime).filter(Boolean).sort().at(-1) ?? null;

  if (active.length === 0) {
    return (
      <p className="flex flex-wrap items-center gap-x-2 rounded-xl border border-teal/30 bg-white px-4 py-2.5 text-xs text-navy/70">
        <span className="font-medium text-teal">✅ 気象庁の警報・注意報：発表なし</span>
        <span className="text-navy/45">（{list.map((w) => w.area.label).join("・")}）</span>
      </p>
    );
  }

  const marineSerious = active.some((w) => w.warnings.some((x) => x.marine && x.level >= 30));
  const marineAny = active.some((w) => w.warnings.some((x) => x.marine));

  return (
    <section
      className={`rounded-2xl border p-4 shadow-sm ${
        marineSerious ? "border-red-300 bg-red-50" : "border-amber-300 bg-amber-50"
      }`}
    >
      <div className="flex items-baseline justify-between gap-2">
        <h2 className="text-sm font-semibold text-navy">⚠️ 気象庁の警報・注意報</h2>
        {latest ? <span className="text-[0.65rem] text-navy/50">{time(latest)} 時点</span> : null}
      </div>

      <div className="mt-3 space-y-3">
        {active.map((w) => (
          <div key={w.area.area}>
            <p className="text-xs text-navy/60">{w.area.label}</p>
            <div className="mt-1 flex flex-wrap gap-1.5">
              {w.warnings.map((x) => (
                <span
                  key={x.code}
                  className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${LEVEL_STYLE[x.level]}`}
                >
                  {x.name}
                  {x.additions.length ? `（${x.additions.join("・")}）` : ""}
                </span>
              ))}
            </div>
            {w.headlines.map((h) => (
              <p key={h} className="mt-1.5 text-xs leading-relaxed text-navy/75">
                {h}
              </p>
            ))}
            <a
              href={jmaWarningUrl(w.area)}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-1 inline-block text-[0.7rem] text-ocean underline underline-offset-2"
            >
              気象庁で詳しく見る ↗
            </a>
          </div>
        ))}
      </div>

      {marineAny ? (
        <p className="mt-3 rounded-lg bg-white/70 px-3 py-2 text-xs font-medium text-navy/80">
          {marineSerious
            ? "警報が出ています。海に入るのは控えてください。"
            : "海に関わる注意報が出ています。初心者の方は入水を控え、経験者も無理をしないでください。"}
        </p>
      ) : null}
      <p className="mt-2 text-[0.6rem] text-navy/40">出典：気象庁（10分ごとに更新）</p>
    </section>
  );
}
