import type { Metadata } from "next";
import Link from "next/link";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { news } from "@/content/news";

export const metadata: Metadata = {
  title: "お知らせ・コラム",
  description:
    "合同会社アイズからのお知らせと、クルマ（販売・買取・リース）やアプリ・Web開発に関するコラムをお届けします。",
  alternates: { canonical: "/news" },
};

export default function NewsPage() {
  const sorted = [...news].sort((a, b) => (a.date < b.date ? 1 : -1));

  return (
    <>
      <PageHero
        eyebrow="News & Column"
        title="お知らせ・コラム"
        lead="会社からのお知らせと、事業のヒントになるコラムをお届けします。"
      />
      <Section tone="light">
        {news.some((n) => n.isPlaceholder) && (
          <p className="mb-8 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            ※ 現在の記事は構成確認用のサンプル（ダミー）です。実際の記事に差し替えてください。
          </p>
        )}
        <ul className="divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200">
          {sorted.map((n) => (
            <li key={n.slug}>
              <Link
                href={`/news/${n.slug}`}
                className="group flex flex-col gap-2 px-6 py-6 transition-colors hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-6"
              >
                <div className="flex items-center gap-3 sm:w-56 sm:flex-none">
                  <time className="text-sm text-ink-500" dateTime={n.date}>
                    {n.date}
                  </time>
                  <span className="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
                    {n.category}
                  </span>
                </div>
                <div className="flex flex-1 items-center justify-between gap-4">
                  <span className="font-semibold text-ink-900 group-hover:text-brand-700">
                    {n.title}
                  </span>
                  <Icon
                    name="arrow-right"
                    className="h-4 w-4 flex-none text-brand-600 transition-transform group-hover:translate-x-1"
                  />
                </div>
              </Link>
            </li>
          ))}
        </ul>
      </Section>
      <CtaBanner />
    </>
  );
}
