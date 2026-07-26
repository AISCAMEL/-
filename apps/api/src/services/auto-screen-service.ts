import { allKeywords, CAT_GOODS } from "@hub/core";
import type { MarketResearchConnector, SupplierConnector } from "@hub/connectors";
import { screenCandidates } from "./screening-service.js";
import { pushAlert } from "./alert-service.js";

let lastAutoScreenAt: string | null = null;
let lastResults: { keyword: string; grade: string; score: number }[] = [];

export async function runAutoScreen(params: {
  resolveSupplier: (id: string) => SupplierConnector | undefined;
  markets: MarketResearchConnector[];
}): Promise<{ found: number; alerts: number }> {
  const keywords = allKeywords(CAT_GOODS);
  const candidates = keywords.map((keyword, i) => ({
    supplierId: "theckb" as const,
    externalId: `CKB-${String(i + 1).padStart(4, "0")}`,
    keyword,
  }));

  const result = await screenCandidates({
    candidates,
    resolveSupplier: params.resolveSupplier,
    markets: params.markets,
    options: { minMarginRate: 0.2, minGrade: "B" },
  });

  let alertCount = 0;
  const gradeA = result.items.filter((it) => it.score.grade === "A");

  if (gradeA.length > 0) {
    const names = gradeA.map((it) => it.keyword).join("、");
    pushAlert({
      type: "hot_product",
      title: `A判定 ${gradeA.length}件`,
      message: `売れ筋候補: ${names}`,
      severity: "info",
    });
    alertCount++;
  }

  lastAutoScreenAt = new Date().toISOString();
  lastResults = result.items.map((it) => ({
    keyword: it.keyword,
    grade: it.score.grade,
    score: it.score.score,
  }));

  console.log(`[auto-screen] ${result.items.length} passed, ${gradeA.length} grade-A`);
  return { found: result.items.length, alerts: alertCount };
}

export function getAutoScreenStatus() {
  return {
    lastRunAt: lastAutoScreenAt,
    results: lastResults,
  };
}
