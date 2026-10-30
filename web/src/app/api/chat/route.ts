import Anthropic from "@anthropic-ai/sdk";
import { z } from "zod";
import { anthropic, AI_ENABLED, AI_MODEL, FALLBACK_BETA } from "@/lib/ai/client";
import { CHAT_SYSTEM } from "@/lib/ai/chat-knowledge";
import { resolvePlace, forecastDigest } from "@/lib/ai/forecast-digest";
import { getWaveReport } from "@/lib/waves";
import { getJmaWarnings, jmaAreasForSpots } from "@/lib/jma";

// ============================================================
// AIチャット（Claude）— ストリーミング応答
// ------------------------------------------------------------
// クライアントへは NDJSON（1行1イベント）で返す：
//   {"t":"text","v":"..."}   本文の差分
//   {"t":"status","v":"..."} ツール実行中などの状態表示
//   {"t":"error","v":"..."}  エラー
// ============================================================

export const maxDuration = 60;

const MAX_TURNS = 20;
const MAX_CHARS = 1000;
const MAX_TOOL_ROUNDS = 4;

const BodySchema = z.object({
  messages: z
    .array(
      z.object({
        role: z.enum(["user", "assistant"]),
        content: z.string().min(1).max(MAX_CHARS * 4),
      }),
    )
    .min(1)
    .max(MAX_TURNS),
});

// ---- かんたんなレート制限（インスタンス単位・ベストエフォート） ----
const WINDOW_MS = 10 * 60 * 1000;
const LIMIT = 20;
const hits = new Map<string, number[]>();
function rateLimited(ip: string): boolean {
  const now = Date.now();
  const list = (hits.get(ip) ?? []).filter((t) => now - t < WINDOW_MS);
  list.push(now);
  hits.set(ip, list);
  if (hits.size > 5000) hits.clear();
  return list.length > LIMIT;
}

// ---- ツール ----
const ForecastInput = z.object({ place_id: z.string().min(1).max(64) });

const TOOLS: Anthropic.Beta.BetaTool[] = [
  {
    name: "get_wave_forecast",
    description:
      "サーフポイントまたはエリアの最新の波・風・天気の予報（現在値、今後24時間の3時間ごと、数日先）と、気象庁の警報・注意報を取得する。place_id はシステムプロンプトの全国サーフポイント一覧にある id（例: iwasawa, shonan, ichinomiya）。",
    input_schema: {
      type: "object",
      properties: {
        place_id: { type: "string", description: "ポイントまたはエリアの id" },
      },
      required: ["place_id"],
      additionalProperties: false,
    },
    eager_input_streaming: true,
  },
];

async function runForecastTool(input: unknown): Promise<{ content: string; isError: boolean; label: string }> {
  const parsed = ForecastInput.safeParse(input);
  if (!parsed.success) {
    return { content: "place_id が不正です。", isError: true, label: "予報" };
  }
  const place = resolvePlace(parsed.data.place_id);
  if (!place) {
    return {
      content: `id「${parsed.data.place_id}」は見つかりません。一覧にある id を使ってください。`,
      isError: true,
      label: "予報",
    };
  }
  const [report, warnings] = await Promise.all([
    getWaveReport({ lat: place.lat, lon: place.lon }),
    getJmaWarnings(jmaAreasForSpots(place.spotIds)),
  ]);
  if (!report) {
    return { content: "予報を取得できませんでした。時間をおいて確認するよう伝えてください。", isError: true, label: place.name };
  }
  return { content: JSON.stringify(forecastDigest(place, report, warnings)), isError: false, label: place.name };
}

function line(obj: { t: "text" | "status" | "error"; v: string }): Uint8Array {
  return new TextEncoder().encode(JSON.stringify(obj) + "\n");
}

function ndjson(stream: ReadableStream<Uint8Array>): Response {
  return new Response(stream, {
    headers: {
      "Content-Type": "application/x-ndjson; charset=utf-8",
      "Cache-Control": "no-store",
    },
  });
}

