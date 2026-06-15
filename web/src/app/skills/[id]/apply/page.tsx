import Link from "next/link";
import { redirect, notFound } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { ApplyForm } from "@/components/skills/apply-form";

type Props = { params: Promise<{ id: string }> };

export default async function ApplyPage({ params }: Props) {
  const { id } = await params;
  const member = await getCurrentMember();
  if (!member) redirect(`/login?next=/skills/${id}/apply`);

  const supabase = await createClient();
  const { data: skill } = await supabase
    .from("skills")
    .select("id, title, owner_id")
    .eq("id", id)
    .single();
  if (!skill) notFound();

  if (skill.owner_id === member.id) redirect(`/skills/${id}`);

  return (
    <main className="mx-auto max-w-xl px-4 py-8">
      <Link href={`/skills/${id}`} className="text-sm text-ocean hover:underline">
        ← スキル詳細へ
      </Link>
      <h1 className="mt-4 text-2xl font-semibold text-navy">申し込む・相談する</h1>
      <p className="mt-1 text-sm text-navy/60">「{skill.title}」について</p>
      <div className="mt-8">
        <ApplyForm skillId={id} />
      </div>
    </main>
  );
}
