import { Container } from "@/components/ui/Container";

/** 下層ページ共通のページヘッダー */
export function PageHero({
  eyebrow,
  title,
  lead,
}: {
  eyebrow: string;
  title: string;
  lead?: string;
}) {
  return (
    <section className="relative overflow-hidden bg-ink-900 text-white">
      <div
        className="pointer-events-none absolute inset-0 opacity-80"
        style={{
          background:
            "radial-gradient(60% 80% at 85% 0%, rgba(6,182,212,0.16), transparent 60%), radial-gradient(50% 80% at 0% 100%, rgba(37,99,235,0.22), transparent 55%)",
        }}
        aria-hidden="true"
      />
      <Container className="relative">
        <div className="max-w-3xl py-16 sm:py-20">
          <span className="eyebrow text-accent-400 before:bg-accent-400">{eyebrow}</span>
          <h1 className="mt-4 text-3xl font-bold leading-tight sm:text-4xl md:text-5xl">
            {title}
          </h1>
          {lead && (
            <p className="mt-5 text-base leading-relaxed text-slate-300 sm:text-lg">{lead}</p>
          )}
        </div>
      </Container>
    </section>
  );
}
