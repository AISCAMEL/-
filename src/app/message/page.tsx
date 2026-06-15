import type { Metadata } from "next";
import { PageHero } from "@/components/layout/PageHero";
import { Section, SectionHeading } from "@/components/ui/Section";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { Reveal } from "@/components/ui/Reveal";
import { representative, philosophy } from "@/content/company";

export const metadata: Metadata = {
  title: "代表メッセージ",
  description:
    "合同会社アイズ 代表メッセージ。変化を成長の機会に変え、構想から実行・成果まで最後まで伴走する——私たちの姿勢をお伝えします。",
  alternates: { canonical: "/message" },
};

export default function MessagePage() {
  return (
    <>
      <PageHero
        eyebrow="Message"
        title="代表メッセージ"
        lead={philosophy.tagline}
      />

      <Section tone="light">
        <div className="mx-auto max-w-3xl">
          <Reveal>
            <SectionHeading eyebrow={representative.lead} title={representative.heading} />
          </Reveal>
          <Reveal delay={80}>
            <div className="mt-8 space-y-5">
              {representative.body.map((p, i) => (
                <p key={i} className="text-base leading-relaxed text-ink-700">
                  {p}
                </p>
              ))}
            </div>
          </Reveal>

          <Reveal delay={160}>
            <div className="mt-10 flex items-center justify-end gap-4 border-t border-slate-200 pt-6">
              <div className="text-right">
                <p className="text-sm text-ink-500">{representative.title}</p>
                <p className="mt-1 text-xl font-bold tracking-wide text-ink-900">
                  {representative.name}
                </p>
              </div>
            </div>
          </Reveal>
        </div>
      </Section>

      <CtaBanner />
    </>
  );
}
