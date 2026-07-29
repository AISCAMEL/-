import Link from "next/link";
import { redirect, notFound } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { ApplyForm } from "@/components/skills/apply-form";
import { SITE } from "@/lib/site";

type Props = { params: Promise<{ id: string }> };

export default async function ApplyPage({ params }: Props) {
  const { id } = await params;
  const member = await getCurrentMember();
  if (!member) redirect(`/login?next=/skills/${id}/apply`);

  const supabase = await createClient();
  const [{ data: skill }, { data: me }] = await Promise.all([
    supabase.from("skills").select("id, title, owner_id").eq("id", id).single(),
    supabase.from("members").select("rules_ack_at").eq("id", member.id).single(),
  ]);
  if (!skill) notFound();
  if (skill.owner_id === member.id) redirect(`/skills/${id}`);

  const learnedRules = Boolean(me?.rules_ack_at);

  return (
    <main className="mx-auto max-w-xl px-4 py-8">
      <Link href={`/skills/${id}`} className="text-sm text-ocean hover:underline">
        ← スキル詳細へ
      </Link>
      <h1 className="mt-4 text-2xl font-semibold text-navy">申し込む・相談する</h1>
      <p className="mt-1 text-sm text-navy/60">「{skill.title}」について</p>

      {learnedRules ? (
        <>
          <div className="mt-6 flex items-center gap-2 rounded-xl bg-teal/10 px-4 py-3 text-sm text-teal">
            🌊 <span>海のルール 学習済み。安全への一歩、ありがとうございます。</span>
          </div>
          <div className="mt-6">
            <ApplyForm skillId={id} />
          </div>
        </>
      ) : (
        // ルール未学習は、学習をはさんでから申し込む
        <div className="mt-6 rounded-2xl border border-amber-300 bg-amber-50 p-6">
          <p className="text-base font-semibold text-navy">
            申し込む前に、海のルールを。
          </p>
          <p className="mt-2 text-sm leading-relaxed text-navy/70">
            {SITE.name} では、<strong>ルールとマナーを知ってから、プロに習い、海に入る</strong>
            ことを大切にしています。まずは「海に入る前の5つの約束」を確認しましょう（数分で終わります）。
          </p>
          <Link
            href={`/rules?next=/skills/${id}/apply`}
            className="mt-4 inline-block rounded-full bg-navy px-6 py-2.5 text-sm font-medium text-foam transition hover:bg-ocean"
          >
            海のルールを学ぶ →
          </Link>
          <p className="mt-3 text-xs text-navy/40">
            学習を終えると、この画面に戻って申し込めます。
          </p>
        </div>
      )}
    </main>
  );
}
