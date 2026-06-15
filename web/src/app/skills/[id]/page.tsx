import Link from "next/link";
import { notFound } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import {
  SKILL_CATEGORY_LABEL,
  priceLabel,
  type SkillCategory,
} from "@/lib/skills";

type Props = { params: Promise<{ id: string }> };

type SkillRow = {
  id: string;
  owner_id: string;
  category: SkillCategory;
  title: string;
  description: string;
  price: number | null;
  area: string | null;
  owner: { display_name: string | null; role: string } | null;
};

export default async function SkillDetail({ params }: Props) {
  const { id } = await params;
  const supabase = await createClient();
  const member = await getCurrentMember();

  const { data } = await supabase
    .from("skills")
    .select(
      "id, owner_id, category, title, description, price, area, owner:members!skills_owner_id_fkey(display_name, role)",
    )
    .eq("id", id)
    .single();

  const skill = data as unknown as SkillRow | null;
  if (!skill) notFound();

  const isOwner = member?.id === skill.owner_id;

  return (
    <main className="mx-auto max-w-2xl px-4 py-8">
      <Link href="/skills" className="text-sm text-ocean hover:underline">
        ← スキル掲示板へ
      </Link>

      <article className="mt-4 rounded-2xl border border-navy/10 bg-white p-6 shadow-sm">
        <div className="flex items-center justify-between text-xs">
          <span className="rounded-full bg-ocean/10 px-2.5 py-0.5 font-medium text-ocean">
            {SKILL_CATEGORY_LABEL[skill.category]}
          </span>
          <span className="font-medium text-teal">{priceLabel(skill.price)}</span>
        </div>
        <h1 className="mt-3 text-xl font-semibold text-navy">{skill.title}</h1>
        <p className="mt-3 whitespace-pre-wrap text-navy/80">{skill.description}</p>
        <div className="mt-6 flex items-center justify-between border-t border-navy/10 pt-4 text-sm text-navy/50">
          <span>{skill.owner?.display_name ?? "メンバー"}</span>
          {skill.area ? <span>📍 {skill.area}</span> : null}
        </div>
      </article>

      <div className="mt-6">
        {isOwner ? (
          <p className="rounded-xl border border-navy/10 bg-white p-5 text-center text-sm text-navy/60">
            これはあなたの出品です。申込状況は{" "}
            <Link href="/me/skills" className="font-medium text-ocean hover:underline">
              マイページ
            </Link>
            で確認できます。
          </p>
        ) : member ? (
          <Link
            href={`/skills/${skill.id}/apply`}
            className="block rounded-xl bg-ocean px-4 py-3 text-center font-medium text-foam transition hover:bg-navy"
          >
            このスキルに申し込む・相談する
          </Link>
        ) : (
          <div className="rounded-xl border border-ocean/20 bg-white p-5 text-center text-sm text-navy/70">
            申し込むには{" "}
            <Link
              href={`/login?next=/skills/${skill.id}`}
              className="font-medium text-ocean hover:underline"
            >
              ログイン
            </Link>
            {" / "}
            <Link href="/signup" className="font-medium text-ocean hover:underline">
              新規登録
            </Link>
          </div>
        )}
      </div>

      <p className="mt-6 text-center text-xs text-navy/40">
        ※ 現在はオンライン決済に未対応です。連絡・相談のうえで進めてください。
      </p>
    </main>
  );
}
