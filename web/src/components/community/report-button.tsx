"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";

/** 投稿/コメントの通報。送信すると管理画面の「通報・要確認」に流れる。 */
export function ReportButton({
  targetType,
  targetId,
  canReport,
}: {
  targetType: "post" | "comment" | "skill";
  targetId: string;
  canReport: boolean;
}) {
  const router = useRouter();
  const [done, setDone] = useState(false);
  const [pending, setPending] = useState(false);

  async function report() {
    if (!canReport) {
      router.push(`/login?next=/community/posts/${targetId}`);
      return;
    }
    const reason = window.prompt("通報の理由を教えてください（任意）") ?? "";
    if (reason === null) return;
    setPending(true);

    const supabase = createClient();
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) {
      router.push("/login");
      return;
    }
    await supabase.from("reports").insert({
      target_type: targetType,
      target_id: targetId,
      reporter_id: user.id,
      reason: reason.trim() || null,
      status: "open",
    });
    setPending(false);
    setDone(true);
  }

  if (done) {
    return <span className="text-xs text-navy/40">通報を受け付けました</span>;
  }

  return (
    <button
      onClick={report}
      disabled={pending}
      className="text-xs text-navy/40 hover:text-red-500"
    >
      通報する
    </button>
  );
}
