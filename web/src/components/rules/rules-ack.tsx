"use client";

import { useState } from "react";
import Link from "next/link";

const ITEMS = [
  "一本の波に乗るのは一人。前乗り（ドロップイン）はしません。",
  "パドルで沖に出るときは、乗っている人の進路を避けます。",
  "自分のレベルと、その日の海況（サイズ・風・離岸流）を確認します。",
  "混雑時は譲り合い、ローカルや先行者に敬意を持って挨拶します。",
  "ゴミは持ち帰り、駐車・着替えのマナーを守ります。",
];

export function RulesAck() {
  const [checked, setChecked] = useState<boolean[]>(ITEMS.map(() => false));
  const allChecked = checked.every(Boolean);

  function toggle(i: number) {
    setChecked((prev) => prev.map((v, idx) => (idx === i ? !v : v)));
    if (typeof window !== "undefined") {
      const next = checked.map((v, idx) => (idx === i ? !v : v));
      if (next.every(Boolean)) localStorage.setItem("rules_ack", "1");
    }
  }

  return (
    <div className="rounded-2xl border border-navy/10 bg-white p-6">
      <h2 className="text-base font-semibold text-navy">海に入る前の、5つの約束</h2>
      <p className="mt-1 text-sm text-navy/60">
        チェックして、自分の言葉として確認しましょう。
      </p>
      <ul className="mt-4 space-y-3">
        {ITEMS.map((t, i) => (
          <li key={i}>
            <label className="flex cursor-pointer items-start gap-3 text-sm text-navy/80">
              <input
                type="checkbox"
                checked={checked[i]}
                onChange={() => toggle(i)}
                className="mt-0.5 h-5 w-5 shrink-0 rounded border-navy/30 text-ocean"
              />
              <span className={checked[i] ? "text-navy" : ""}>{t}</span>
            </label>
          </li>
        ))}
      </ul>

      <div
        className={`mt-6 rounded-xl p-4 text-center transition ${
          allChecked ? "bg-teal/10" : "bg-navy/5"
        }`}
      >
        {allChecked ? (
          <>
            <p className="text-sm font-medium text-teal">
              海のルールを理解しました 🌊
            </p>
            <p className="mt-1 text-xs text-navy/60">
              次は、プロに習ってから海へ。安全に、いちばん近い上達を。
            </p>
            <Link
              href="/instructors"
              className="mt-4 inline-block rounded-full bg-ocean px-6 py-2.5 text-sm font-medium text-foam transition hover:bg-navy"
            >
              プロに習う（スクールへ）→
            </Link>
          </>
        ) : (
          <p className="text-sm text-navy/50">
            5つすべてにチェックすると、次のステップへ進めます。
          </p>
        )}
      </div>
    </div>
  );
}
