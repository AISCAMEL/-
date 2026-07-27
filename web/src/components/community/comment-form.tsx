"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";
import { Notice } from "@/components/ui/form";

export function CommentForm({ postId }: { postId: string }) {
  const router = useRouter();
  const [body, setBody] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!body.trim()) return;
    setError(null);
    setPending(true);

    const supabase = createClient();
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) {
      router.push(`/login?next=/community/posts/${postId}`);
      return;
    }

    const { error } = await supabase
      .from("comments")
      .insert({ post_id: postId, author_id: user.id, body: body.trim() });
    setPending(false);

    if (error) {
      setError("コメントの投稿に失敗しました。");
      return;
    }
    setBody("");
    router.refresh();
  }

  return (
    <form onSubmit={onSubmit} className="space-y-3">
      {error ? <Notice kind="error">{error}</Notice> : null}
      <textarea
        value={body}
        onChange={(e) => setBody(e.target.value)}
        rows={3}
        placeholder="やさしいひと言を。初めての人も読んでいます🌊"
        className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
      />
      <button
        type="submit"
        disabled={pending}
        className="rounded-lg bg-ocean px-4 py-2 text-sm font-medium text-foam transition hover:bg-navy disabled:opacity-60"
      >
        {pending ? "送信中…" : "コメントする"}
      </button>
    </form>
  );
}
