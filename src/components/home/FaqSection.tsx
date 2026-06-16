import { Section, SectionHeading } from "@/components/ui/Section";
import { Button } from "@/components/ui/Button";
import { Accordion } from "@/components/ui/Accordion";
import { faqs } from "@/content/faq";

export function FaqSection() {
  return (
    <Section tone="muted">
      <div className="mx-auto max-w-3xl">
        <SectionHeading
          eyebrow="FAQ"
          title="よくある質問"
          lead="お問い合わせ前に、よくいただくご質問をまとめました。"
          align="center"
        />
        <div className="mt-10">
          {/* トップでは抜粋5件を表示 */}
          <Accordion items={faqs.slice(0, 5)} />
        </div>
        <div className="mt-8 text-center">
          <Button href="/faq" variant="secondary">
            すべての質問を見る
          </Button>
        </div>
      </div>
    </Section>
  );
}
