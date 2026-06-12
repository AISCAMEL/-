"use client";

import { useState } from "react";
import { createClient, isSupabaseConfigured } from "@/lib/supabase/client";
import { Field, SubmitButton, Notice } from "@/components/ui/form";

export function SignupForm() {
  const [displayName, setDisplayName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);
  const [pending, setPending] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);

    if (!isSupabaseConfigured()) {
      setError("Supabase が未設定です（.env.local を設定してください）。");
      return;
    }
    if (password.length < 8) {
      setError("パスワードは8文字以上にしてください。");
      return;
    }

    setPending(true);
    const supabase = createClient();
    const siteUrl =
      process.env.NEXT_PUBLIC_SITE_URL ?? window.location.origin;
    const { error } = await supabase.auth.signUp({
      email,
      password,
      options: {
        data: { display_name: displayName },
        emailRedirectTo: `${siteUrl}/auth/callback?next=/me`,
      },
    });
    setPending(false);

    if (error) {
      setError(error.message);
      return;
    }
    setDone(true);
  }

  if (done) {
    return (
      <Notice kind="success">
        確認メールを送りました。メール内のリンクを開くと登録が完了します🌊
      </Notice>
    );
  }

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      {error ? <Notice kind="error">{error}</Notice> : null}
      <Field
        label="表示名（ニックネーム）"
        type="text"
        required
        autoComplete="nickname"
        value={displayName}
        onChange={(e) => setDisplayName(e.target.value)}
      />
      <Field
        label="メールアドレス"
        type="email"
        required
        autoComplete="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
      />
      <Field
        label="パスワード（8文字以上）"
        type="password"
        required
        minLength={8}
        autoComplete="new-password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
      />
      <SubmitButton pending={pending}>登録して、岩沢の今日を覗く</SubmitButton>
      <p className="text-xs text-navy/50">
        登録すると Beginner として、投稿・いいね・スキルの申し込みができます。
      </p>
    </form>
  );
}
