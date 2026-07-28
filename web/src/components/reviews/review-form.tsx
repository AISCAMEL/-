"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";
import { Notice } from "@/components/ui/form";

/** ★評価＋コメントの投稿（スキル・講師どちらにも使う） */
export function ReviewForm({
  targetType,
  targetId,
  canReview,
  loginNext,
}: {
  targetType: "skill" | "instructor";
  targetId: string;
  canReview: boolean;
  loginNext: string;
}) {
  const router = useRouter();
  const [rating, setRating] = useState(5);
  const [hover, setHover] = useState(0);
  const [body, setBody] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);
  const [pending, setPending] = useState(false);

  if (!canReview) {
    return (
      <div className="rounded-xl border border-ocean/20 bg-white p-5 text-center text-sm text-navy/70">
        口コミを書くには{" "}
        <a href={`/login?next=${loginNext}`} className="font-medium text-ocean hover:underline">
          ログイン
        </a>
        {" / "}
        <a href="/signup" className="font-medium text-ocean hover:underline">
          新規登録
        </a>
      </div>
    );
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setPending(true);

    const supabase = createClient();
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) {
      router.push(`/login?next=${loginNext}`);
      return;
    }

    const { error } = await supabase.from("reviews").insert({
      target_type: targetType,
      target_id: targetId,
      author_id: user.id,
      rating,
      body: body.trim() || null,
    });
    setPending(false);

    if (error) {
      setError(
        error.code === "23505"
          ? "すでに口コミを投稿済みです。"
          : "投稿に失敗しました（自分自身は評価できません）。",
      );
      return;
    }
    setDone(true);
    router.refresh();
  }

  if (done) {
    return <Notice kind="success">口コミを投稿しました。ありがとうございます🌊</Notice>;
  }

  return (
    <form onSubmit={onSubmit} className="space-y-3 rounded-xl border border-navy/10 bg-white p-4">
      {error ? <Notice kind="error">{error}</Notice> : null}
      <div className="flex items-center gap-1">
        {[1, 2, 3, 4, 5].map((n) => (
          <button
            key={n}
            type="button"
            onClick={() => setRating(n)}
            onMouseEnter={() => setHover(n)}
            onMouseLeave={() => setHover(0)}
            className={`text-2xl leading-none ${
              n <= (hover || rating) ? "text-amber-400" : "text-navy/20"
            }`}
            aria-label={`${n}つ星`}
          >
            ★
          </button>
        ))}
        <span className="ml-2 text-sm text-navy/50">{rating}.0</span>
      </div>
      <textarea
        value={body}
        onChange={(e) => setBody(e.target.value)}
        rows={3}
        placeholder="レッスンの感想を、これから受ける人へ。"
        className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
      />
      <button
        type="submit"
        disabled={pending}
        className="rounded-lg bg-ocean px-4 py-2 text-sm font-medium text-foam transition hover:bg-navy disabled:opacity-60"
      >
        {pending ? "投稿中…" : "口コミを投稿する"}
      </button>
    </form>
  );
}
