import { NextResponse } from "next/server";

const HUB = process.env.HUB_API_URL ?? "http://127.0.0.1:3001";

export async function GET(req: Request) {
  const origin = new URL(req.url).origin;
  const redirectUri = `${origin}/api/base/callback`;

  try {
    const res = await fetch(
      `${HUB}/auth/base/authorize?redirectUri=${encodeURIComponent(redirectUri)}`,
      { cache: "no-store" },
    );
    const data = await res.json();
    if (!res.ok || !data.url) {
      return Response.json(
        { error: data.error ?? "authorize URL を取得できません" },
        { status: res.status },
      );
    }
    return NextResponse.redirect(data.url);
  } catch (e) {
    return Response.json(
      { error: `Hub API へ接続できません (${HUB})`, detail: String(e) },
      { status: 502 },
    );
  }
}
