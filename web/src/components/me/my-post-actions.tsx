"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";

/** 自分の投稿の公開切替・削除（RLSで本人のみ許可） */
export function MyPostActions({
  postId,
  status,
}: {
  postId: string;
  status: string;
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);

  async function setStatus(next: "published" | "hidden") {
    setPending(true);
    const supabase = createClient();
    await supabase.from("posts").update({ status: next }).eq("id", postId);
    setPending(false);
    router.refresh();
  }

  async function remove() {
    if (!window.confirm("この投稿を削除します。よろしいですか？")) return;
    setPending(true);
    const supabase = createClient();
    await supabase.from("posts").delete().eq("id", postId);
    setPending(false);
    router.refresh();
  }

  return (
    <div className="flex items-center gap-2">
      {status === "published" ? (
        <button
          onClick={() => setStatus("hidden")}
          disabled={pending}
          className="rounded border border-navy/15 px-2.5 py-1 text-xs text-navy/60 hover:border-ocean"
        >
          非公開にする
        </button>
      ) : status === "hidden" ? (
        <button
          onClick={() => setStatus("published")}
          disabled={pending}
          className="rounded border border-teal/40 px-2.5 py-1 text-xs text-teal hover:bg-teal/10"
        >
          公開する
        </button>
      ) : null}
      <button
        onClick={remove}
        disabled={pending}
        className="rounded border border-red-200 px-2.5 py-1 text-xs text-red-500 hover:bg-red-50"
      >
        削除
      </button>
    </div>
  );
}
