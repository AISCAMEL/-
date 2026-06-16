import Link from "next/link";
import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { CommunityHeader } from "@/components/community/community-header";
import { CATEGORY_LABEL, type Category } from "@/lib/community";
import { MyPostActions } from "@/components/me/my-post-actions";

export const metadata: Metadata = { title: "自分の投稿｜IWASAWA SURF BASE" };

type Row = {
  id: string;
  category: Category;
  title: string | null;
  body: string;
  status: string;
  like_count: number;
  created_at: string;
};

const STATUS_LABEL: Record<string, string> = {
  published: "公開",
  hidden: "非公開",
  draft: "下書き",
};

export default async function MyPostsPage() {
  const member = await getCurrentMember();
  if (!member) redirect("/login?next=/me/posts");

  const supabase = await createClient();
  const { data } = await supabase
    .from("posts")
    .select("id, category, title, body, status, like_count, created_at")
    .eq("author_id", member.id)
    .order("created_at", { ascending: false });
  const posts = (data ?? []) as unknown as Row[];

  return (
    <div className="min-h-screen bg-foam">
      <CommunityHeader />
      <main className="mx-auto max-w-3xl px-4 py-8">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold text-navy">自分の投稿</h1>
          <Link
            href="/community/new"
            className="rounded-lg bg-ocean px-3 py-1.5 text-sm font-medium text-foam transition hover:bg-navy"
          >
            ＋ 投稿する
          </Link>
        </div>
        <p className="mt-1 text-sm text-navy/50">{posts.length} 件</p>

        <div className="mt-6 space-y-3">
          {posts.length === 0 ? (
            <p className="rounded-2xl border border-dashed border-navy/20 bg-white p-10 text-center text-sm text-navy/60">
              まだ投稿がありません。最初の波を、あなたから。
            </p>
          ) : (
            posts.map((p) => (
              <div
                key={p.id}
                className="rounded-2xl border border-navy/10 bg-white p-5"
              >
                <div className="flex items-center gap-2 text-xs">
                  <span className="rounded-full bg-ocean/10 px-2.5 py-0.5 font-medium text-ocean">
                    {CATEGORY_LABEL[p.category]}
                  </span>
                  <span
                    className={`rounded-full px-2.5 py-0.5 ${
                      p.status === "published"
                        ? "bg-teal/10 text-teal"
                        : "bg-navy/5 text-navy/50"
                    }`}
                  >
                    {STATUS_LABEL[p.status] ?? p.status}
                  </span>
                  <span className="text-navy/40">🌊 {p.like_count}</span>
                </div>
                <Link href={`/community/posts/${p.id}`} className="mt-2 block">
                  {p.title ? (
                    <p className="font-semibold text-navy">{p.title}</p>
                  ) : null}
                  <p className="mt-1 line-clamp-2 text-sm text-navy/60">{p.body}</p>
                </Link>
                <div className="mt-3 flex justify-end">
                  <MyPostActions postId={p.id} status={p.status} />
                </div>
              </div>
            ))
          )}
        </div>
      </main>
    </div>
  );
}
