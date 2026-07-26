import { dbEnabled, priceRuleRepo } from "@hub/db";

const FALLBACK_RATE = 21;
const CACHE_TTL_MS = 6 * 60 * 60 * 1000;

let cachedRate: { rate: number; fetchedAt: number } | null = null;

export async function fetchCnyToJpy(): Promise<number> {
  if (cachedRate && Date.now() - cachedRate.fetchedAt < CACHE_TTL_MS) {
    return cachedRate.rate;
  }

  try {
    const res = await fetch(
      "https://open.er-api.com/v6/latest/CNY",
      { signal: AbortSignal.timeout(5000) },
    );
    if (!res.ok) throw new Error(`FX API ${res.status}`);
    const data = (await res.json()) as { rates?: Record<string, number> };
    const rate = data.rates?.JPY;
    if (typeof rate !== "number" || rate <= 0) throw new Error("invalid rate");
    cachedRate = { rate, fetchedAt: Date.now() };
    console.log(`[fx] CNY→JPY: ${rate}`);
    return rate;
  } catch (e) {
    console.warn(`[fx] fetch failed, using ${cachedRate?.rate ?? FALLBACK_RATE}:`, e);
    return cachedRate?.rate ?? FALLBACK_RATE;
  }
}

export async function updatePriceRuleFxRate(): Promise<{ rate: number; updated: boolean }> {
  const rate = await fetchCnyToJpy();

  if (!dbEnabled) {
    return { rate, updated: false };
  }

  try {
    const rule = await priceRuleRepo.getDefaultPriceRule();
    if (rule) {
      const currentRate = Number(rule.fxRateToJpy);
      const diff = Math.abs(rate - currentRate);
      if (diff / currentRate > 0.005) {
        const { prisma } = await import("@hub/db");
        await prisma.priceRule.update({
          where: { id: "default" },
          data: { fxRateToJpy: rate },
        });
        console.log(`[fx] PriceRule updated: ${currentRate} → ${rate}`);
        return { rate, updated: true };
      }
    }
  } catch (e) {
    console.error("[fx] DB update failed:", e);
  }

  return { rate, updated: false };
}

export function getCachedRate(): number | null {
  return cachedRate?.rate ?? null;
}
