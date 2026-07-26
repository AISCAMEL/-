const HUB = process.env.HUB_API_URL ?? "http://127.0.0.1:3001";

export async function DELETE(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  try {
    const res = await fetch(`${HUB}/products/${id}`, { method: "DELETE", cache: "no-store" });
    const body = await res.text();
    return new Response(body, { status: res.status, headers: { "content-type": "application/json" } });
  } catch (e) {
    return Response.json({ error: `Hub API へ接続できません`, detail: String(e) }, { status: 502 });
  }
}
