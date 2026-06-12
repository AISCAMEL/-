import { redirect } from "next/navigation";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { Brand } from "@/components/brand";
import { SignOutButton } from "@/components/auth/sign-out-button";

export const metadata: Metadata = { title: "マイページ｜IWASAWA SURF BASE" };

const ROLE_LABEL: Record<string, string> = {
  visitor: "Visitor",
  beginner: "Beginner",
  local: "Local",
  staff: "Staff",
  admin: "Admin",
};

export default async function MePage() {
  const supabase = await createClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();

  // ミドルウェアでも保護しているが、二重で確認（API層のゲート）
  if (!user) redirect("/login?next=/me");

  const { data: member } = await supabase
    .from("members")
    .select("display_name, handle, role, plan, email, bio")
    .eq("id", user.id)
    .single();

  return (
    <div className="min-h-screen bg-foam">
      <header className="flex items-center justify-between border-b border-navy/10 bg-white px-6 py-4">
        <Brand />
        <SignOutButton />
      </header>

      <main className="mx-auto max-w-2xl px-6 py-12">
        <h1 className="text-2xl font-semibold text-navy">マイページ</h1>
        <p className="mt-1 text-sm text-navy/60">
          こんにちは、{member?.display_name ?? "ゲスト"}さん🌊
        </p>

        <div className="mt-8 rounded-2xl border border-navy/10 bg-white p-6 shadow-sm">
          <div className="flex flex-wrap gap-2">
            <span className="rounded-full bg-ocean/10 px-3 py-1 text-xs font-medium text-ocean">
              種別：{ROLE_LABEL[member?.role ?? "beginner"]}
            </span>
            <span className="rounded-full bg-teal/10 px-3 py-1 text-xs font-medium text-teal">
              プラン：{member?.plan === "premium" ? "Premium" : "Free"}
            </span>
          </div>

          <dl className="mt-6 space-y-4 text-sm">
            <div>
              <dt className="text-navy/50">表示名</dt>
              <dd className="text-navy">{member?.display_name ?? "—"}</dd>
            </div>
            <div>
              <dt className="text-navy/50">メールアドレス</dt>
              <dd className="text-navy">{member?.email ?? user.email}</dd>
            </div>
            <div>
              <dt className="text-navy/50">自己紹介</dt>
              <dd className="text-navy">
                {member?.bio ?? "まだ設定されていません。"}
              </dd>
            </div>
          </dl>
        </div>

        <p className="mt-6 text-xs text-navy/40">
          ※ プロフィール編集・自分の投稿一覧は次のステップ（Step5）で追加します。
        </p>
      </main>
    </div>
  );
}
