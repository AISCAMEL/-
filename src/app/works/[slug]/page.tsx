import type { Metadata } from "next";
import { notFound } from "next/navigation";
import Link from "next/link";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { works } from "@/content/works";

export function generateStaticParams() {
  return works.map((w) => ({ slug: w.slug }));
}

export function generateMetadata({ params }: { params: { slug: string } }): Metadata {
  const work = works.find((w) => w.slug === params.slug);
  if (!work) return {};
  return {
    title: `${work.title}｜実績`,
    description: work.summary,
    alternates: { canonical: `/works/${work.slug}` },
  };
}

export default function WorkDetailPage({ params }: { params: { slug: string } }) {
  const work = works.find((w) => w.slug === params.slug);
  if (!work) notFound();

  return (
    <>
      <PageHero eyebrow={work.categoryLabel} title={work.title} lead={work.summary} />
      <Section tone="light">
        <div className="mx-auto max-w-3xl">
          {work.isPlaceholder && (
            <p className="mb-8 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
              ※ この事例は構成確認用のサンプル（ダミー）です。実際の実績に差し替えてください。
            </p>
          )}

          <div className="grid gap-4 sm:grid-cols-3">
            <div className="rounded-xl border border-slate-200 bg-slate-50 p-5">
              <p className="text-xs font-semibold text-ink-500">領域</p>
              <p className="mt-1 font-bold text-ink-900">{work.categoryLabel}</p>
            </div>
            <div className="rounded-xl border border-slate-200 bg-slate-50 p-5 sm:col-span-2">
              <p className="text-xs font-semibold text-ink-500">主な成果</p>
              <p className="mt-1 inline-flex items-center gap-2 font-bold text-accent-600">
                <Icon name="spark" className="h-4 w-4" />
                {work.result}
              </p>
            </div>
          </div>

          {/* 本文（プレースホルダー構成。背景→課題→施策→成果のテンプレート） */}
          <div className="mt-10 space-y-8">
            {[
              { h: "背景・ご相談内容", t: "（プレースホルダー）お客様の事業背景と、ご相談に至った経緯を記載します。" },
              { h: "課題", t: "（プレースホルダー）解決すべきだった具体的な課題を記載します。" },
              { h: "アイズの支援内容", t: "（プレースホルダー）実施した支援・施策の内容を記載します。" },
              { h: "成果", t: "（プレースホルダー）定量・定性の成果を記載します。数値はダミーです。" },
            ].map((s) => (
              <section key={s.h}>
                <h2 className="border-l-4 border-brand-600 pl-3 text-xl font-bold text-ink-900">
                  {s.h}
                </h2>
                <p className="mt-3 text-sm leading-relaxed text-ink-600">{s.t}</p>
              </section>
            ))}
          </div>

          <div className="mt-12">
            <Link
              href="/works"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800"
            >
              <Icon name="arrow-right" className="h-4 w-4 rotate-180" />
              実績一覧に戻る
            </Link>
          </div>
        </div>
      </Section>
      <CtaBanner />
    </>
  );
}
