import { getWaveReport, vibe, waveSizeLabel, compass } from "@/lib/waves";
import { windRelation, WIND_RELATION_META } from "@/lib/spots";
import { resolvePlace } from "@/lib/ai/forecast-digest";
import { getWaveAdvice } from "@/lib/ai/wave-advice";
import { getJmaWarnings, jmaAreasForSpots } from "@/lib/jma";

/**
 * AI波解説（Claude）。予報モデルの数値 → サーファー向けのコメントに。
 * AI が使えない場合は、数値からのかんたんコメントを表示する。
 */
export async function AiWaveAdvice({ placeId }: { placeId: string }) {
  const place = resolvePlace(placeId);
  if (!place) return null;
  const report = await getWaveReport({ lat: place.lat, lon: place.lon });
  if (!report) return null;

  const warnings = await getJmaWarnings(jmaAreasForSpots(place.spotIds));
  const advice = await getWaveAdvice(place, report, warnings);

  if (!advice) {
    // フォールバック：数値からのかんたんコメント
    const marine = (warnings ?? []).flatMap((w) => w.warnings.filter((x) => x.marine));
    const serious = marine.some((x) => x.level >= 30);
    const v = serious
      ? { text: "気象庁の警報が出ています。今日は海に入らないでください。" }
      : marine.length > 0
        ? { text: `${marine[0].name}が出ています。初心者は入水を控え、経験者も無理をしないで。` }
        : vibe(report.now);
    const rel =
      report.now.windDirection == null
        ? null
        : WIND_RELATION_META[windRelation(report.now.windDirection, place.facingDeg)];
    return (
      <section className="rounded-2xl border border-teal/30 bg-white p-5 shadow-sm">
        <p className="text-xs font-medium text-teal">かんたん波コメント</p>
        <p className="mt-1 text-base font-semibold text-navy">{v.text}</p>
        <p className="mt-2 text-sm text-navy/70">
          いまは{waveSizeLabel(report.now.waveHeight)}、{compass(report.now.windDirection)}の風
          {rel ? `（${place.name}では${rel.label}）` : ""}。
        </p>
      </section>
    );
  }

  return (
    <section className="overflow-hidden rounded-2xl border border-teal/30 bg-white shadow-sm">
      <div className="bg-ocean-gradient px-5 py-4 text-foam">
        <div className="flex items-center justify-between gap-2">
          <span className="rounded-full bg-white/15 px-2.5 py-0.5 text-[0.7rem] font-medium">
            ✨ AI波解説
          </span>
          <span className="text-sm tracking-widest" aria-label={`コンディション ${advice.rating} / 5`}>
            {"🌊".repeat(advice.rating)}
            <span className="opacity-30">{"🌊".repeat(5 - advice.rating)}</span>
          </span>
        </div>
        <p className="mt-2 text-lg font-semibold">{advice.headline}</p>
        <p className="mt-1 text-sm leading-relaxed text-sand/95">{advice.summary}</p>
      </div>

      <dl className="grid gap-3 p-5 text-sm sm:grid-cols-2">
        <Item icon="⏰" label="おすすめの時間帯" text={advice.best_time} />
        <Item icon="🔰" label="初心者の方へ" text={advice.beginner} />
        <Item icon="🏄" label="中・上級者の方へ" text={advice.experienced} />
        <Item icon="🧥" label="ウェット・持ち物" text={advice.gear} />
        <Item icon="⚠️" label="安全面" text={advice.caution} />
        <Item icon="📅" label="この先の見通し" text={advice.outlook} />
      </dl>

      <p className="border-t border-navy/5 px-5 py-2.5 text-[0.65rem] text-navy/40">
        Claude（Anthropic）が予報モデルの数値から作成したコメントです。実際の海は地形・潮で変わるため、現地で必ず確認してください。
      </p>
    </section>
  );
}

function Item({ icon, label, text }: { icon: string; label: string; text: string }) {
  return (
    <div className="rounded-xl bg-foam px-3 py-2.5">
      <dt className="text-[0.7rem] text-navy/50">
        {icon} {label}
      </dt>
      <dd className="mt-0.5 leading-relaxed text-navy/85">{text}</dd>
    </div>
  );
}

export function AiWaveAdviceSkeleton() {
  return (
    <section className="animate-pulse rounded-2xl border border-teal/30 bg-white p-5 shadow-sm">
      <p className="text-xs font-medium text-teal">✨ AIが波を読んでいます…</p>
      <div className="mt-3 h-4 w-2/3 rounded bg-foam" />
      <div className="mt-2 h-3 w-full rounded bg-foam" />
      <div className="mt-2 h-3 w-5/6 rounded bg-foam" />
    </section>
  );
}
