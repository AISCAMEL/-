import Link from "next/link";
import {
  getWaveReport,
  waveSizeLabel,
  windLabel,
  compass,
  vibe,
} from "@/lib/waves";

/** WV-01: コンパクトな波情報ウィジェット（フィード上部などに置く） */
export async function WaveWidget() {
  const report = await getWaveReport();

  if (!report) {
    return (
      <Link
        href="/waves"
        className="block rounded-2xl border border-navy/10 bg-white p-4 text-sm text-navy/60"
      >
        🌊 波情報は現在取得できません。
        <span className="text-ocean"> 詳しく見る →</span>
      </Link>
    );
  }

  const { now } = report;
  const v = vibe(now);
  const tone =
    v.tone === "good"
      ? "border-teal/40 bg-teal/10"
      : v.tone === "ok"
        ? "border-ocean/30 bg-ocean/5"
        : "border-navy/10 bg-white";

  return (
    <Link
      href="/waves"
      className={`block rounded-2xl border p-4 transition hover:shadow-sm ${tone}`}
    >
      <div className="flex items-center justify-between">
        <div>
          <p className="text-xs text-navy/50">岩沢海岸・いまの波</p>
          <p className="mt-0.5 text-lg font-semibold text-navy">
            {waveSizeLabel(now.waveHeight)}
            {now.waveHeight != null ? (
              <span className="ml-1 text-sm font-normal text-navy/50">
                ({now.waveHeight.toFixed(1)}m)
              </span>
            ) : null}
          </p>
        </div>
        <div className="text-right text-xs text-navy/60">
          <p>風 {compass(now.windDirection)}・{windLabel(now.windSpeed)}</p>
          {now.temperature != null ? <p>気温 {Math.round(now.temperature)}℃</p> : null}
        </div>
      </div>
      <p className="mt-2 text-xs text-navy/70">{v.text}</p>
    </Link>
  );
}
