import Link from "next/link";
import { notFound } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { RANK_META, stars, priceLabelMonthly, type InstructorRank } from "@/lib/instructors";
import { ReviewForm } from "@/components/reviews/review-form";
import { DEMO, demoInstructors, demoInstructorReviews } from "@/lib/demo";

type Props = { params: Promise<{ id: string }> };

type Instructor = {
  id: string;
  name: string;
  rank: InstructorRank;
  headline: string | null;
  bio: string | null;
  achievements: string | null;
  home_break: string | null;
  years: number | null;
  monthly_price: number | null;
  accepting: boolean;
  rating_avg: number;
  rating_count: number;
};

type Review = { id: string; author: string; rating: number; body: string | null };

export default async function InstructorDetail({ params }: Props) {
  const { id } = await params;
  const member = await getCurrentMember();

  let ins: Instructor | null = null;
  let reviews: Review[] = [];

  if (DEMO) {
    const d = demoInstructors.find((x) => x.id === id);
    if (!d) notFound();
    ins = { ...d };
    reviews = (demoInstructorReviews[id] ?? []).map((r) => ({
      id: r.id,
      author: r.author,
      rating: r.rating,
      body: r.body,
    }));
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("instructor_profiles")
      .select(
        "member_id, rank, headline, bio, achievements, home_break, years, monthly_price, accepting, rating_avg, rating_count, member:members!instructor_profiles_member_id_fkey(display_name)",
      )
      .eq("member_id", id)
      .single();
    if (!data) notFound();
    const d = data as unknown as {
      member_id: string;
      rank: InstructorRank;
      headline: string | null;
      bio: string | null;
      achievements: string | null;
      home_break: string | null;
      years: number | null;
      monthly_price: number | null;
      accepting: boolean;
      rating_avg: number;
      rating_count: number;
      member: { display_name: string | null } | null;
    };
    ins = {
      id: d.member_id,
      name: d.member?.display_name ?? "講師",
      rank: d.rank,
      headline: d.headline,
      bio: d.bio,
      achievements: d.achievements,
      home_break: d.home_break,
      years: d.years,
      monthly_price: d.monthly_price,
      accepting: d.accepting,
      rating_avg: d.rating_avg,
      rating_count: d.rating_count,
    };
    const { data: rv } = await supabase
      .from("reviews")
      .select("id, rating, body, author:members!reviews_author_id_fkey(display_name)")
      .eq("target_type", "instructor")
      .eq("target_id", id)
      .order("created_at", { ascending: false });
    reviews = ((rv ?? []) as unknown as { id: string; rating: number; body: string | null; author: { display_name: string | null } | null }[]).map(
      (r) => ({ id: r.id, author: r.author?.display_name ?? "メンバー", rating: r.rating, body: r.body }),
    );
  }

  if (!ins) notFound();
  const meta = RANK_META[ins.rank];

  return (
    <main className="mx-auto max-w-2xl px-4 py-8">
      <Link href="/instructors" className="text-sm text-ocean hover:underline">
        ← サーフィンスクールへ
      </Link>

      {/* プロフィール */}
      <section className="mt-4 overflow-hidden rounded-2xl border border-navy/10 bg-white shadow-sm">
        <div className="bg-ocean-gradient p-6 text-foam">
          <div className="flex items-center gap-4">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-white/15 text-2xl font-semibold">
              {ins.name.slice(0, 1)}
            </div>
            <div>
              <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${meta.badge}`}>
                {meta.label}
              </span>
              <h1 className="mt-1 text-xl font-semibold">{ins.name}</h1>
              <p className="text-xs text-amber-300">
                {stars(ins.rating_avg)}{" "}
                <span className="text-sand/80">
                  {ins.rating_avg.toFixed(1)}（{ins.rating_count}件）
                </span>
              </p>
            </div>
          </div>
          {ins.headline ? <p className="mt-4 text-sm text-sand/95">{ins.headline}</p> : null}
        </div>

        <div className="space-y-4 p-6">
          {ins.bio ? <p className="whitespace-pre-wrap text-sm text-navy/80">{ins.bio}</p> : null}
          {ins.achievements ? (
            <div>
              <p className="text-xs font-semibold text-navy/50">主な実績</p>
              <p className="mt-1 text-sm text-navy/80">{ins.achievements}</p>
            </div>
          ) : null}
          <div className="flex flex-wrap gap-x-6 gap-y-1 text-xs text-navy/50">
            {ins.home_break ? <span>📍 {ins.home_break}</span> : null}
            {ins.years ? <span>🏄 経験{ins.years}年</span> : null}
            <span>{priceLabelMonthly(ins.monthly_price)}</span>
          </div>
        </div>
      </section>

      {/* 受講CTA */}
      <div className="mt-6">
        {ins.accepting ? (
          <button
            className="block w-full rounded-xl bg-ocean px-4 py-3 text-center font-medium text-foam transition hover:bg-navy"
            type="button"
          >
            この講師に受講を申し込む（月額）
          </button>
        ) : (
          <div className="rounded-xl border border-navy/10 bg-white p-4 text-center text-sm text-navy/60">
            現在は満員です。空き次第ご案内します。
          </div>
        )}
        <p className="mt-2 text-center text-xs text-navy/40">
          ※ 登録・相談は無料。受講開始後の月額から運営手数料を差し引いて講師にお支払いします（決済は今後対応）。
        </p>
      </div>

      {/* 口コミ */}
      <section className="mt-10">
        <h2 className="text-sm font-semibold text-navy/70">口コミ（{reviews.length}）</h2>
        <div className="mt-4 space-y-3">
          {reviews.length === 0 ? (
            <p className="rounded-xl border border-dashed border-navy/20 bg-white p-5 text-center text-sm text-navy/50">
              まだ口コミがありません。最初の感想を、あなたから。
            </p>
          ) : (
            reviews.map((r) => (
              <div key={r.id} className="rounded-xl border border-navy/10 bg-white p-4">
                <p className="text-sm text-amber-400">{stars(r.rating)}</p>
                {r.body ? (
                  <p className="mt-1 whitespace-pre-wrap text-sm text-navy/80">{r.body}</p>
                ) : null}
                <p className="mt-2 text-xs text-navy/40">{r.author}</p>
              </div>
            ))
          )}
        </div>

        <div className="mt-6">
          <ReviewForm
            targetType="instructor"
            targetId={ins.id}
            canReview={Boolean(member) && member?.id !== ins.id}
            loginNext={`/instructors/${ins.id}`}
          />
        </div>
      </section>
    </main>
  );
}
