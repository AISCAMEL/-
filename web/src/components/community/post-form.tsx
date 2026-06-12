"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";
import { CATEGORIES, type Category } from "@/lib/community";
import { Notice } from "@/components/ui/form";
import type { MemberRole } from "@/lib/auth";

const RANK: Record<MemberRole, number> = {
  visitor: 0,
  beginner: 1,
  local: 2,
  staff: 3,
  admin: 4,
};

export function PostForm({ role }: { role: MemberRole }) {
  const router = useRouter();
  const [category, setCategory] = useState<Category>("questions");
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const canWaves = RANK[role] >= RANK.local;

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!body.trim()) {
      setError("本文を入力してください。");
      return;
    }
    setError(null);
    setPending(true);

    const supabase = createClient();
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) {
      router.push("/login?next=/community/new");
      return;
    }

    const { data, error } = await supabase
      .from("posts")
      .insert({
        author_id: user.id,
        category,
        title: title.trim() || null,
        body: body.trim(),
        status: "published",
      })
      .select("id")
      .single();
    setPending(false);

    if (error) {
      setError("投稿に失敗しました。権限やネットワークをご確認ください。");
      return;
    }
    router.push(`/community/posts/${data.id}`);
    router.refresh();
  }

  return (
    <form onSubmit={onSubmit} className="space-y-5">
      {error ? <Notice kind="error">{error}</Notice> : null}

      <div>
        <span className="mb-1.5 block text-sm font-medium text-navy/80">
          カテゴリ
        </span>
        <div className="flex flex-wrap gap-2">
          {CATEGORIES.map((c) => {
            const disabled = c.localOnly && !canWaves;
            return (
              <button
                key={c.key}
                type="button"
                disabled={disabled}
                onClick={() => setCategory(c.key)}
                title={disabled ? "波情報は Local 以上が投稿できます" : c.hint}
                className={`rounded-full border px-3 py-1.5 text-sm transition ${
                  category === c.key
                    ? "border-ocean bg-ocean text-foam"
                    : "border-navy/15 bg-white text-navy/70 hover:border-ocean"
                } ${disabled ? "cursor-not-allowed opacity-40" : ""}`}
              >
                {c.label}
              </button>
            );
          })}
        </div>
        {!canWaves ? (
          <p className="mt-2 text-xs text-navy/40">
            ※「波情報」は Local 以上の方が投稿できます。
          </p>
        ) : null}
      </div>

      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-navy/80">
          タイトル（任意）
        </span>
        <input
          type="text"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
        />
      </label>

      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-navy/80">
          本文
        </span>
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          rows={6}
          required
          placeholder="海のこと、質問、体験。気軽にどうぞ🌊"
          className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
        />
      </label>

      <button
        type="submit"
        disabled={pending}
        className="w-full rounded-lg bg-ocean px-4 py-2.5 font-medium text-foam transition hover:bg-navy disabled:opacity-60"
      >
        {pending ? "投稿中…" : "投稿する"}
      </button>
    </form>
  );
}
