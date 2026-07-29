import Link from "next/link";
import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { Brand } from "@/components/brand";
import { AvatarUpload } from "@/components/me/avatar-upload";
import { InstructorForm } from "@/components/me/instructor-form";
import { rankLabel, type InstructorRank } from "@/lib/instructors";

export const metadata: Metadata = { title: "講師プロフィール編集" };

export default async function InstructorEditPage() {
  const member = await getCurrentMember();
  if (!member) redirect("/login?next=/me/instructor");

  const supabase = await createClient();
  const [{ data: prof }, { data: me }] = await Promise.all([
    supabase
      .from("instructor_profiles")
      .select("rank, headline, bio, achievements, home_break, years, monthly_price, accepting")
      .eq("member_id", member.id)
      .maybeSingle(),
    supabase.from("members").select("avatar_url, display_name").eq("id", member.id).single(),
  ]);

  return (
    <div className="min-h-screen bg-foam">
      <header className="border-b border-navy/10 bg-white px-6 py-4">
        <Brand />
      </header>
      <main className="mx-auto max-w-xl px-6 py-10">
        <Link href="/me" className="text-sm text-ocean hover:underline">
          ← マイページへ
        </Link>
        <h1 className="mt-4 text-2xl font-semibold text-navy">講師プロフィール</h1>

        {!prof ? (
          <div className="mt-8 rounded-2xl border border-ocean/20 bg-white p-6 text-sm text-navy/70">
            <p>あなたはまだ講師として登録されていません。</p>
            <p className="mt-2 text-navy/50">
              トッププロ・トップアマ・インストラクターとして掲載を希望される方は、
              運営（Staff）にご連絡ください。承認後、ここでプロフィールを編集できます。
            </p>
            <Link
              href="/me/edit"
              className="mt-4 inline-block rounded-lg bg-ocean px-4 py-2 font-medium text-foam transition hover:bg-navy"
            >
              まずは基本プロフィールを整える →
            </Link>
          </div>
        ) : (
          <>
            <p className="mt-1 text-sm text-navy/50">
              ランク：{rankLabel(prof.rank as InstructorRank)}（変更は運営が行います）
            </p>

            <section className="mt-8">
              <h2 className="mb-3 text-sm font-semibold text-navy/70">プロフィール写真</h2>
              <AvatarUpload
                initialUrl={me?.avatar_url ?? null}
                name={me?.display_name ?? member.display_name ?? "講師"}
              />
              <p className="mt-2 text-xs text-navy/40">
                ※ 掲載する写真は本人のものを、ご本人の同意のうえでご使用ください。
              </p>
            </section>

            <section className="mt-8">
              <InstructorForm
                initial={{
                  headline: prof.headline,
                  bio: prof.bio,
                  achievements: prof.achievements,
                  home_break: prof.home_break,
                  years: prof.years,
                  monthly_price: prof.monthly_price,
                  accepting: prof.accepting,
                }}
              />
            </section>
          </>
        )}
      </main>
    </div>
  );
}
