import { getWaveReport } from "@/lib/waves";
import { getTideReport } from "@/lib/tide";
import { getJmaWarnings, jmaAreasForSpots } from "@/lib/jma";
import { AI_ENABLED } from "@/lib/ai/client";
import { DEMO } from "@/lib/demo";

// 監視用ヘルスチェック。外部の死活監視サービス（UptimeRobot など）から定期的に呼ぶ。
// 必須のデータ源（波予報・潮位表）が取れなければ 503 を返し、監視側で異常として通知できる。
// 気象庁の警報データは公式APIではないため、取れなくても 200 のまま「degraded」とする。
export const dynamic = "force-dynamic";

async function check<T>(fn: () => Promise<T | null>): Promise<{ ok: boolean; ms: number }> {
  const t = Date.now();
  try {
    const v = await fn();
    return { ok: v != null, ms: Date.now() - t };
  } catch {
    return { ok: false, ms: Date.now() - t };
  }
}

export async function GET() {
  const [waves, tide, jma] = await Promise.all([
    check(() => getWaveReport()),
    check(() => getTideReport("iwasawa")),
    check(() => getJmaWarnings(jmaAreasForSpots(["iwasawa"]))),
  ]);
  const critical = waves.ok && tide.ok;
  const status = critical ? (jma.ok ? "ok" : "degraded") : "down";
  return Response.json(
    {
      status,
      checkedAt: new Date().toISOString(),
      mode: DEMO ? "demo" : "live",
      ai: AI_ENABLED ? "enabled" : "disabled",
      sources: { waves, tide, jmaWarnings: jma },
    },
    { status: critical ? 200 : 503, headers: { "Cache-Control": "no-store" } },
  );
}
