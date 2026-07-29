"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";
import { Field, Notice } from "@/components/ui/form";
import { SITE } from "@/lib/site";

type Initial = {
  headline: string | null;
  bio: string | null;
  achievements: string | null;
  home_break: string | null;
  years: number | null;
  monthly_price: number | null;
  accepting: boolean;
};

export function InstructorForm({ initial }: { initial: Initial }) {
  const router = useRouter();
  const [f, setF] = useState({
    headline: initial.headline ?? "",
    bio: initial.bio ?? "",
    achievements: initial.achievements ?? "",
    home_break: initial.home_break ?? "",
    years: initial.years?.toString() ?? "",
    monthly_price: initial.monthly_price?.toString() ?? "",
    accepting: initial.accepting,
  });
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [pending, setPending] = useState(false);

  function set<K extends keyof typeof f>(key: K, value: (typeof f)[K]) {
    setF((prev) => ({ ...prev, [key]: value }));
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaved(false);
    setPending(true);

    const supabase = createClient();
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) {
      router.push("/login?next=/me/instructor");
      return;
    }

    const { error } = await supabase
      .from("instructor_profiles")
      .update({
        headline: f.headline.trim() || null,
        bio: f.bio.trim() || null,
        achievements: f.achievements.trim() || null,
        home_break: f.home_break.trim() || null,
        years: f.years ? Number(f.years.replace(/[^\d]/g, "")) : null,
        monthly_price: f.monthly_price ? Number(f.monthly_price.replace(/[^\d]/g, "")) : null,
        accepting: f.accepting,
      })
      .eq("member_id", user.id);
    setPending(false);

    if (error) {
      setError("保存に失敗しました。");
      return;
    }
    setSaved(true);
    router.refresh();
  }

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      {error ? <Notice kind="error">{error}</Notice> : null}
      {saved ? <Notice kind="success">講師プロフィールを保存しました🌊</Notice> : null}

      <Field
        label="キャッチコピー"
        type="text"
        placeholder="例：初めての一本を全力サポート"
        value={f.headline}
        onChange={(e) => set("headline", e.target.value)}
      />
      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-navy/80">自己紹介</span>
        <textarea
          value={f.bio}
          onChange={(e) => set("bio", e.target.value)}
          rows={4}
          placeholder="経歴、指導方針、どんな人に来てほしいか など"
          className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
        />
      </label>
      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-navy/80">主な実績</span>
        <textarea
          value={f.achievements}
          onChange={(e) => set("achievements", e.target.value)}
          rows={2}
          placeholder="大会成績・資格 など"
          className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
        />
      </label>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field
          label="ホームの海"
          type="text"
          placeholder={SITE.region.beach}
          value={f.home_break}
          onChange={(e) => set("home_break", e.target.value)}
        />
        <Field
          label="経験年数"
          type="text"
          inputMode="numeric"
          placeholder="10"
          value={f.years}
          onChange={(e) => set("years", e.target.value)}
        />
      </div>
      <Field
        label="月額の目安（円）"
        type="text"
        inputMode="numeric"
        placeholder="未入力なら「応相談」"
        value={f.monthly_price}
        onChange={(e) => set("monthly_price", e.target.value)}
      />
      <label className="flex items-center gap-2 text-sm text-navy/80">
        <input
          type="checkbox"
          checked={f.accepting}
          onChange={(e) => set("accepting", e.target.checked)}
          className="h-4 w-4 rounded border-navy/30 text-ocean"
        />
        いまは受付中（生徒を募集している）
      </label>

      <button
        type="submit"
        disabled={pending}
        className="w-full rounded-lg bg-ocean px-4 py-2.5 font-medium text-foam transition hover:bg-navy disabled:opacity-60"
      >
        {pending ? "保存中…" : "保存する"}
      </button>
    </form>
  );
}
