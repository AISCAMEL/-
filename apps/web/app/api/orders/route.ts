const HUB = process.env.HUB_API_URL ?? "http://localhost:3001";

/** Hub API の /orders をプロキシ（受注一覧・損益付き）。 */
export async function GET() {
  try {
    const res = await fetch(`${HUB}/orders`, { cache: "no-store" });
    const body = await res.text();
    return new Response(body, { status: res.status, headers: { "content-type": "application/json" } });
  } catch (e) {
    return Response.json({ error: `Hub API へ接続できません (${HUB})`, detail: String(e) }, { status: 502 });
  }
}
