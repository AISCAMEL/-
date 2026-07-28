import Link from "next/link";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { RANK_META, type InstructorRank } from "@/lib/instructors";
import { AdBanner } from "@/components/ads/ad-banner";
import { DEMO, demoArticles } from "@/lib/demo";

export const metadata: Metadata = {
  title: "プロのブログ｜IWASAWA SURF BASE",
  description: "プロサーファー・講師が発信する、初心者に役立つコラム。",
};

type Card = {
  id: string;
  title: string;
  author: string;
  rank: InstructorRank | null;
  excerpt: string;
  published_at: string;
};

export default async function ArticlesPage() {
  let cards: Card[] = [];

  if (DEMO) {
    cards = demoArticles.map((a) => ({
      id: a.id,
      title: a.title,
      author: a.author,
      rank: a.rank,
      excerpt: a.excerpt,
      published_at: a.published_at,
    }));
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("articles")
      .select("id, title, body, published_at, author:members!articles_author_id_fkey(display_name)")
      .eq("status", "published")
      .order("published_at", { ascending: false })
      .limit(30);
    cards = ((data ?? []) as unknown as {
      id: string;
      title: string;
      body: string;
      published_at: string;
      author: { display_name: string | null } | null;
    }[]).map((a) => ({
      id: a.id,
      title: a.title,
      author: a.author?.display_name ?? "講師",
      rank: null,
      excerpt: a.body.slice(0, 70),
      published_at: a.published_at,
    }));
  }

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      <div className="rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">PRO BLOG</p>
        <h1 className="mt-2 text-2xl font-semibold">プロのブログ</h1>
        <p className="mt-1 text-sm text-sand/90">
          プロ・講師が発信する、初心者に役立つコラム。
        </p>
      </div>

      <div className="mt-6">
        <AdBanner placement="blog" />
      </div>

      <div className="mt-4 space-y-4">
        {cards.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-navy/20 bg-white p-10 text-center text-sm text-navy/60">
            まだ記事がありません。
          </div>
        ) : (
          cards.map((a) => (
            <Link
              key={a.id}
              href={`/articles/${a.id}`}
              className="block rounded-2xl border border-navy/10 bg-white p-5 shadow-sm transition hover:border-ocean/40"
            >
              <div className="flex items-center gap-2 text-xs text-navy/40">
                <span>{new Date(a.published_at).toLocaleDateString("ja-JP")}</span>
                {a.rank ? (
                  <span className={`rounded-full px-2 py-0.5 font-medium ${RANK_META[a.rank].badge}`}>
                    {RANK_META[a.rank].label}
                  </span>
                ) : null}
              </div>
              <h2 className="mt-2 font-semibold text-navy">{a.title}</h2>
              <p className="mt-1.5 line-clamp-2 text-sm text-navy/60">{a.excerpt}</p>
              <p className="mt-3 text-xs text-navy/40">by {a.author}</p>
            </Link>
          ))
        )}
      </div>
    </main>
  );
}
