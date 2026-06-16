import Link from "next/link";
import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { Brand } from "@/components/brand";
import { ProfileForm } from "@/components/me/profile-form";

export const metadata: Metadata = { title: "プロフィール編集｜IWASAWA SURF BASE" };

export default async function EditProfilePage() {
  const member = await getCurrentMember();
  if (!member) redirect("/login?next=/me/edit");

  const supabase = await createClient();
  const { data } = await supabase
    .from("members")
    .select("display_name, handle, bio, home_area")
    .eq("id", member.id)
    .single();

  return (
    <div className="min-h-screen bg-foam">
      <header className="border-b border-navy/10 bg-white px-6 py-4">
        <Brand />
      </header>
      <main className="mx-auto max-w-xl px-6 py-10">
        <Link href="/me" className="text-sm text-ocean hover:underline">
          ← マイページへ
        </Link>
        <h1 className="mt-4 text-2xl font-semibold text-navy">プロフィール編集</h1>
        <div className="mt-8">
          <ProfileForm
            initial={{
              display_name: data?.display_name ?? null,
              handle: data?.handle ?? null,
              bio: data?.bio ?? null,
              home_area: data?.home_area ?? null,
            }}
          />
        </div>
      </main>
    </div>
  );
}
