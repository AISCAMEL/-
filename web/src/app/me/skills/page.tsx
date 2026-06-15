import Link from "next/link";
import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { CommunityHeader } from "@/components/community/community-header";
import { SKILL_CATEGORY_LABEL, type SkillCategory } from "@/lib/skills";

export const metadata: Metadata = {
  title: "自分のスキル｜IWASAWA SURF BASE",
};

const APP_STATUS_LABEL: Record<string, string> = {
  applied: "申込中",
  accepted: "承認",
  declined: "見送り",
  done: "完了",
};

const SKILL_STATUS_LABEL: Record<string, string> = {
  open: "公開中",
  closed: "受付終了",
  hidden: "非公開",
};

export default async function MySkillsPage() {
  const member = await getCurrentMember();
  if (!member) redirect("/login?next=/me/skills");

  const supabase = await createClient();

  const [listingsRes, appliedRes, receivedRes] = await Promise.all([
    supabase
      .from("skills")
      .select("id, title, category, status")
      .eq("owner_id", member.id)
      .order("created_at", { ascending: false }),
    supabase
      .from("skill_applications")
      .select("id, status, created_at, skill:skills!inner(id, title)")
      .eq("applicant_id", member.id)
      .order("created_at", { ascending: false }),
    supabase
      .from("skill_applications")
      .select(
        "id, status, message, created_at, skill:skills!inner(id, title, owner_id), applicant:members!skill_applications_applicant_id_fkey(display_name)",
      )
      .eq("skill.owner_id", member.id)
      .order("created_at", { ascending: false }),
  ]);

  type Listing = { id: string; title: string; category: SkillCategory; status: string };
  type Applied = {
    id: string;
    status: string;
    created_at: string;
    skill: { id: string; title: string } | null;
  };
  type Received = {
    id: string;
    status: string;
    message: string | null;
    created_at: string;
    skill: { id: string; title: string } | null;
    applicant: { display_name: string | null } | null;
  };

  const listings = (listingsRes.data ?? []) as unknown as Listing[];
  const applied = (appliedRes.data ?? []) as unknown as Applied[];
  const received = (receivedRes.data ?? []) as unknown as Received[];

  return (
    <div className="min-h-screen bg-foam">
      <CommunityHeader />
      <main className="mx-auto max-w-3xl px-4 py-8">
        <h1 className="text-2xl font-semibold text-navy">自分のスキル</h1>

        {/* 出品 */}
        <Section title="出品したスキル" action={{ href: "/skills/new", label: "＋ 出品する" }}>
          {listings.length === 0 ? (
            <Empty>まだ出品はありません。</Empty>
          ) : (
            listings.map((s) => (
              <Link
                key={s.id}
                href={`/skills/${s.id}`}
                className="flex items-center justify-between rounded-xl border border-navy/10 bg-white p-4 transition hover:border-ocean/40"
              >
                <div>
                  <span className="text-xs text-ocean">
                    {SKILL_CATEGORY_LABEL[s.category]}
                  </span>
                  <p className="font-medium text-navy">{s.title}</p>
                </div>
                <span className="rounded-full bg-navy/5 px-2.5 py-0.5 text-xs text-navy/60">
                  {SKILL_STATUS_LABEL[s.status] ?? s.status}
                </span>
              </Link>
            ))
          )}
        </Section>

        {/* 受け取った申込 */}
        <Section title="受け取った申込">
          {received.length === 0 ? (
            <Empty>まだ申込はありません。</Empty>
          ) : (
            received.map((r) => (
              <div key={r.id} className="rounded-xl border border-navy/10 bg-white p-4">
                <div className="flex items-center justify-between">
                  <p className="text-sm font-medium text-navy">
                    {r.skill?.title}
                  </p>
                  <span className="rounded-full bg-teal/10 px-2.5 py-0.5 text-xs text-teal">
                    {APP_STATUS_LABEL[r.status] ?? r.status}
                  </span>
                </div>
                <p className="mt-1 text-xs text-navy/50">
                  {r.applicant?.display_name ?? "メンバー"} さんから
                </p>
                {r.message ? (
                  <p className="mt-2 whitespace-pre-wrap text-sm text-navy/70">
                    {r.message}
                  </p>
                ) : null}
              </div>
            ))
          )}
        </Section>

        {/* 申し込んだスキル */}
        <Section title="申し込んだスキル">
          {applied.length === 0 ? (
            <Empty>まだ申込はありません。</Empty>
          ) : (
            applied.map((a) => (
              <Link
                key={a.id}
                href={a.skill ? `/skills/${a.skill.id}` : "/skills"}
                className="flex items-center justify-between rounded-xl border border-navy/10 bg-white p-4 transition hover:border-ocean/40"
              >
                <p className="text-sm font-medium text-navy">{a.skill?.title}</p>
                <span className="rounded-full bg-ocean/10 px-2.5 py-0.5 text-xs text-ocean">
                  {APP_STATUS_LABEL[a.status] ?? a.status}
                </span>
              </Link>
            ))
          )}
        </Section>
      </main>
    </div>
  );
}

function Section({
  title,
  action,
  children,
}: {
  title: string;
  action?: { href: string; label: string };
  children: React.ReactNode;
}) {
  return (
    <section className="mt-8">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-navy/70">{title}</h2>
        {action ? (
          <Link href={action.href} className="text-xs text-ocean hover:underline">
            {action.label}
          </Link>
        ) : null}
      </div>
      <div className="mt-3 space-y-3">{children}</div>
    </section>
  );
}

function Empty({ children }: { children: React.ReactNode }) {
  return (
    <p className="rounded-xl border border-dashed border-navy/20 bg-white p-5 text-center text-sm text-navy/50">
      {children}
    </p>
  );
}
