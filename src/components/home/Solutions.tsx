import Link from "next/link";
import { Section, SectionHeading } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { services } from "@/content/services";

export function Solutions() {
  const primary = services.find((s) => s.isPrimary);
  const others = services.filter((s) => !s.isPrimary);

  return (
    <Section id="services" tone="light">
      <SectionHeading
        eyebrow="Business"
        title="アイズの事業"
        lead="自動車事業（販売・買取・リース）を主力に、アプリ・GPS・FCの各事業を展開。クルマのことからデジタルまで、ワンストップでお応えします。"
        align="center"
      />

      {/* 主力事業：自動車を大きく見せる */}
      {primary && (
        <Link
          href={`/services/${primary.slug}`}
          className="group mt-12 grid gap-6 rounded-3xl border border-brand-100 bg-gradient-to-br from-brand-50 to-white p-7 shadow-card transition-all hover:-translate-y-1 hover:shadow-card-hover md:grid-cols-12 md:p-9"
        >
          <div className="md:col-span-7">
            <span className="inline-flex items-center gap-2 rounded-full bg-brand-600 px-3 py-1 text-xs font-bold text-white">
              <Icon name="spark" className="h-3.5 w-3.5" />
              主力事業
            </span>
            <div className="mt-4 flex items-center gap-4">
              <span className="grid h-14 w-14 flex-none place-items-center rounded-2xl bg-brand-600 text-white">
                <Icon name={primary.icon} className="h-7 w-7" />
              </span>
              <div>
                <h3 className="text-2xl font-bold text-ink-900">{primary.name}</h3>
                <p className="text-sm font-medium text-brand-600">{primary.tagline}</p>
              </div>
            </div>
            <p className="mt-5 text-sm leading-relaxed text-ink-700">{primary.summary}</p>
            <span className="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">
              詳しく見る
              <Icon
                name="arrow-right"
                className="h-4 w-4 transition-transform group-hover:translate-x-1"
              />
            </span>
          </div>
          <div className="md:col-span-5">
            <ul className="grid gap-3">
              {primary.highlights.map((h) => (
                <li
                  key={h}
                  className="flex items-center gap-3 rounded-xl border border-brand-100 bg-white px-4 py-3 text-sm font-medium text-ink-800"
                >
                  <Icon name="check" className="h-4 w-4 flex-none text-accent-600" />
                  {h}
                </li>
              ))}
            </ul>
          </div>
        </Link>
      )}

      {/* その他の事業 */}
      <div className="mt-6 grid gap-6 md:grid-cols-3">
        {others.map((s) => (
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