export async function POST(req: Request) {
  const ip = (req.headers.get("x-forwarded-for") ?? "local").split(",")[0].trim();
  if (rateLimited(ip)) {
    return Response.json({ error: "少し時間をおいてから、もう一度お試しください。" }, { status: 429 });
  }

  let body: z.infer<typeof BodySchema>;
  try {
    body = BodySchema.parse(await req.json());
  } catch {
    return Response.json({ error: "リクエストが不正です。" }, { status: 400 });
  }
  const last = body.messages[body.messages.length - 1];
  if (last.role !== "user" || last.content.length > MAX_CHARS) {
    return Response.json({ error: "メッセージが長すぎます。" }, { status: 400 });
  }

  // AI 未設定時：案内メッセージだけ返す
  if (!AI_ENABLED) {
    return ndjson(
      new ReadableStream({
        start(controller) {
          controller.enqueue(
            line({
              t: "text",
              v: "いまAIアシスタントは準備中です🙏\n波の様子は /waves、全国のポイントは /spots、はじめての方は /rules と /instructors をご覧ください。",
            }),
          );
          controller.close();
        },
      }),
    );
  }

  // 先頭はユーザー発話から（クライアントの挨拶文などは除く）
  const firstUser = body.messages.findIndex((m) => m.role === "user");
  const messages: Anthropic.Beta.BetaMessageParam[] = body.messages
    .slice(firstUser)
    .map((m) => ({ role: m.role, content: m.content }));

  const client = anthropic();

  const stream = new ReadableStream<Uint8Array>({
    async start(controller) {
      const send = (obj: Parameters<typeof line>[0]) => controller.enqueue(line(obj));
      try {
        for (let round = 0; round <= MAX_TOOL_ROUNDS; round++) {
          const s = client.beta.messages.stream(
            {
              model: AI_MODEL,
              max_tokens: 16000,
              betas: [FALLBACK_BETA],
              fallbacks: "default",
              output_config: { effort: "low" },
              cache_control: { type: "ephemeral" },
              system: [{ type: "text", text: CHAT_SYSTEM, cache_control: { type: "ephemeral" } }],
              tools: TOOLS,
              messages,
            },
            { signal: req.signal },
          );
          s.on("text", (delta) => send({ t: "text", v: delta }));

          const message = await s.finalMessage();

          if (message.stop_reason === "refusal") {
            send({ t: "text", v: "\nごめんなさい、その内容にはお答えできません。サーフィンや海のことなら何でも聞いてください。" });
            break;
          }
          const toolUses = message.content.filter(
            (b): b is Anthropic.Beta.BetaToolUseBlock => b.type === "tool_use",
          );
          if (message.stop_reason !== "tool_use" || toolUses.length === 0) break;
          if (round === MAX_TOOL_ROUNDS) break;

          messages.push({ role: "assistant", content: message.content });
          const results: Anthropic.Beta.BetaToolResultBlockParam[] = await Promise.all(
            toolUses.map(async (tu) => {
              if (tu.name !== "get_wave_forecast") {
                return { type: "tool_result" as const, tool_use_id: tu.id, content: "未知のツールです。", is_error: true };
              }
              send({ t: "status", v: "🌊 最新の波予報を確認しています…" });
              const r = await runForecastTool(tu.input);
              return { type: "tool_result" as const, tool_use_id: tu.id, content: r.content, is_error: r.isError };
            }),
          );
          messages.push({ role: "user", content: results });
        }
      } catch (err) {
        if (!req.signal.aborted) {
          console.error("[ai] chat failed:", err);
          const msg =
            err instanceof Anthropic.RateLimitError
              ? "いま混み合っています。少し時間をおいてお試しください。"
              : "うまく応答できませんでした。もう一度お試しください。";
          send({ t: "error", v: msg });
        }
      } finally {
        controller.close();
      }
    },
  });

  return ndjson(stream);
}
