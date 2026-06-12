"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";

export function LikeButton({
  postId,
  initialCount,
  initialLiked,
  canLike,
}: {
  postId: string;
  initialCount: number;
  initialLiked: boolean;
  canLike: boolean;
}) {
  const router = useRouter();
  const [count, setCount] = useState(initialCount);
  const [liked, setLiked] = useState(initialLiked);
  const [pending, setPending] = useState(false);

  async function toggle() {
    if (!canLike) {
      router.push(`/login?next=/community/posts/${postId}`);
      return;
    }
    if (pending) return;
    setPending(true);

    const supabase = createClient();
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) {
      router.push(`/login?next=/community/posts/${postId}`);
      return;
    }

    // 楽観的更新
    const nextLiked = !liked;
    setLiked(nextLiked);
    setCount((c) => c + (nextLiked ? 1 : -1));

    if (nextLiked) {
      await supabase.from("likes").insert({ post_id: postId, member_id: user.id });
    } else {
      await supabase
        .from("likes")
        .delete()
        .eq("post_id", postId)
        .eq("member_id", user.id);
    }
    setPending(false);
  }

  return (
    <button
      onClick={toggle}
      disabled={pending}
      className={`inline-flex items-center gap-1.5 rounded-full border px-4 py-1.5 text-sm transition ${
        liked
          ? "border-teal bg-teal/15 text-teal"
          : "border-navy/15 text-navy/60 hover:border-teal"
      }`}
    >
      🌊 <span>{count}</span>
    </button>
  );
}
