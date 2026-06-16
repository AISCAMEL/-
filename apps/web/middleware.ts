import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

const COOKIE = "hub_session";

/**
 * ダッシュボードのアクセス制御。
 * DASHBOARD_PASSWORD が未設定なら認証無効（ローカル開発用）。
 * 設定時は、ログイン済み（セッションCookie）以外を /login に誘導する。
 */
export function middleware(req: NextRequest) {
  const password = process.env.DASHBOARD_PASSWORD;
  if (!password) return NextResponse.next(); // 開発時は認証なし

  const { pathname } = req.nextUrl;
  // ログイン関連は素通し
  if (pathname.startsWith("/login") || pathname.startsWith("/api/login")) {
    return NextResponse.next();
  }

  const token = req.cookies.get(COOKIE)?.value;
  const expected = process.env.AUTH_TOKEN || password;
  if (token && token === expected) return NextResponse.next();

  // API は 401、画面は /login へリダイレクト
  if (pathname.startsWith("/api/")) {
    return new NextResponse(JSON.stringify({ error: "unauthorized" }), {
      status: 401,
      headers: { "content-type": "application/json" },
    });
  }
  const url = req.nextUrl.clone();
  url.pathname = "/login";
  return NextResponse.redirect(url);
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
};
