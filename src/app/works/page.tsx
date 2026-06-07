import type { Metadata } from "next";
import Link from "next/link";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { works } from "@/content/works";

export const metadata: Metadata = {
  title: "実績・事例",
  description:
    "合同会社アイズの支援実績・事例。自動車業界支援、創業支援、Web・開発支援における取り組みをご紹介します。",
  alternates: { canonical: "/works" },
};

export default function WorksPage() {
  return (
    <>
      <PageHero
        eyebrow="Works"
        title="実績・事例"
        lead="お客様の事業フェーズに合わせた支援の取り組みをご紹介します。"
      />
      <Section tone="light">
        {works.some((w) => w.isPlaceholder) && (
          <p className="mb-8 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            ※ 現在掲載中の事例は構成確認用のサンプル（ダミー）です。実績が確定し次第、内容を差し替えてください。
          </p>
        )}
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {works.map((w) => (
            <Link
              key={w.slug}
              href={`/works/${w.slug}`}
              className="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card transition-all hover:-translate-y-1 hover:shadow-card-hover"
            >
              <div className="relative aspect-[16/9] bg-gradient-to-br from-brand-600 to-ink-900">
                <span className="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-brand-700">
                  {w.categoryLabel}
                </span>
                {w.isPlaceholder && (
                  <span className="absolute right-4 top-4 rounded-full bg-amber-400 px-2.5 py-1 text-[10px] font-bold text-amber-950">
                    サンプル
                  </span>
                )}
              </div>
              <div className="flex flex-1 flex-col p-6">
                <h2 className="text-base font-bold text-ink-900">{w.title}</h2>
                <p className="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{w.summary}</p>
                <p className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-accent-600">
                  <Icon name="spark" className="h-4 w-4" />
                  {w.result}
                </p>
              </div>
            </Link>
          ))}
        </div>
      </Section>
      <CtaBanner />
    </>
  );
}
