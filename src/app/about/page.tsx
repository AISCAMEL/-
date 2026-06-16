import type { Metadata } from "next";
import { PageHero } from "@/components/layout/PageHero";
import { Section, SectionHeading } from "@/components/ui/Section";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { companyProfile, philosophy } from "@/content/company";

export const metadata: Metadata = {
  title: "アイズについて｜会社概要",
  description:
    "合同会社アイズ（AIS LLC）の会社概要。AIS = Always Innovation Solutions。自動車業界の知見とITを掛け合わせ、戦略から実行まで顧客の成長に伴走します。",
  alternates: { canonical: "/about" },
};

export default function AboutPage() {
  return (
    <>
      <PageHero
        eyebrow="About"
        title="アイズについて"
        lead="AIS = Always Innovation Solutions。常に新しいことを取り入れ、課題を解決しながら、最後までお客様に寄り添う会社です。"
      />

      <Section tone="light">
        <SectionHeading
          eyebrow="Our Values"
          title="私たちが大切にする3つの価値観"
          lead="すべての仕事の判断基準となる、変わらない軸です。"
          align="center"
        />
        <div className="mt-12 grid gap-5 md:grid-cols-3">
          {philosophy.values.map((v) => (
            <div
              key={v.title}
              className="rounded-2xl border border-slate-200 bg-slate-50 p-7"
            >
              <h3 className="text-lg font-bold text-brand-700">{v.title}</h3>
              <p className="mt-3 text-sm leading-relaxed text-ink-600">{v.body}</p>
            </div>
          ))}
        </div>
      </Section>

      <Section tone="muted">
        <SectionHeading eyebrow="Company" title="会社概要" />
        <dl className="mt-10 overflow-hidden rounded-2xl border border-slate-200 bg-white">
          {companyProfile.map((row, i) => (
            <div
              key={row.label}
              className={`grid grid-cols-1 gap-1 px-6 py-5 sm:grid-cols-4 sm:gap-4 ${
                i !== 0 ? "border-t border-slate-100" : ""
              }`}
            >
              <dt className="text-sm font-bold text-ink-900">{row.label}</dt>
              <dd className="text-sm leading-relaxed text-ink-600 sm:col-span-3">{row.value}</dd>
            </div>
          ))}
        </dl>
      </Section>

      <CtaBanner />
    </>
  );
}
