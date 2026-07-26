const HUB = process.env.HUB_API_URL ?? "http://127.0.0.1:3001";

export async function POST(req: Request) {
  const body = await req.json();
  const orderId = body.orderId as string | undefined;

  const url = orderId ? `${HUB}/orders/${encodeURIComponent(orderId)}/fulfill` : `${HUB}/orders/fulfill-all`;
  const res = await fetch(url, { method: "POST", headers: { "content-type": "application/json" } });
  const data = await res.text();
  return new Response(data, { status: res.status, headers: { "content-type": "application/json" } });
}
