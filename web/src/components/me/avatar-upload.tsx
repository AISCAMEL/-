"use client";

/* eslint-disable @next/next/no-img-element */
import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";
import { Notice } from "@/components/ui/form";

/** プロフィール写真のアップロード（Supabase Storage: avatars バケット） */
export function AvatarUpload({
  initialUrl,
  name,
}: {
  initialUrl: string | null;
  name: string;
}) {
  const router = useRouter();
  const [url, setUrl] = useState<string | null>(initialUrl);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function onChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
      setError("画像は5MB以下にしてください。");
      return;
    }
    setError(null);
    setPending(true);

    const supabase = createClient();
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) {
      router.push("/login?next=/me/edit");
      return;
    }

    const ext = file.name.split(".").pop() || "jpg";
    const path = `${user.id}/avatar.${ext}`;
    const { error: upErr } = await supabase.storage
      .from("avatars")
      .upload(path, file, { upsert: true, cacheControl: "3600" });

    if (upErr) {
      setPending(false);
      setError("アップロードに失敗しました（Storageバケット 'avatars' の作成をご確認ください）。");
      return;
    }

    const { data: pub } = supabase.storage.from("avatars").getPublicUrl(path);
    const publicUrl = `${pub.publicUrl}?t=${Date.now()}`; // キャッシュ回避

    const { error: updErr } = await supabase
      .from("members")
      .update({ avatar_url: publicUrl })
      .eq("id", user.id);

    setPending(false);
    if (updErr) {
      setError("プロフィールの更新に失敗しました。");
      return;
    }
    setUrl(publicUrl);
    router.refresh();
  }

  return (
    <div className="flex items-center gap-4">
      {url ? (
        <img src={url} alt={name} className="h-16 w-16 rounded-full object-cover" />
      ) : (
        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-ocean-gradient text-xl font-semibold text-foam">
          {name.slice(0, 1)}
        </div>
      )}
      <div className="space-y-2">
        <label className="inline-block cursor-pointer rounded-lg border border-navy/15 px-4 py-2 text-sm text-navy/70 transition hover:border-ocean hover:text-ocean">
          {pending ? "アップロード中…" : "写真を選ぶ"}
          <input
            type="file"
            accept="image/*"
            onChange={onChange}
            disabled={pending}
            className="hidden"
          />
        </label>
        <p className="text-xs text-navy/40">JPG / PNG・5MBまで</p>
      </div>
      {error ? (
        <div className="w-full">
          <Notice kind="error">{error}</Notice>
        </div>
      ) : null}
    </div>
  );
}
