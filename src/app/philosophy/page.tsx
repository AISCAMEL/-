import type { Metadata } from "next";
import { PageHero } from "@/components/layout/PageHero";
import { Section, SectionHeading } from "@/components/ui/Section";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { philosophy } from "@/content/company";

export const metadata: Metadata = {
  title: "理念｜Always Innovation Solutions",
  description:
    "合同会社アイズの理念。革新・品質・信頼性を軸に、自動車業界の変革を成長の機会へ。常に新しい解決策で、お客様の事業に最後まで寄り添います。",
  alternates: { canonical: "/philosophy" },
};

export default function PhilosophyPage() {
  return (
    <>
      <PageHero
        eyebrow="Philosophy"
        title={philosophy.tagline}
        lead={philosophy.brand}
      />

      <Section tone="light">
        <div className="mx-auto max-w-3xl">
          <SectionHeading eyebrow="Vision" title={philosophy.vision.title} />
          <div className="mt-6 space-y-5">
            {philosophy.vision.body.map((p, i) => (
              <p key={i} className="text-base leading-relaxed text-ink-700">
                {p}
              </p>
            ))}
          </div>
        </div>
      </Section>

      <Section tone="dark">
        <SectionHeading
          eyebrow="Values"
          title="革新・品質・信頼性"
          lead="私たちの価値観は、日々の仕事のすべてに通底しています。"
          align="center"
          invert
        />
        <div className="mt-12 grid gap-5 md:grid-cols-3">
          {philosophy.values.map((v, i) => (
            <div
              key={v.title}
              className="rounded-2xl border border-white/10 bg-white/[0.04] p-7"
            >
              <span className="text-3xl font-bold text-accent-400">
                {String(i + 1).padStart(2, "0")}
              </span>
              <h3 className="mt-2 text-lg font-bold text-white">{v.title}</h3>
              <p className="mt-3 text-sm leading-relaxed text-slate-300">{v.body}</p>
            </div>
          ))}
        </div>
      </Section>

      <CtaBanner />
    </>
  );
}
