import { NextResponse } from "next/server";

const HUB = process.env.HUB_API_URL ?? "http://127.0.0.1:3001";

export async function GET(req: Request) {
  const url = new URL(req.url);
  const code = url.searchParams.get("code");
  const error = url.searchParams.get("error");

  if (error) {
    const dest = new URL("/", url.origin);
    dest.searchParams.set("base_error", error);
    return NextResponse.redirect(dest.toString());
  }

  if (!code) {
    const dest = new URL("/", url.origin);
    dest.searchParams.set("base_error", "code_missing");
    return NextResponse.redirect(dest.toString());
  }

  const redirectUri = `${url.origin}/api/base/callback`;

  try {
    const res = await fetch(`${HUB}/auth/base/exchange`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ code, redirectUri }),
    });
    const data = await res.json();

    const dest = new URL("/", url.origin);
    if (res.ok && data.ok) {
      dest.searchParams.set("base", "connected");
    } else {
      dest.searchParams.set(
        "base_error",
        data.detail || data.error || "token_exchange_failed",
      );
    }
    return NextResponse.redirect(dest.toString());
  } catch (e) {
    const dest = new URL("/", url.origin);
    dest.searchParams.set("base_error", `hub_unreachable: ${String(e)}`);
    return NextResponse.redirect(dest.toString());
  }
}
