import Link from "next/link";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { RANK_META, stars, priceLabelMonthly, type InstructorRank } from "@/lib/instructors";
import { DEMO, demoInstructors } from "@/lib/demo";

export const metadata: Metadata = {
  title: "サーフィンスクール｜IWASAWA SURF BASE",
  description: "トッププロ・トップアマ・認定インストラクターが、初めての一本をサポート。",
};

type Row = {
  member_id: string;
  rank: InstructorRank;
  headline: string | null;
  home_break: string | null;
  years: number | null;
  monthly_price: number | null;
  accepting: boolean;
  rating_avg: number;
  rating_count: number;
  member: { display_name: string | null; avatar_url: string | null } | null;
};

export default async function InstructorsPage() {
  let rows: {
    id: string;
    name: string;
    rank: InstructorRank;
    headline: string | null;
    home_break: string | null;
    years: number | null;
    monthly_price: number | null;
    accepting: boolean;
    rating_avg: number;
    rating_count: number;
  }[] = [];

  if (DEMO) {
    rows = demoInstructors.map((d) => ({ ...d }));
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("instructor_profiles")
      .select(
        "member_id, rank, headline, home_break, years, monthly_price, accepting, rating_avg, rating_count, member:members!instructor_profiles_member_id_fkey(display_name, avatar_url)",
      )
      .order("is_featured", { ascending: false })
      .order("rating_avg", { ascending: false });
    rows = ((data ?? []) as unknown as Row[]).map((r) => ({
      id: r.member_id,
      name: r.member?.display_name ?? "講師",
      rank: r.rank,
      headline: r.headline,
      home_break: r.home_break,
      years: r.years,
      monthly_price: r.monthly_price,
      accepting: r.accepting,
      rating_avg: r.rating_avg,
      rating_count: r.rating_count,
    }));
  }

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      <div className="rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">SURF SCHOOL</p>
        <h1 className="mt-2 text-2xl font-semibold">プロと、海へ。</h1>
        <p className="mt-1 text-sm text-sand/90">
          トッププロ・トップアマ・認定インストラクター。
          初めての一本から、あなたのペースで。登録は無料です。
        </p>
      </div>

      <div className="mt-6 space-y-4">
        {rows.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-navy/20 bg-white p-10 text-center text-sm text-navy/60">
            準備中です。まもなく講師が登録されます。
          </div>
        ) : (
          rows.map((r) => {
            const meta = RANK_META[r.rank];
            return (
              <Link
                key={r.id}
                href={`/instructors/${r.id}`}
                className="block rounded-2xl border border-navy/10 bg-white p-5 shadow-sm transition hover:border-ocean/40"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex items-center gap-3">
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-ocean-gradient text-lg font-semibold text-foam">
                      {r.name.slice(0, 1)}
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${meta.badge}`}>
                          {meta.label}
                        </span>
                        {!r.accepting ? (
                          <span className="rounded-full bg-navy/5 px-2 py-0.5 text-xs text-navy/50">
                            満員
                          </span>
                        ) : null}
                      </div>
                      <p className="mt-1 font-semibold text-navy">{r.name}</p>
                      <p className="text-xs text-amber-500">
                        {stars(r.rating_avg)}{" "}
                        <span className="text-navy/40">
                          {r.rating_avg.toFixed(1)}（{r.rating_count}件）
                        </span>
                      </p>
                    </div>
                  </div>
                  <span className="shrink-0 text-xs font-medium text-teal">
                    {priceLabelMonthly(r.monthly_price)}
                  </span>
                </div>
                {r.headline ? (
                  <p className="mt-3 text-sm text-navy/70">{r.headline}</p>
                ) : null}
                <p className="mt-2 text-xs text-navy/40">
                  {r.home_break ? `📍 ${r.home_break}` : ""}
                  {r.years ? `・経験${r.years}年` : ""}
                </p>
              </Link>
            );
          })
        )}
      </div>

      <p className="mt-8 text-center text-xs text-navy/40">
        ※ マッチング後の受講は月額制。運営手数料を除いた額が講師に支払われます（決済は今後対応）。
      </p>
    </main>
  );
}
