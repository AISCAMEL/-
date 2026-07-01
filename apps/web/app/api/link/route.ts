const HUB = process.env.HUB_API_URL ?? "http://127.0.0.1:3001";

/** Hub API の /marketing/link をプロキシ（UTM計測リンク生成）。 */
export async function GET(req: Request) {
  const { search } = new URL(req.url);
  try {
    const res = await fetch(`${HUB}/marketing/link${search}`, { cache: "no-store" });
    const body = await res.text();
    return new Response(body, { status: res.status, headers: { "content-type": "application/json" } });
  } catch (e) {
    return Response.json({ error: `Hub API へ接続できません (${HUB})`, detail: String(e) }, { status: 502 });
  }
}
