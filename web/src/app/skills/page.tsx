import Link from "next/link";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import {
  SKILL_CATEGORIES,
  SKILL_CATEGORY_LABEL,
  priceLabel,
  isSkillCategory,
  type SkillCategory,
} from "@/lib/skills";

export const metadata: Metadata = {
  title: "スキル掲示板｜IWASAWA SURF BASE",
  description: "できる人が、これからの人へ。海にまつわるスキルを教え合う。",
};

type SkillRow = {
  id: string;
  category: SkillCategory;
  title: string;
  description: string;
  price: number | null;
  area: string | null;
  owner: { display_name: string | null; role: string } | null;
};

type Props = { searchParams: Promise<{ category?: string }> };

export default async function SkillsPage({ searchParams }: Props) {
  const { category } = await searchParams;
  const active = category && isSkillCategory(category) ? category : null;
  const member = await getCurrentMember();

  const supabase = await createClient();
  let query = supabase
    .from("skills")
    .select(
      "id, category, title, description, price, area, owner:members!skills_owner_id_fkey(display_name, role)",
    )
    .eq("status", "open")
    .order("created_at", { ascending: false })
    .limit(40);
  if (active) query = query.eq("category", active);

  const { data, error } = await query;
  const skills = (data ?? []) as unknown as SkillRow[];

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      <div className="flex items-start justify-between gap-4 rounded-2xl bg-ocean-gradient p-6 text-foam">
        <div>
          <h1 className="text-2xl font-semibold">スキル掲示板</h1>
          <p className="mt-1 text-sm text-sand/90">
            できる人が、これからの人へ。
            あなたのスキルを、海の入口にしよう。
          </p>
        </div>
        {member ? (
          <Link
            href="/skills/new"
            className="shrink-0 rounded-full bg-teal px-4 py-2 text-sm font-medium text-navy transition hover:bg-foam"
          >
            出品する
          </Link>
        ) : null}
      </div>

      {/* カテゴリフィルター */}
      <nav className="mt-6 flex flex-wrap gap-2">
        <Chip label="すべて" href="/skills" active={!active} />
        {SKILL_CATEGORIES.map((c) => (
          <Chip
            key={c.key}
            label={c.label}
            href={`/skills?category=${c.key}`}
            active={active === c.key}
          />
        ))}
      </nav>

      <div className="mt-6 grid gap-4 sm:grid-cols-2">
        {error ? (
          <p className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 sm:col-span-2">
            読み込みに失敗しました。Supabase の設定（SETUP.md）をご確認ください。
          </p>
        ) : skills.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-navy/20 bg-white p-10 text-center text-sm text-navy/60 sm:col-span-2">
            まだ出品がありません。最初のスキルを、あなたから。
          </div>
        ) : (
          skills.map((s) => (
            <Link
              key={s.id}
              href={`/skills/${s.id}`}
              className="rounded-2xl border border-navy/10 bg-white p-5 shadow-sm transition hover:border-ocean/40"
            >
              <div className="flex items-center justify-between text-xs">
                <span className="rounded-full bg-ocean/10 px-2.5 py-0.5 font-medium text-ocean">
                  {SKILL_CATEGORY_LABEL[s.category]}
                </span>
                <span className="font-medium text-teal">{priceLabel(s.price)}</span>
              </div>
              <h3 className="mt-3 font-semibold text-navy">{s.title}</h3>
              <p className="mt-1.5 line-clamp-2 text-sm text-navy/60">
                {s.description}
              </p>
              <div className="mt-4 flex items-center justify-between text-xs text-navy/50">
                <span>{s.owner?.display_name ?? "メンバー"}</span>
                {s.area ? <span>📍 {s.area}</span> : null}
              </div>
            </Link>
          ))
        )}
      </div>

      <p className="mt-8 text-center text-xs text-navy/40">
        ※ 現在は「連絡・相談まで」を無料でご利用いただけます。オンライン決済は今後対応予定です。
      </p>
    </main>
  );
}

function Chip({ label, href, active }: { label: string; href: string; active: boolean }) {
  return (
    <Link
      href={href}
      className={`rounded-full border px-3.5 py-1.5 text-sm transition ${
        active
          ? "border-ocean bg-ocean text-foam"
          : "border-navy/15 bg-white text-navy/70 hover:border-ocean"
      }`}
    >
      {label}
    </Link>
  );
}
