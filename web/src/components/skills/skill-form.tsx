"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";
import { SKILL_CATEGORIES, type SkillCategory } from "@/lib/skills";
import { Field, Notice } from "@/components/ui/form";

export function SkillForm() {
  const router = useRouter();
  const [category, setCategory] = useState<SkillCategory>("school");
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [area, setArea] = useState("");
  const [price, setPrice] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!title.trim() || !description.trim()) {
      setError("タイトルと内容を入力してください。");
      return;
    }
    setError(null);
    setPending(true);

    const supabase = createClient();
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) {
      router.push("/login?next=/skills/new");
      return;
    }

    const priceValue = price.trim() ? Number(price.replace(/[^\d]/g, "")) : null;
    const { data, error } = await supabase
      .from("skills")
      .insert({
        owner_id: user.id,
        category,
        title: title.trim(),
        description: description.trim(),
        area: area.trim() || null,
        price: priceValue && priceValue > 0 ? priceValue : null,
        status: "open",
      })
      .select("id")
      .single();
    setPending(false);

    if (error) {
      setError("出品に失敗しました。ネットワークや権限をご確認ください。");
      return;
    }
    router.push(`/skills/${data.id}`);
    router.refresh();
  }

  return (
    <form onSubmit={onSubmit} className="space-y-5">
      {error ? <Notice kind="error">{error}</Notice> : null}

      <div>
        <span className="mb-1.5 block text-sm font-medium text-navy/80">カテゴリ</span>
        <div className="flex flex-wrap gap-2">
          {SKILL_CATEGORIES.map((c) => (
            <button
              key={c.key}
              type="button"
              onClick={() => setCategory(c.key)}
              title={c.hint}
              className={`rounded-full border px-3 py-1.5 text-sm transition ${
                category === c.key
                  ? "border-ocean bg-ocean text-foam"
                  : "border-navy/15 bg-white text-navy/70 hover:border-ocean"
              }`}
            >
              {c.label}
            </button>
          ))}
        </div>
      </div>

      <Field
        label="タイトル"
        type="text"
        required
        placeholder="例：初心者向け 海デビュー体験レッスン"
        value={title}
        onChange={(e) => setTitle(e.target.value)}
      />

      <label className="block">
        <span className="mb-1.5 block text-sm font-medium text-navy/80">内容</span>
        <textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          rows={5}
          required
          placeholder="教えられること、対象、持ち物、流れなどを書きましょう。"
          className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
        />
      </label>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field
          label="エリア・場所（任意）"
          type="text"
          placeholder="岩沢海岸 など"
          value={area}
          onChange={(e) => setArea(e.target.value)}
        />
        <Field
          label="目安料金（任意・円）"
          type="text"
          inputMode="numeric"
          placeholder="未入力なら「応相談」"
          value={price}
          onChange={(e) => setPrice(e.target.value)}
        />
      </div>

      <p className="text-xs text-navy/50">
        ※ 現在はオンライン決済に未対応です。料金は目安として表示され、実際のやり取りは連絡後に相談する形です。
      </p>

      <button
        type="submit"
        disabled={pending}
        className="w-full rounded-lg bg-ocean px-4 py-2.5 font-medium text-foam transition hover:bg-navy disabled:opacity-60"
      >
        {pending ? "出品中…" : "出品する"}
      </button>
    </form>
  );
}
