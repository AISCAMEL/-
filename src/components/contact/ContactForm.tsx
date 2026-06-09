"use client";

import { useState, FormEvent } from "react";
import { Icon } from "@/components/ui/Icon";

const subjects = [
  "自動車（販売・買取・リース・カーレスキュー）",
  "アプリ開発（APPREX・ノーコード）",
  "Web制作・システム開発（WEBCREWS）",
  "GPS事業について",
  "FC事業について",
  "その他・どれか分からない",
];

const inputBase =
  "w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-ink-900 placeholder:text-ink-400 focus:border-brand-500 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-0";

export function ContactForm() {
  const [submitted, setSubmitted] = useState(false);

  // ※ 送信処理は未接続（placeholder）。
  // 本番では API Route / 外部フォーム（formrun, HubSpot 等）に接続してください。
  function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setSubmitted(true);
  }

  if (submitted) {
    return (
      <div className="rounded-2xl border border-brand-200 bg-brand-50 p-8 text-center">
        <span className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-brand-600 text-white">
          <Icon name="check" className="h-6 w-6" />
        </span>
        <h2 className="mt-4 text-xl font-bold text-ink-900">送信ありがとうございます</h2>
        <p className="mt-2 text-sm text-ink-600">
          内容を確認のうえ、原則1〜2営業日以内にご返信いたします。
          <br />
          （※ デモ表示です。実際の送信処理は未接続です。）
        </p>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5" noValidate>
      <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
        ※ 本フォームは送信処理が未接続（placeholder）です。実装時に API Route または外部フォームサービスへ接続してください。
      </p>

      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="お名前" required htmlFor="name">
          <input id="name" name="name" required className={inputBase} placeholder="山田 太郎" />
        </Field>
        <Field label="会社名・屋号" htmlFor="company">
          <input id="company" name="company" className={inputBase} placeholder="株式会社○○（任意）" />
        </Field>
      </div>

      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="メールアドレス" required htmlFor="email">
          <input
            id="email"
            name="email"
            type="email"
            required
            className={inputBase}
            placeholder="example@example.com"
          />
        </Field>
        <Field label="電話番号" htmlFor="tel">
          <input id="tel" name="tel" type="tel" className={inputBase} placeholder="000-0000-0000（任意）" />
        </Field>
      </div>

      <Field label="ご相談の種類" required htmlFor="subject">
        <select id="subject" name="subject" required className={inputBase} defaultValue="">
          <option value="" disabled>
            選択してください
          </option>
          {subjects.map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
      </Field>

      <Field label="ご相談内容" required htmlFor="message">
        <textarea
          id="message"
          name="message"
          required
          rows={6}
          className={inputBase}
          placeholder="現状の課題や、相談したいことをご記入ください。まとまっていなくても問題ありません。"
        />
      </Field>

      <label className="flex items-start gap-2 text-sm text-ink-600">
        <input type="checkbox" required className="mt-1 h-4 w-4 rounded border-slate-300" />
        <span>
          <a href="/privacy" className="text-brand-700 underline">
            プライバシーポリシー
          </a>
          に同意します
        </span>
      </label>

      <button
        type="submit"
        className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-brand-600 px-7 py-4 text-base font-semibold text-white shadow-card transition-all hover:bg-brand-700 hover:shadow-card-hover focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 sm:w-auto"
      >
        この内容で送信する
        <Icon name="arrow-right" className="h-4 w-4" />
      </button>
    </form>
  );
}

function Field({
  label,
  required,
  htmlFor,
  children,
}: {
  label: string;
  required?: boolean;
  htmlFor: string;
  children: React.ReactNode;
}) {
  return (
    <div>
      <label htmlFor={htmlFor} className="mb-1.5 flex items-center gap-2 text-sm font-semibold text-ink-900">
        {label}
        {required ? (
          <span className="rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold text-white">必須</span>
        ) : (
          <span className="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-ink-500">任意</span>
        )}
      </label>
      {children}
    </div>
  );
}
