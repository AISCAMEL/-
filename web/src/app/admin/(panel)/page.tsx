import Link from "next/link";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { CATEGORIES } from "@/lib/community";

export const metadata: Metadata = { title: "ダッシュボード｜運営管理" };

function todayStartISO() {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d.toISOString();
}

type CountFilter = { gte: (c: string, v: string) => CountFilter; eq: (c: string, v: string) => CountFilter };

async function count(table: string, build?: (q: CountFilter) => CountFilter) {
  const supabase = await createClient();
  const base = supabase.from(table).select("*", { count: "exact", head: true });
  const q = build ? build(base as unknown as CountFilter) : base;
  const { count: c } = await (q as unknown as typeof base);
  return c ?? 0;
}

export default async function Dashboard() {
  const today = todayStartISO();

  const [members, newMembers, posts, todayPosts, openReports] = await Promise.all([
    count("members"),
    count("members", (q) => q.gte("created_at", today)),
    count("posts", (q) => q.eq("status", "published")),
    count("posts", (q) => q.gte("created_at", today)),
    count("reports", (q) => q.eq("status", "open")),
  ]);

  // 人気カテゴリ（投稿数）
  const supabase = await createClient();
  const catCounts = await Promise.all(
    CATEGORIES.map(async (c) => {
      const { count: n } = await supabase
        .from("posts")
        .select("*", { count: "exact", head: true })
        .eq("category", c.key)
        .eq("status", "published");
      return { label: c.label, n: n ?? 0 };
    }),
  );
  catCounts.sort((a, b) => b.n - a.n);

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">ダッシュボード</h1>
      <p className="mt-1 text-sm text-slate-500">岩沢サーフベースの今日の状況</p>

      <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
        <Metric label="会員数" value={members} />
        <Metric label="新規登録（今日）" value={newMembers} />
        <Metric label="今日の投稿数" value={todayPosts} />
        <Metric label="公開中の投稿" value={posts} />
        <Metric
          label="承認待ち（通報）"
          value={openReports}
          href="/admin/reports"
          highlight={openReports > 0}
        />
      </div>

      <section className="mt-8">
        <h2 className="text-sm font-semibold text-slate-600">よく投稿されるカテゴリ</h2>
        <div className="mt-3 rounded-lg border border-slate-200 bg-white p-4">
          <ul className="space-y-2 text-sm">
            {catCounts.map((c) => (
              <li key={c.label} className="flex items-center justify-between">
                <span className="text-slate-600">{c.label}</span>
                <span className="font-medium text-slate-800">{c.n}</span>
              </li>
            ))}
          </ul>
        </div>
      </section>
    </div>
  );
}

function Metric({
  label,
  value,
  href,
  highlight,
}: {
  label: string;
  value: number;
  href?: string;
  highlight?: boolean;
}) {
  const inner = (
    <div
      className={`rounded-lg border bg-white p-5 ${
        highlight ? "border-amber-300 bg-amber-50" : "border-slate-200"
      }`}
    >
      <p className="text-xs text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-slate-900">{value}</p>
    </div>
  );
  return href ? <Link href={href}>{inner}</Link> : inner;
}
