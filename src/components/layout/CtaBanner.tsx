import { Button } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { site } from "@/content/site";

/** ページ下部に共通で差し込む最終CTA */
export function CtaBanner() {
  return (
    <section className="relative overflow-hidden bg-ink-900 py-16 sm:py-20">
      {/* 装飾グラデーション */}
      <div
        className="pointer-events-none absolute inset-0 opacity-70"
        style={{
          background:
            "radial-gradient(60% 80% at 80% 0%, rgba(6,182,212,0.18), transparent 60%), radial-gradient(50% 70% at 0% 100%, rgba(37,99,235,0.22), transparent 55%)",
        }}
        aria-hidden="true"
      />
      <Container className="relative">
        <div className="mx-auto max-w-3xl text-center">
          <span className="eyebrow mx-auto justify-center text-accent-400 before:bg-accent-400">
            Contact
          </span>
          <h2 className="mt-4 text-2xl font-bold text-white sm:text-3xl md:text-4xl">
            まずは、現状をお聞かせください。
          </h2>
          <p className="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-slate-300">
            「何から始めればいいか分からない」段階のご相談も歓迎です。法人・個人事業主どちらも対応。
            初回のご相談・お見積りは無料、{site.contact.replyTarget}にご返信します。
          </p>
          <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button href="/contact" size="lg">
              無料で相談する
            </Button>
            <Button href="/services" size="lg" variant="ghost">
              サービスを見る
            </Button>
          </div>
        </div>
      </Container>
    </section>
  );
}
