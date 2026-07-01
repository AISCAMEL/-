const HUB = process.env.HUB_API_URL ?? "http://127.0.0.1:3001";

/** Hub API の /sync/status をプロキシ（定期同期の状態・前回結果）。 */
export async function GET() {
  try {
    const res = await fetch(`${HUB}/sync/status`, { cache: "no-store" });
    const body = await res.text();
    return new Response(body, { status: res.status, headers: { "content-type": "application/json" } });
  } catch (e) {
    return Response.json({ error: `Hub API へ接続できません (${HUB})`, detail: String(e) }, { status: 502 });
  }
}
