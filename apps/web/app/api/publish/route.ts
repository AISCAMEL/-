const HUB = process.env.HUB_API_URL ?? "http://127.0.0.1:3001";

/** Hub API の /products/publish をプロキシ（BASE 出品）。 */
export async function POST(req: Request) {
  try {
    const payload = await req.text();
    const res = await fetch(`${HUB}/products/publish`, {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: payload,
      cache: "no-store",
    });
    const body = await res.text();
    return new Response(body, { status: res.status, headers: { "content-type": "application/json" } });
  } catch (e) {
    return Response.json({ error: `Hub API へ接続できません (${HUB})`, detail: String(e) }, { status: 502 });
  }
}
