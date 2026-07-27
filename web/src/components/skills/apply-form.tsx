"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";
import { Notice } from "@/components/ui/form";

/** SK-04: スキルへの申込／相談（MVP は連絡まで・決済なし） */
export function ApplyForm({ skillId }: { skillId: string }) {
  const router = useRouter();
  const [message, setMessage] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);
  const [pending, setPending] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setPending(true);

    const supabase = createClient();
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) {
      router.push(`/login?next=/skills/${skillId}/apply`);
      return;
    }

    const { error } = await supabase.from("skill_applications").insert({
      skill_id: skillId,
      applicant_id: user.id,
      message: message.trim() || null,
      status: "applied",
    });
    setPending(false);

    if (error) {
      if (error.code === "23505") {
        setError("すでにこのスキルに申し込み済みです。");
      } else {
        setError("申込に失敗しました。自分の出品には申し込めません。");
      }
      return;
    }
    setDone(true);
  }

  if (done) {
    return (
      <Notice kind="success">
        申し込みました。出品者からの連絡をお待ちください🌊（やり取りは今後マイページに表示されます）
      </Notice>
    );
  }

  return (
    <form onSubmit={onSubmit} className="space-y-3">
      {error ? <Notice kind="error">{error}</Notice> : null}
      <textarea
        value={message}
        onChange={(e) => setMessage(e.target.value)}
        rows={4}
        placeholder="希望日・レベル・聞きたいことなどを書きましょう（任意）。"
        className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
      />
      <button
        type="submit"
        disabled={pending}
        className="w-full rounded-lg bg-ocean px-4 py-2.5 font-medium text-foam transition hover:bg-navy disabled:opacity-60"
      >
        {pending ? "送信中…" : "申し込む・相談する"}
      </button>
    </form>
  );
}
