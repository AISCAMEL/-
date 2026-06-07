import { Hero } from "@/components/home/Hero";
import { Problems } from "@/components/home/Problems";
import { Solutions } from "@/components/home/Solutions";
import { Strengths } from "@/components/home/Strengths";
import { Workflow } from "@/components/home/Workflow";
import { CaseStudies } from "@/components/home/CaseStudies";
import { Message } from "@/components/home/Message";
import { FaqSection } from "@/components/home/FaqSection";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { faqs } from "@/content/faq";
import { site } from "@/content/site";

// 構造化データ（Organization + FAQ）でSEO/リッチリザルトに対応
const jsonLd = {
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      name: site.name,
      alternateName: site.nameEn,
      url: site.url,
      description: site.description,
      slogan: site.brandTagline,
    },
    {
      "@type": "FAQPage",
      mainEntity: faqs.map((f) => ({
        "@type": "Question",
        name: f.q,
        acceptedAnswer: { "@type": "Answer", text: f.a },
      })),
    },
  ],
};

export default function HomePage() {
  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <Hero />
      <Problems />
      <Solutions />
      <Strengths />
      <Workflow />
      <CaseStudies />
      <Message />
      <FaqSection />
      <CtaBanner />
    </>
  );
}
