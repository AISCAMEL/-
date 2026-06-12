import { createServerClient } from "@supabase/ssr";
import { NextResponse, type NextRequest } from "next/server";

/** 会員のみアクセス可能なパス（未ログインは /login へ誘導） */
const PROTECTED_PREFIXES = ["/me", "/community/new", "/skills/new"];
/** 運営のみアクセス可能なパス */
const ADMIN_PREFIX = "/admin";

/**
 * セッションを更新しつつ、保護ルートのアクセス制御を行う。
 * 権限は UI + API + RLS の三重化の「ミドルウェア（入口）」部分。
 */
export async function updateSession(request: NextRequest) {
  let supabaseResponse = NextResponse.next({ request });

  const url = process.env.NEXT_PUBLIC_SUPABASE_URL ?? "";
  const anon = process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY ?? "";

  // 未設定時はゲートをかけず素通り（セットアップ前でも開発できるように）
  if (!url || !anon) return supabaseResponse;

  const supabase = createServerClient(url, anon, {
    cookies: {
      getAll() {
        return request.cookies.getAll();
      },
      setAll(cookiesToSet) {
        cookiesToSet.forEach(({ name, value }) =>
          request.cookies.set(name, value),
        );
        supabaseResponse = NextResponse.next({ request });
        cookiesToSet.forEach(({ name, value, options }) =>
          supabaseResponse.cookies.set(name, value, options),
        );
      },
    },
  });

  const {
    data: { user },
  } = await supabase.auth.getUser();

  const path = request.nextUrl.pathname;
  const needsAuth = PROTECTED_PREFIXES.some((p) => path.startsWith(p));
  const needsAdmin = path.startsWith(ADMIN_PREFIX) && path !== "/admin/login";

  if (!user && (needsAuth || needsAdmin)) {
    const redirect = request.nextUrl.clone();
    redirect.pathname = needsAdmin ? "/admin/login" : "/login";
    redirect.searchParams.set("next", path);
    return NextResponse.redirect(redirect);
  }

  if (user && needsAdmin) {
    // 会員種別を確認し、staff/admin 以外は弾く
    const { data: member } = await supabase
      .from("members")
      .select("role")
      .eq("id", user.id)
      .single();
    const role = member?.role;
    if (role !== "staff" && role !== "admin") {
      const redirect = request.nextUrl.clone();
      redirect.pathname = "/";
      return NextResponse.redirect(redirect);
    }
  }

  return supabaseResponse;
}
