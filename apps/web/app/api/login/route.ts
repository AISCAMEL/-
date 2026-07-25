const COOKIE = "hub_session";

/** ログイン: パスワード照合 → セッションCookie を発行。 */
export async function POST(req: Request) {
  const expected = process.env.DASHBOARD_PASSWORD;
  if (!expected) return Response.json({ ok: true, note: "auth disabled" });

  let password = "";
  try {
    password = (await req.json()).password ?? "";
  } catch {
    return Response.json({ error: "bad request" }, { status: 400 });
  }
  if (password !== expected) {
    return Response.json({ error: "パスワードが違います" }, { status: 401 });
  }

  const token = process.env.AUTH_TOKEN || expected;
  const secure = process.env.NODE_ENV === "production" ? "; Secure" : "";
  const res = Response.json({ ok: true });
  res.headers.append(
    "Set-Cookie",
    `${COOKIE}=${token}; Path=/; HttpOnly; SameSite=Lax; Max-Age=604800${secure}`,
  );
  return res;
}

/** ログアウト: Cookie 失効。 */
export async function DELETE() {
  const res = Response.json({ ok: true });
  res.headers.append("Set-Cookie", `${COOKIE}=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0`);
  return res;
}
