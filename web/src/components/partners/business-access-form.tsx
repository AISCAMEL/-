"use client";

import { useState } from "react";
import { Field, SubmitButton, Notice } from "@/components/ui/form";
import { PARTNER_CATEGORIES } from "@/lib/partners";

type Mode = "login" | "apply";

/**
 * 事業者向けアクセス（ログイン／新規掲載申込）。
 * デモモードでは実際の認証・送信は行わず、受付済みの体験だけを返す。
 * 実接続時は Supabase の事業者テーブル／認証に載せ替える。
 */
export function BusinessAccessForm() {
  const [mode, setMode] = useState<Mode>("apply");
  const [done, setDone] = useState<Mode | null>(null);
  const [pending, setPending] = useState(false);

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    // デモ：ネットワークに出ず、受付済みの状態にする
    setTimeout(() => {
      setPending(false);
      setDone(mode);
    }, 400);
  }

  if (done) {
    return (
      <Notice kind="success">
        {done === "apply"
          ? "掲載のお申し込みを受け付けました（デモ）。担当より2〜3営業日でご連絡します。掲載は無料です。"
          : "ログインしました（デモ）。ダッシュボードで掲載内容・特典・営業時間を編集できます。"}
      </Notice>
    );
  }

  return (
    <div>
      {/* タブ */}
      <div className="grid grid-cols-2 gap-1 rounded-xl bg-navy/5 p-1">
        <button
          type="button"
          onClick={() => setMode("apply")}
          className={`rounded-lg px-3 py-2 text-sm font-medium transition ${
            mode === "apply" ? "bg-white text-navy shadow-sm" : "text-navy/55 hover:text-navy"
          }`}
        >
          新規掲載（無料）
        </button>
        <button
          type="button"
          onClick={() => setMode("login")}
          className={`rounded-lg px-3 py-2 text-sm font-medium transition ${
            mode === "login" ? "bg-white text-navy shadow-sm" : "text-navy/55 hover:text-navy"
          }`}
        >
          事業者ログイン
        </button>
      </div>

      <form onSubmit={onSubmit} className="mt-5 space-y-4">
        <Notice kind="info">
          これはデモ画面です。実際の送信・認証は行われません。
        </Notice>

        {mode === "apply" ? (
          <>
            <Field label="店名・事業者名" required placeholder="例：岩沢の宿 うみやど" autoComplete="organization" />
            <label className="block">
              <span className="mb-1.5 block text-sm font-medium text-navy/80">カテゴリ</span>
              <select
                required
                defaultValue=""
                className="w-full rounded-lg border border-navy/15 bg-white px-3.5 py-2.5 text-navy outline-none transition focus:border-ocean focus:ring-2 focus:ring-ocean/20"
              >
                <option value="" disabled>
                  選択してください
                </option>
                {PARTNER_CATEGORIES.map((c) => (
                  <option key={c.key} value={c.key}>
                    {c.emoji} {c.label}
                  </option>
                ))}
              </select>
            </label>
            <Field label="ご担当者名" required placeholder="例：山田 太郎" autoComplete="name" />
            <Field label="メールアドレス" type="email" required placeholder="you@example.com" autoComplete="email" />
            <Field label="電話番号" type="tel" placeholder="0240-00-0000" autoComplete="tel" />
            <SubmitButton pending={pending}>無料で掲載を申し込む</SubmitButton>
          </>
        ) : (
          <>
            <Field label="メールアドレス" type="email" required placeholder="you@example.com" autoComplete="email" />
            <Field label="パスワード" type="password" required autoComplete="current-password" />
            <SubmitButton pending={pending}>ログイン</SubmitButton>
          </>
        )}
      </form>
    </div>
  );
}
