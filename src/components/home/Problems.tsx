import { Section, SectionHeading } from "@/components/ui/Section";
import { problems } from "@/content/home";

export function Problems() {
  return (
    <Section tone="muted">
      <SectionHeading
        eyebrow="Problem"
        title={problems.heading}
        lead={problems.lead}
        align="center"
      />
      <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {problems.items.map((item) => (
          <div
            key={item.title}
            className="rounded-2xl border border-slate-200 bg-white p-6 shadow-card"
          >
            <p className="text-3xl font-bold text-brand-100">？</p>
            <h3 className="mt-2 text-base font-bold text-ink-900">{item.title}</h3>
            <p className="mt-2 text-sm leading-relaxed text-ink-600">{item.body}</p>
          </div>
        ))}
      </div>
      <p className="mt-10 text-center text-base font-semibold text-ink-700">
        ──そのお悩み、アイズが
        <span className="text-brand-700">戦略から実行まで</span>
        まとめてご支援します。
      </p>
    </Section>
  );
}
