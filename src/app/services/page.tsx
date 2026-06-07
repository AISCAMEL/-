import type { Metadata } from "next";
import Link from "next/link";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { services } from "@/content/services";

export const metadata: Metadata = {
  title: "サービス｜自動車業界支援・創業支援・Web/開発支援",
  description:
    "合同会社アイズのサービス一覧。自動車業界向けコンサル/DX、創業・資金調達・補助金支援、Web制作・マーケティング・システム/アプリ開発まで、戦略から実行まで伴走します。",
  alternates: { canonical: "/services" },
};

export default function ServicesPage() {
  return (
    <>
      <PageHero
        eyebrow="Services"
        title="戦略から実行まで、3つの領域でご支援します"
        lead="自動車業界支援を主軸に、創業支援、Web/開発支援を組み合わせて、事業フェーズに合わせた最適な支援を提供します。"
      />
      <Section tone="light">
        <div className="grid gap-8">
          {services.map((s, i) => (
            <div
              key={s.slug}
              className="grid gap-6 rounded-2xl border border-slate-200 bg-white p-7 shadow-card md:grid-cols-12 md:p-9"
            >
              <div className="md:col-span-4">
                <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
                  <Icon name={s.icon} className="h-7 w-7" />
                </span>
                <p className="mt-4 text-sm font-semibold text-brand-600">
                  0{i + 1} / {s.tagline}
                </p>
                <h2 className="mt-1 text-2xl font-bold text-ink-900">{s.name}</h2>
                <p className="mt-3 text-sm leading-relaxed text-ink-600">{s.summary}</p>
                <Link
                  href={`/services/${s.slug}`}
                  className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800"
                >
                  このサービスの詳細
                  <Icon name="arrow-right" className="h-4 w-4" />
                </Link>
              </div>
              <div className="md:col-span-8">
                <div className="grid gap-3 sm:grid-cols-2">
                  {s.offerings.map((o) => (
                    <div
                      key={o.title}
                      className="rounded-xl border border-slate-100 bg-slate-50 p-4"
                    >
                      <h3 className="text-sm font-bold text-ink-900">{o.title}</h3>
                      <p className="mt-1.5 text-xs leading-relaxed text-ink-600">{o.description}</p>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>
      </Section>
      <CtaBanner />
    </>
  );
}
