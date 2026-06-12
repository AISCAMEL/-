import Link from "next/link";
import { notFound } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { CATEGORY_LABEL, type Category } from "@/lib/community";
import { LikeButton } from "@/components/community/like-button";
import { CommentForm } from "@/components/community/comment-form";

type Props = { params: Promise<{ id: string }> };

type PostRow = {
  id: string;
  category: Category;
  title: string | null;
  body: string;
  like_count: number;
  created_at: string;
  author: { display_name: string | null; role: string } | null;
};

type CommentRow = {
  id: string;
  body: string;
  created_at: string;
  author: { display_name: string | null } | null;
};

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString("ja-JP", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

export default async function PostDetail({ params }: Props) {
  const { id } = await params;
  const supabase = await createClient();
  const member = await getCurrentMember();

  const { data } = await supabase
    .from("posts")
    .select(
      "id, category, title, body, like_count, created_at, author:members!posts_author_id_fkey(display_name, role)",
    )
    .eq("id", id)
    .single();

  const post = data as unknown as PostRow | null;
  if (!post) notFound();

  const { data: commentData } = await supabase
    .from("comments")
    .select(
      "id, body, created_at, author:members!comments_author_id_fkey(display_name)",
    )
    .eq("post_id", id)
    .eq("status", "published")
    .order("created_at", { ascending: true });
  const comments = (commentData ?? []) as unknown as CommentRow[];

  // 現在ユーザーがいいね済みか
  let liked = false;
  if (member) {
    const { data: likeRow } = await supabase
      .from("likes")
      .select("id")
      .eq("post_id", id)
      .eq("member_id", member.id)
      .maybeSingle();
    liked = Boolean(likeRow);
  }

  return (
    <main className="mx-auto max-w-2xl px-4 py-8">
      <Link href="/community" className="text-sm text-ocean hover:underline">
        ← コミュニティへ
      </Link>

      <article className="mt-4 rounded-2xl border border-navy/10 bg-white p-6 shadow-sm">
        <div className="flex items-center gap-2 text-xs">
          <span className="rounded-full bg-ocean/10 px-2.5 py-0.5 font-medium text-ocean">
            {CATEGORY_LABEL[post.category]}
          </span>
          <span className="text-navy/40">{formatDate(post.created_at)}</span>
        </div>
        {post.title ? (
          <h1 className="mt-3 text-xl font-semibold text-navy">{post.title}</h1>
        ) : null}
        <p className="mt-3 whitespace-pre-wrap text-navy/80">{post.body}</p>
        <div className="mt-6 flex items-center justify-between border-t border-navy/10 pt-4">
          <span className="text-sm text-navy/50">
            {post.author?.display_name ?? "メンバー"}
          </span>
          <LikeButton
            postId={post.id}
            initialCount={post.like_count}
            initialLiked={liked}
            canLike={Boolean(member)}
          />
        </div>
      </article>

      {/* コメント */}
      <section className="mt-8">
        <h2 className="text-sm font-semibold text-navy/70">
          コメント（{comments.length}）
        </h2>
        <div className="mt-4 space-y-3">
          {comments.map((c) => (
            <div
              key={c.id}
              className="rounded-xl border border-navy/10 bg-white p-4"
            >
              <p className="whitespace-pre-wrap text-sm text-navy/80">{c.body}</p>
              <p className="mt-2 text-xs text-navy/40">
                {c.author?.display_name ?? "メンバー"}・{formatDate(c.created_at)}
              </p>
            </div>
          ))}
        </div>

        <div className="mt-6">
          {member ? (
            <CommentForm postId={post.id} />
          ) : (
            <div className="rounded-xl border border-ocean/20 bg-white p-5 text-center text-sm text-navy/70">
              コメントするには{" "}
              <Link
                href={`/login?next=/community/posts/${post.id}`}
                className="font-medium text-ocean hover:underline"
              >
                ログイン
              </Link>
              {" / "}
              <Link
                href="/signup"
                className="font-medium text-ocean hover:underline"
              >
                新規登録
              </Link>
            </div>
          )}
        </div>
      </section>
    </main>
  );
}
