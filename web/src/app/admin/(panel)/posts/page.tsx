import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { CATEGORIES, CATEGORY_LABEL, type Category } from "@/lib/community";
import { ConfirmSubmit } from "@/components/admin/confirm-submit";
import {
  setPostStatus,
  toggleFeatured,
  setPostCategory,
  deletePost,
} from "@/app/admin/actions";

export const metadata: Metadata = { title: "投稿管理｜運営管理" };

type Row = {
  id: string;
  category: Category;
  title: string | null;
  body: string;
  status: string;
  is_featured: boolean;
  like_count: number;
  created_at: string;
  author: { display_name: string | null } | null;
};

const STATUS_LABEL: Record<string, string> = {
  published: "公開",
  hidden: "非公開",
  draft: "下書き",
};

export default async function AdminPosts() {
  const supabase = await createClient();
  const { data } = await supabase
    .from("posts")
    .select(
      "id, category, title, body, status, is_featured, like_count, created_at, author:members!posts_author_id_fkey(display_name)",
    )
    .order("created_at", { ascending: false })
    .limit(100);
  const posts = (data ?? []) as unknown as Row[];

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">投稿管理</h1>
      <p className="mt-1 text-sm text-slate-500">{posts.length} 件</p>

      <div className="mt-6 space-y-3">
        {posts.length === 0 ? (
          <p className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-400">
            投稿がありません。
          </p>
        ) : null}

        {posts.map((p) => (
          <div key={p.id} className="rounded-lg border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-center gap-2 text-xs">
              <span className="rounded bg-slate-100 px-2 py-0.5 text-slate-600">
                {CATEGORY_LABEL[p.category]}
              </span>
              <span
                className={`rounded px-2 py-0.5 ${
                  p.status === "published"
                    ? "bg-emerald-100 text-emerald-700"
                    : "bg-slate-200 text-slate-600"
                }`}
              >
                {STATUS_LABEL[p.status] ?? p.status}
              </span>
              {p.is_featured ? (
                <span className="rounded bg-amber-100 px-2 py-0.5 text-amber-700">注目</span>
              ) : null}
              <span className="text-slate-400">
                {p.author?.display_name ?? "メンバー"}・🌊{p.like_count}
              </span>
            </div>

            <p className="mt-2 text-sm font-medium text-slate-800">
              {p.title ?? "（無題）"}
            </p>
            <p className="mt-1 line-clamp-2 text-sm text-slate-500">{p.body}</p>

            <div className="mt-3 flex flex-wrap items-center gap-2">
              {p.status === "published" ? (
                <form action={setPostStatus}>
                  <input type="hidden" name="id" value={p.id} />
                  <input type="hidden" name="status" value="hidden" />
                  <Btn className="bg-slate-600">非公開にする</Btn>
                </form>
              ) : (
                <form action={setPostStatus}>
                  <input type="hidden" name="id" value={p.id} />
                  <input type="hidden" name="status" value="published" />
                  <Btn className="bg-emerald-600">公開する</Btn>
                </form>
              )}

              <form action={toggleFeatured}>
                <input type="hidden" name="id" value={p.id} />
                <input type="hidden" name="featured" value={String(!p.is_featured)} />
                <Btn className="bg-amber-500">
                  {p.is_featured ? "注目を外す" : "注目にする"}
                </Btn>
              </form>

              <form action={setPostCategory} className="inline-flex items-center gap-1">
                <input type="hidden" name="id" value={p.id} />
                <select
                  name="category"
                  defaultValue={p.category}
                  className="rounded border border-slate-300 px-2 py-1 text-xs"
                >
                  {CATEGORIES.map((c) => (
                    <option key={c.key} value={c.key}>
                      {c.label}
                    </option>
                  ))}
                </select>
                <Btn className="bg-slate-200 !text-slate-700">変更</Btn>
              </form>

              <form action={deletePost}>
                <input type="hidden" name="id" value={p.id} />
                <ConfirmSubmit
                  className="rounded bg-red-600 px-2.5 py-1 text-xs font-medium text-white"
                  message="この投稿を削除します。よろしいですか？"
                >
                  削除
                </ConfirmSubmit>
              </form>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function Btn({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <button
      className={`rounded px-2.5 py-1 text-xs font-medium text-white ${className ?? "bg-slate-600"}`}
    >
      {children}
    </button>
  );
}
