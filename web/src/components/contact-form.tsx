"use client";

import { useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { Field, Notice } from "@/components/ui/form";
import { DEMO } from "@/lib/demo";

const CATEGORIES = ["スクールについて", "スキル掲示板について", "不具合の報告", "その他"];

export function ContactForm() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [category, setCategory] = useState(CATEGORIES[0]);
  const [body, setBody] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);
  const [pending, setPending] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!name.trim() || !email.trim() || !body.trim()) {
      setError("お名前・メール・お問い合わせ内容をご記入ください。");
      return;
    }
    setError(null);
    setPending(true);

    if (DEMO) {
      // デモ：送信は行わず完了表示
      setTimeout(() => {
        setPending(false);
        setDone(true);
      }, 400);
      return;
    }

    const supabase = createClient();
    const { error } = await supabase.from("inquiries").insert({
      name: name.trim(),
      email: email.trim(),
      category,
      body: body.trim(),
    });
    setPending(false);

    if (error) {
      setError("送信に失敗しました。時間をおいて再度お試しください。");
      return;
    }
    setDone(true);
  }

  if (done) {
    return (
      <Notice kind="success">
        お問い合わせを受け付けました。担当者よりご連絡します。ありがとうございます🌊
      </Notice>
    );
  }

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      {error ? <Notice kind="error">{error}</Notice> : null}
      <Field label="お名前" type="text" value={name} onChange={(e) => setName(e.target.value)} />
      <Field
        label="メールアドレス"
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
      />
      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-navy/80">種別</span>
        <select
          value={category}
          onChange={(e) => setCategory(e.target.value)}
          className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none focus:border-ocean"
        >
          {CATEGORIES.map((c) => (
            <option key={c} value={c}>
              {c}
            </option>
          ))}
        </select>
      </label>
      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-navy/80">お問い合わせ内容</span>
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          rows={5}
          className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
        />
      </label>
      <button
        type="submit"
        disabled={pending}
        className="w-full rounded-lg bg-ocean px-4 py-2.5 font-medium text-foam transition hover:bg-navy disabled:opacity-60"
      >
        {pending ? "送信中…" : "送信する"}
      </button>
    </form>
  );
}
