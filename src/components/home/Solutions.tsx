import Link from "next/link";
import { Section, SectionHeading } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { serviceGroups, getServicesByGroup } from "@/content/services";

export function Solutions() {
  return (
    <Section id="services" tone="light">
      <SectionHeading
        eyebrow="Business"
        title="アイズの事業"
        lead="自動車事業（販売・買取・オンライン販売・セキュリティ・レッカー）を主力に、IT・WEB事業、FC事業を展開。クルマのことからデジタルまで、ワンストップでお応えします。"
        align="center"
      />

      <div className="mt-12 space-y-12">
        {serviceGroups.map((group) => {
          const items = getServicesByGroup(group.id);
          return (
            <div key={group.id}>
              <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h3 className="text-lg font-bold text-ink-900">{group.label}</h3>
                {group.isPrimary && (
                  <span className="rounded-full bg-brand-600 px-2.5 py-0.5 text-[11px] font-bold text-white">
                    主力事業
                  </span>
                )}
                <p className="text-sm text-ink-500">{group.description}</p>
              </div>

              <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {items.map((s) => (
                  <Link
                    key={s.slug}
                    href={`/services/${s.slug}`}
                    className="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-card transition-all hover:-translate-y-1 hover:border-brand-200 hover:shadow-card-hover"
                  >
                    <div className="flex items-center gap-3">
                      <span className="grid h-11 w-11 flex-none place-items-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
                        <Icon name={s.icon} className="h-6 w-6" />
                      </span>
                      <div>
                        {s.brand && (
                          <p className="text-xs font-semibold tracking-wide text-brand-600">
                            {s.brand}
                          </p>
                        )}
                        <h4 className="font-bold text-ink-900">{s.name}</h4>
                      </div>
                    </div>
                    <p className="mt-3 text-sm font-medium text-ink-700">{s.tagline}</p>
                    <p className="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{s.summary}</p>
                    <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">
                      詳しく見る
                      <Icon
                        name="arrow-right"
                        className="h-4 w-4 transition-transform group-hover:translate-x-1"
                      />
                    </span>
                  </Link>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </Section>
  );
}
