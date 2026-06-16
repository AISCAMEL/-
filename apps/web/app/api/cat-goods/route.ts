const HUB = process.env.HUB_API_URL ?? "http://localhost:3001";

/** Hub API の /niche/cat-goods をプロキシ（ブラウザから同一オリジンで叩くため）。 */
export async function GET() {
  try {
    const res = await fetch(`${HUB}/niche/cat-goods`, { cache: "no-store" });
    const body = await res.text();
    return new Response(body, { status: res.status, headers: { "content-type": "application/json" } });
  } catch (e) {
    return Response.json({ error: `Hub API へ接続できません (${HUB})`, detail: String(e) }, { status: 502 });
  }
}
