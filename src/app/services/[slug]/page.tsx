import type { Metadata } from "next";
import { notFound } from "next/navigation";
import Link from "next/link";
import { PageHero } from "@/components/layout/PageHero";
import { Section, SectionHeading } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { Button } from "@/components/ui/Button";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { services, getService } from "@/content/services";
import { workflow } from "@/content/home";

// 静的生成（SSG）対象のスラッグを列挙
export function generateStaticParams() {
  return services.map((s) => ({ slug: s.slug }));
}

export function generateMetadata({ params }: { params: { slug: string } }): Metadata {
  const service = getService(params.slug);
  if (!service) return {};
  return {
    title: service.seo.title,
    description: service.seo.description,
    alternates: { canonical: `/services/${service.slug}` },
  };
}

export default function ServiceDetailPage({ params }: { params: { slug: string } }) {
  const service = getService(params.slug);
  if (!service) notFound();

  const others = services.filter((s) => s.slug !== service.slug);

  return (
    <>
      <PageHero eyebrow={service.tagline} title={service.name} lead={service.summary} />

      {/* こんな方へ */}
      <Section tone="light">
        <SectionHeading
          eyebrow="For You"
          title="こんな企業・事業者の方へ"
          lead="ひとつでも当てはまれば、お力になれる可能性があります。"
        />
        <div className="mt-10 grid gap-4 sm:grid-cols-3">
          {service.audience.map((a) => (
            <div
              key={a}
              className="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-5"
            >
              <Icon name="check" className="mt-0.5 h-5 w-5 flex-none text-accent-600" />
              <p className="text-sm font-medium leading-relaxed text-ink-800">{a}</p>
            </div>
          ))}
        </div>
      </Section>

      {/* 提供メニュー */}
      <Section tone="muted">
        <SectionHeading
          eyebrow="Menu"
          title="主な支援メニュー"
          lead="課題に合わせて、必要なメニューを組み合わせてご提供します。"
        />
        <div className="mt-10 grid gap-5 md:grid-cols-2">
          {service.offerings.map((o, i) => (
            <div
              key={o.title}
              className="rounded-2xl border border-slate-200 bg-white p-7 shadow-card"
            >
              <span className="text-sm font-bold text-brand-300">
                {String(i + 1).padStart(2, "0")}
              </span>
              <h3 className="mt-1 text-lg font-bold text-ink-900">{o.title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-ink-600">{o.description}</p>
            </div>
          ))}
        </div>
      </Section>

      {/* 進め方 */}
      <Section tone="light">
        <SectionHeading
          eyebrow="Flow"
          title="ご相談から支援開始までの流れ"
          align="center"
        />
        <ol className="mt-10 grid gap-4 md:grid-cols-5">
          {workflow.steps.map((step, i) => (
            <li key={step.no} className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
              <span className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
                {i + 1}
              </span>
              <h3 className="mt-3 text-sm font-bold text-ink-900">{step.title}</h3>
              <p className="mt-1.5 text-xs leading-relaxed text-ink-600">{step.body}</p>
            </li>
          ))}
        </ol>
        <div className="mt-10 text-center">
          <Button href="/contact" size="lg">
            この内容で相談する
            <Icon name="arrow-right" className="h-4 w-4" />
          </Button>
        </div>
      </Section>

      {/* 他のサービス */}
      <Section tone="muted">
        <SectionHeading eyebrow="Other Services" title="その他のサービス" />
        <div className="mt-8 grid gap-5 sm:grid-cols-2">
          {others.map((o) => (
            <Link
              key={o.slug}
              href={`/services/${o.slug}`}
              className="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-card transition-all hover:-translate-y-0.5 hover:shadow-card-hover"
            >
              <span className="grid h-12 w-12 flex-none place-items-center rounded-xl bg-brand-50 text-brand-600">
                <Icon name={o.icon} className="h-6 w-6" />
              </span>
              <div className="flex-1">
                <h3 className="font-bold text-ink-900">{o.name}</h3>
                <p className="text-sm text-ink-500">{o.tagline}</p>
              </div>
              <Icon
                name="arrow-right"
                className="h-5 w-5 text-brand-600 transition-transform group-hover:translate-x-1"
              />
            </Link>
          ))}
        </div>
      </Section>

      <CtaBanner />
    </>
  );
}
