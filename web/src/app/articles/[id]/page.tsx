import Link from "next/link";
import { notFound } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { RANK_META, type InstructorRank } from "@/lib/instructors";
import { DEMO, demoArticles } from "@/lib/demo";

type Props = { params: Promise<{ id: string }> };

type Article = {
  id: string;
  title: string;
  body: string;
  author: string;
  rank: InstructorRank | null;
  published_at: string;
};

export default async function ArticleDetail({ params }: Props) {
  const { id } = await params;

  let a: Article | null = null;
  if (DEMO) {
    const d = demoArticles.find((x) => x.id === id);
    if (!d) notFound();
    a = { id: d.id, title: d.title, body: d.body, author: d.author, rank: d.rank, published_at: d.published_at };
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("articles")
      .select("id, title, body, published_at, author:members!articles_author_id_fkey(display_name)")
      .eq("id", id)
      .single();
    if (!data) notFound();
    const d = data as unknown as {
      id: string;
      title: string;
      body: string;
      published_at: string;
      author: { display_name: string | null } | null;
    };
    a = {
      id: d.id,
      title: d.title,
      body: d.body,
      author: d.author?.display_name ?? "講師",
      rank: null,
      published_at: d.published_at,
    };
  }

  if (!a) notFound();

  return (
    <main className="mx-auto max-w-2xl px-4 py-8">
      <Link href="/articles" className="text-sm text-ocean hover:underline">
        ← プロのブログへ
      </Link>

      <article className="mt-4 rounded-2xl border border-navy/10 bg-white p-6 shadow-sm">
        <div className="flex items-center gap-2 text-xs text-navy/40">
          <span>{new Date(a.published_at).toLocaleDateString("ja-JP")}</span>
          {a.rank ? (
            <span className={`rounded-full px-2 py-0.5 font-medium ${RANK_META[a.rank].badge}`}>
              {RANK_META[a.rank].label}
            </span>
          ) : null}
        </div>
        <h1 className="mt-2 text-2xl font-semibold text-navy">{a.title}</h1>
        <p className="mt-1 text-sm text-navy/50">by {a.author}</p>
        <div className="mt-6 whitespace-pre-wrap leading-relaxed text-navy/80">{a.body}</div>
      </article>

      <div className="mt-6 text-center">
        <Link
          href="/instructors"
          className="inline-block rounded-full bg-teal px-6 py-2.5 text-sm font-medium text-navy transition hover:bg-ocean hover:text-foam"
        >
          この著者のスクールを見る 🏄
        </Link>
      </div>
    </main>
  );
}
