import type { Metadata } from "next";
import { notFound } from "next/navigation";
import Link from "next/link";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { news, getNews } from "@/content/news";

export function generateStaticParams() {
  return news.map((n) => ({ slug: n.slug }));
}

export function generateMetadata({ params }: { params: { slug: string } }): Metadata {
  const item = getNews(params.slug);
  if (!item) return {};
  return {
    title: item.title,
    description: item.excerpt,
    alternates: { canonical: `/news/${item.slug}` },
  };
}

export default function NewsDetailPage({ params }: { params: { slug: string } }) {
  const item = getNews(params.slug);
  if (!item) notFound();

  return (
    <>
      <PageHero eyebrow={item.category} title={item.title} />
      <Section tone="light">
        <article className="mx-auto max-w-3xl">
          <div className="flex items-center gap-3 border-b border-slate-200 pb-6">
            <time className="text-sm text-ink-500" dateTime={item.date}>
              {item.date}
            </time>
            <span className="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
              {item.category}
            </span>
          </div>

          {item.isPlaceholder && (
            <p className="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
              ※ この記事は構成確認用のサンプル（ダミー）です。
            </p>
          )}

          <div className="mt-8 space-y-5">
            {item.body.map((p, i) => (
              <p key={i} className="text-base leading-relaxed text-ink-700">
                {p}
              </p>
            ))}
          </div>

          <div className="mt-12">
            <Link
              href="/news"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800"
            >
              <Icon name="arrow-right" className="h-4 w-4 rotate-180" />
              お知らせ一覧に戻る
            </Link>
          </div>
        </article>
      </Section>
      <CtaBanner />
    </>
  );
}
