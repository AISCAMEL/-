"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";
import { Field, Notice } from "@/components/ui/form";

type Props = {
  initial: {
    display_name: string | null;
    handle: string | null;
    bio: string | null;
    home_area: string | null;
  };
};

export function ProfileForm({ initial }: Props) {
  const router = useRouter();
  const [displayName, setDisplayName] = useState(initial.display_name ?? "");
  const [handle, setHandle] = useState(initial.handle ?? "");
  const [bio, setBio] = useState(initial.bio ?? "");
  const [homeArea, setHomeArea] = useState(initial.home_area ?? "");
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [pending, setPending] = useState(false);

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
      router.push("/login?next=/me/edit");
      return;
    }

    const { error } = await supabase
      .from("members")
      .update({
        display_name: displayName.trim() || null,
        handle: handle.trim() || null,
        bio: bio.trim() || null,
        home_area: homeArea.trim() || null,
      })
      .eq("id", user.id);
    setPending(false);

    if (error) {
      setError(
        error.code === "23505"
          ? "そのハンドルは既に使われています。"
          : "保存に失敗しました。",
      );
      return;
    }
    setSaved(true);
    router.refresh();
  }

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      {error ? <Notice kind="error">{error}</Notice> : null}
      {saved ? <Notice kind="success">プロフィールを保存しました🌊</Notice> : null}

      <Field
        label="表示名"
        type="text"
        value={displayName}
        onChange={(e) => setDisplayName(e.target.value)}
      />
      <Field
        label="ハンドル（半角英数・任意）"
        type="text"
        placeholder="iwasawa_taro"
        value={handle}
        onChange={(e) => setHandle(e.target.value)}
      />
      <Field
        label="地域（例：県内／県外・郡山 など 任意）"
        type="text"
        value={homeArea}
        onChange={(e) => setHomeArea(e.target.value)}
      />
      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-navy/80">
          自己紹介
        </span>
        <textarea
          value={bio}
          onChange={(e) => setBio(e.target.value)}
          rows={4}
          placeholder="サーフィン歴、好きな海、これからやりたいこと など"
          className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
        />
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
