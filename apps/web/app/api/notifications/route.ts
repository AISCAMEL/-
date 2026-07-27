const HUB = process.env.HUB_API_URL ?? "http://127.0.0.1:3001";

export async function GET() {
  try {
    const res = await fetch(`${HUB}/notifications/status`, { cache: "no-store" });
    const body = await res.text();
    return new Response(body, { status: res.status, headers: { "content-type": "application/json" } });
  } catch (e) {
    return Response.json({ error: `Hub API へ接続できません`, detail: String(e) }, { status: 502 });
  }
}

export async function POST() {
  try {
    const res = await fetch(`${HUB}/notifications/test`, { method: "POST", cache: "no-store" });
    const body = await res.text();
    return new Response(body, { status: res.status, headers: { "content-type": "application/json" } });
  } catch (e) {
    return Response.json({ error: `Hub API へ接続できません`, detail: String(e) }, { status: 502 });
  }
}
