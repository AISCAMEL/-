import { NextResponse } from "next/server";
import { createClient } from "@supabase/supabase-js";
import { DEMO } from "@/lib/demo";

// 広告クリック：クリック数を記録してから広告主のサイトへ転送する。
// 転送先は登録済みの広告の href だけ（任意URLへのオープンリダイレクトにしない）。
export async function GET(req: Request) {
  const id = new URL(req.url).searchParams.get("id") ?? "";
  const home = new URL("/", req.url);
  if (DEMO || !/^[0-9a-f-]{36}$/i.test(id)) return NextResponse.redirect(home);

  const supabase = createClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL!,
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!,
    { auth: { persistSession: false } },
  );
  const { data } = await supabase.rpc("register_ad_click", { ad_id: id });
  const href = typeof data === "string" ? data : null;
  if (!href || !/^https?:\/\//i.test(href)) return NextResponse.redirect(home);
  return NextResponse.redirect(href, { headers: { "Cache-Control": "no-store" } });
}
