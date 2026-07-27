import { createServerClient } from "@supabase/ssr";
import { cookies } from "next/headers";

/**
 * サーバー（Server Component / Route Handler / Server Action）用 Supabase クライアント。
 * Cookie からセッションを読み書きする。
 */
export async function createClient() {
  const cookieStore = await cookies();

  // 未設定（デモモード）でもクライアント生成で例外を投げないようダミー値を置く。
  // 実クエリはデモ用データに差し替えるため、この値でネットワークには出ない。
  return createServerClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL || "https://demo.supabase.co",
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY || "demo-anon-key",
    {
      cookies: {
        getAll() {
          return cookieStore.getAll();
        },
        setAll(cookiesToSet) {
          try {
            cookiesToSet.forEach(({ name, value, options }) =>
              cookieStore.set(name, value, options),
            );
          } catch {
            // Server Component から呼ばれた場合は set 不可。proxy 側で更新済み。
          }
        },
      },
    },
  );
}
