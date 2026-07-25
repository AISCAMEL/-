const HUB = process.env.HUB_API_URL ?? "http://127.0.0.1:3001";

export const maxDuration = 60;

/** Hub API の /research/screen をプロキシ。 */
export async function POST(req: Request) {
  try {
    const payload = await req.text();
    const res = await fetch(`${HUB}/research/screen`, {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: payload,
      cache: "no-store",
      signal: AbortSignal.timeout(55_000),
    });
    const body = await res.text();
    return new Response(body, { status: res.status, headers: { "content-type": "application/json" } });
  } catch (e) {
    return Response.json({ error: `Hub API へ接続できません (${HUB})`, detail: String(e) }, { status: 502 });
  }
}
