import Link from "next/link";
import { CATEGORY_LABEL, type Category } from "@/lib/community";

export type PostSummary = {
  id: string;
  category: Category;
  title: string | null;
  body: string;
  like_count: number;
  created_at: string;
  author: { display_name: string | null; role: string } | null;
};

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString("ja-JP", {
    month: "long",
    day: "numeric",
  });
}

export function PostCard({
  post,
  blurred = false,
}: {
  post: PostSummary;
  blurred?: boolean;
}) {
  const content = (
    <article className="rounded-2xl border border-navy/10 bg-white p-5 shadow-sm transition hover:border-ocean/40">
      <div className="flex items-center gap-2 text-xs">
        <span className="rounded-full bg-ocean/10 px-2.5 py-0.5 font-medium text-ocean">
          {CATEGORY_LABEL[post.category]}
        </span>
        <span className="text-navy/40">{formatDate(post.created_at)}</span>
      </div>
      {post.title ? (
        <h3 className="mt-3 font-semibold text-navy">{post.title}</h3>
      ) : null}
      <p className="mt-2 line-clamp-3 text-sm text-navy/70 whitespace-pre-wrap">
        {post.body}
      </p>
      <div className="mt-4 flex items-center justify-between text-xs text-navy/50">
        <span>{post.author?.display_name ?? "メンバー"}</span>
        <span>🌊 {post.like_count}</span>
      </div>
    </article>
  );

  if (blurred) {
    return (
      <div
        aria-hidden
        className="pointer-events-none select-none blur-[6px] saturate-50"
      >
        {content}
      </div>
    );
  }

  return <Link href={`/community/posts/${post.id}`}>{content}</Link>;
}
