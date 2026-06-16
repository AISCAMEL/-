import type { Metadata } from "next";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { Accordion } from "@/components/ui/Accordion";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { faqs } from "@/content/faq";

export const metadata: Metadata = {
  title: "よくある質問（FAQ）",
  description:
    "合同会社アイズへのお問い合わせ前によくいただくご質問をまとめました。費用、相談範囲、対応業種、進め方などについてお答えします。",
  alternates: { canonical: "/faq" },
};

const jsonLd = {
  "@context": "https://schema.org",
  "@type": "FAQPage",
  mainEntity: faqs.map((f) => ({
    "@type": "Question",
    name: f.q,
    acceptedAnswer: { "@type": "Answer", text: f.a },
  })),
};

export default function FaqPage() {
  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <PageHero
        eyebrow="FAQ"
        title="よくある質問"
        lead="お問い合わせ前の不安や疑問を解消できるよう、よくいただくご質問をまとめました。"
      />
      <Section tone="light">
        <div className="mx-auto max-w-3xl">
          <Accordion items={faqs} />
        </div>
      </Section>
      <CtaBanner />
    </>
  );
}
