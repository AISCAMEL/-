import type { Metadata } from "next";
import Link from "next/link";
import { BusinessAccessForm } from "@/components/partners/business-access-form";
import { SITE } from "@/lib/site";

export const metadata: Metadata = {
  title: "事業者の方へ｜掲載無料",
  description:
    "レンタカー・宿泊・飲食店の方へ。岩沢まわりガイドへの掲載は無料。事業者ログインから掲載・管理できます。",
};

const BENEFITS = [
  {
    emoji: "💰",
    title: "掲載は無料",
    desc: "初期費用・月額なしで掲載できます。まずは1店舗からお気軽に。",
  },
  {
    emoji: "🌊",
    title: "海に来る人に届く",
    desc: `${SITE.region.beach}に通うサーファー・来訪者に、あなたのお店を直接ご案内します。`,
  },
  {
    emoji: "🎁",
    title: "会員特典で集客",
    desc: "会員向けの特典（割引・サービス）を設定でき、来店のきっかけをつくれます。",
  },
  {
    emoji: "✏️",
    title: "自分で編集・管理",
    desc: "営業時間・写真・特典は、事業者ログインからいつでも更新できます。",
  },
];

export default function PartnersBusinessPage() {
  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      {/* Hero */}
      <div className="rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">FOR BUSINESS</p>
        <h1 className="mt-2 text-2xl font-semibold">
          海の“ついで”に、あなたのお店を。
        </h1>
        <p className="mt-1 text-sm text-sand/90">
          レンタカー・宿泊・飲食店の方へ。「岩沢まわり」への掲載は無料です。
        </p>
      </div>

      <div className="mt-6 grid gap-6 md:grid-cols-2">
        {/* 左：メリット */}
        <section>
          <h2 className="text-base font-semibold text-navy">掲載のメリット</h2>
          <ul className="mt-3 space-y-3">
            {BENEFITS.map((b) => (
              <li
                key={b.title}
                className="flex gap-3 rounded-2xl border border-navy/10 bg-white p-4 shadow-sm"
              >
                <span className="text-2xl" aria-hidden>
                  {b.emoji}
                </span>
                <div>
                  <p className="text-sm font-semibold text-navy">{b.title}</p>
                  <p className="mt-0.5 text-xs leading-relaxed text-navy/60">{b.desc}</p>
                </div>
              </li>
            ))}
          </ul>

          <div className="mt-4 rounded-2xl border border-teal/30 bg-teal/10 p-4">
            <p className="text-sm font-semibold text-navy">掲載までの流れ</p>
            <ol className="mt-2 space-y-1 text-xs text-navy/70">
              <li>1. 右のフォームから無料で申し込み</li>
              <li>2. 運営が内容を確認（2〜3営業日）</li>
              <li>3. 事業者ログインで写真・特典を編集して公開</li>
            </ol>
          </div>

          <p className="mt-4 text-xs text-navy/50">
            掲載中の一覧は{" "}
            <Link href="/partners" className="text-ocean hover:underline">
              岩沢まわり
            </Link>{" "}
            からご覧いただけます。
          </p>
        </section>

        {/* 右：ログイン／申込 */}
        <section>
          <div className="rounded-2xl border border-navy/10 bg-white p-5 shadow-sm">
            <h2 className="text-base font-semibold text-navy">事業者向けログイン</h2>
            <p className="mt-1 text-xs text-navy/55">
              はじめての方は「新規掲載（無料）」から。
            </p>
            <div className="mt-4">
              <BusinessAccessForm />
            </div>
          </div>

          <p className="mt-4 text-center text-xs text-navy/40">
            ご不明点は{" "}
            <Link href="/contact" className="text-ocean hover:underline">
              お問い合わせ
            </Link>{" "}
            まで。
          </p>
        </section>
      </div>
    </main>
  );
}
