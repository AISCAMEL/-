import Link from "next/link";
import { Section, SectionHeading } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { services } from "@/content/services";

export function Solutions() {
  return (
    <Section id="services" tone="light">
      <SectionHeading
        eyebrow="Services"
        title="3つの領域で、事業の成長を支えます"
        lead="「何でもやる会社」ではなく、強みのある3領域に集中。必要に応じて組み合わせ、戦略から実行まで一貫して伴走します。"
        align="center"
      />
      <div className="mt-12 grid gap-6 lg:grid-cols-3">
        {services.map((s) => (
          <Link
            key={s.slug}
            href={`/services/${s.slug}`}
            className="group flex flex-col rounded-2xl border border-slate-200 bg-white p-7 shadow-card transition-all hover:-translate-y-1 hover:border-brand-200 hover:shadow-card-hover"
          >
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
              <Icon name={s.icon} className="h-7 w-7" />
            </span>
            <h3 className="mt-5 text-xl font-bold text-ink-900">{s.name}</h3>
            <p className="mt-1 text-sm font-medium text-brand-600">{s.tagline}</p>
            <p className="mt-3 text-sm leading-relaxed text-ink-600">{s.summary}</p>
            <ul className="mt-5 space-y-2">
              {s.highlights.map((h) => (
                <li key={h} className="flex items-start gap-2 text-sm text-ink-700">
                  <Icon name="check" className="mt-0.5 h-4 w-4 flex-none text-accent-600" />
                  {h}
                </li>
              ))}
            </ul>
            <span className="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">
              詳しく見る
              <Icon
                name="arrow-right"
                className="h-4 w-4 transition-transform group-hover:translate-x-1"
              />
            </span>
          </Link>
        ))}
      </div>
    </Section>
  );
}
