import { unstable_cache } from "next/cache";
import { z } from "zod";
import { betaZodOutputFormat } from "@anthropic-ai/sdk/helpers/beta/zod";
import { anthropic, AI_ENABLED, AI_MODEL, FALLBACK_BETA } from "@/lib/ai/client";
import { forecastDigest, type ForecastPlace } from "@/lib/ai/forecast-digest";
import type { WaveReport } from "@/lib/waves";
import type { JmaAreaWarnings } from "@/lib/jma";
import type { TideReport } from "@/lib/tide";

const WaveAdviceSchema = z.object({
  headline: z.string().describe("今日のコンディションを一言で（20文字前後）"),
  rating: z.number().int().describe("今日の総合コンディション。1（入らない方がいい）〜5（最高）の整数"),
  summary: z.string().describe("波・風・天気をふまえた総評（2〜3文）"),
  best_time: z.string().describe("おすすめの時間帯と理由（1〜2文）"),
  beginner: z.string().describe("初心者へのアドバイス（1〜2文。入らない方がよければはっきり伝える）"),
  experienced: z.string().describe("中・上級者へのアドバイス（1〜2文）"),
  gear: z.string().describe("ウェットスーツや持ち物の目安（1文）"),
  caution: z.string().describe("安全面の注意（1文）"),
  outlook: z.string().describe("この先2〜3日の見通し（1〜2文）"),
});

export type WaveAdvice = z.infer<typeof WaveAdviceSchema>;

// 変化しない指示文（プロンプトキャッシュが効くよう、日時などの可変値は入れない）
const SYSTEM = `あなたは日本のサーフィン事情に詳しいベテランのサーフガイドです。
渡された「場所の情報」と「予報モデルの数値」だけを根拠に、そのポイント（またはエリア）で今日サーフィンをする人向けの波情報コメントを日本語で書きます。

判断のしかた:
- 波のサイズはサーファーの言い方（スネ・ヒザ・コシ・ハラ・ムネ・カタ・アタマ）で表す。目安：0.3m未満ほぼフラット、〜0.6mスネ〜ヒザ、〜1.0mコシ〜ハラ、〜1.5mハラ〜ムネ、〜2.0mムネ〜カタ、それ以上アタマ以上。
- 周期が長い（10秒以上）うねり成分はパワーとまとまりがあり、風波成分が大きく周期が短い（7秒未満）ときは波がまとまりにくい。
- 「風の当たり方」はオフショア（面が整う）／サイドショア／オンショア（面が乱れる）。風速5m/s以上のオンショアは面が荒れやすい。8m/s以上の強風は初心者には危険。
- 「良いうねり」の向きと今のうねりの向きが合っているかを考慮する。
- 「潮回り」（潮名・満潮/干潮の時刻・いまの上げ/下げ）も使い、おすすめの時間帯に反映する。ビーチは干潮前後に地形が出て割れやすくなる日が多い一方、リーフ・岩のポイントは干潮時に浅くなり危険。潮位は天文潮位の推算値。
- 「気象庁の警報注意報」が発表中なら、何よりも優先して触れる。波浪・暴風・高潮の警報以上（警報・危険警報・特別警報）が出ていれば、全員に海に入らないよう明確に伝え、評価は1にする。波浪注意報・強風注意報が出ていれば、初心者には入水を控えるよう伝え、評価は最大でも2〜3にする。雷注意報なら雷鳴が聞こえたらすぐ海から上がるよう伝える。
- 予報は沖合のモデル値で、実際の波は地形・潮で変わる。数値にないこと（潮汐・混雑の実況・地形）を事実のように書かない。
- 初心者の安全を最優先にする。危ない日は、はっきり「やめておこう」と伝える。
- 親しみやすく、短く。絵文字や見出し記号は使わない。`;

export async function generateWaveAdvice(
  place: ForecastPlace,
  report: WaveReport,
  warnings: JmaAreaWarnings[] | null,
  tide: TideReport | null,
): Promise<WaveAdvice> {
  const digest = forecastDigest(place, report, warnings, tide);
  const res = await anthropic().beta.messages.parse({
    model: AI_MODEL,
    max_tokens: 16000,
    betas: [FALLBACK_BETA],
    fallbacks: "default",
    output_config: { effort: "medium", format: betaZodOutputFormat(WaveAdviceSchema) },
    system: [{ type: "text", text: SYSTEM, cache_control: { type: "ephemeral" } }],
    messages: [
      {
        role: "user",
        content: `次のデータから、今日の波情報コメントを作ってください。\n\n${JSON.stringify(digest)}`,
      },
    ],
  });
  if (res.stop_reason === "refusal" || !res.parsed_output) {
    throw new Error(`AI wave advice unavailable (stop_reason=${res.stop_reason})`);
  }
  const out = res.parsed_output;
  return { ...out, rating: Math.min(5, Math.max(1, Math.round(out.rating))) };
}

function warningKey(warnings: JmaAreaWarnings[] | null): string {
  if (!warnings) return "jma:na";
  const codes = warnings.flatMap((w) => w.warnings.map((x) => `${w.area.area}:${x.code}`)).sort();
  return `jma:${codes.join(",") || "none"}`;
}

/**
 * AI波解説を取得。同じ場所・同じ3時間帯の結果はキャッシュして再利用する
 * （ページ表示のたびに API を呼ばない）。失敗時・未設定時は null。
 */
export async function getWaveAdvice(
  place: ForecastPlace,
  report: WaveReport,
  warnings: JmaAreaWarnings[] | null,
  tide: TideReport | null,
): Promise<WaveAdvice | null> {
  if (!AI_ENABLED) return null;
  const t = report.now.time || report.fetchedAt;
  const bucket = `${t.slice(0, 10)}-${Math.floor(Number(t.slice(11, 13) || 0) / 3)}`;
  const cached = unstable_cache(
    () => generateWaveAdvice(place, report, warnings, tide),
    // 警報・注意報の発表/解除で作り直す
    ["ai-wave-advice-v3", place.id, bucket, warningKey(warnings)],
    { revalidate: 3 * 3600, tags: ["ai-wave-advice"] },
  );
  try {
    return await cached();
  } catch (err) {
    console.error("[ai] wave advice failed:", err);
    return null;
  }
}
