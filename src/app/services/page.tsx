import type { Metadata } from "next";
import Link from "next/link";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { serviceGroups, getServicesByGroup } from "@/content/services";

export const metadata: Metadata = {
  title: "事業紹介｜自動車・IT/WEB・FC",
  description:
    "合同会社アイズの事業一覧。自動車販売「カーメル」・買取「BUYMO」・オンライン車販売「CARSHICO」・車両セキュリティ「天護」・レッカー事業を主力に、IT事業「APPREX」、サブスクWeb制作「WEB crews」、FC事業を展開しています。",
  alternates: { canonical: "/services" },
};

export default function ServicesPage() {
  return (
    <>
      <PageHero
        eyebrow="Business"
        title="事業紹介"
        lead="自動車事業（販売・買取・オンライン販売・セキュリティ・レッカー）を主力に、IT・WEB事業、FC事業を展開。クルマのことからデジタルまで、ワンストップでお応えします。"
      />
      <Section tone="light">
        <div className="space-y-14">
          {serviceGroups.map((group) => (
            <div key={group.id}>
              <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 className="text-xl font-bold text-ink-900">{group.label}</h2>
                {group.isPrimary && (
                  <span className="rounded-full bg-brand-600 px-2.5 py-0.5 text-[11px] font-bold text-white">
                    主力事業
                  </span>
                )}
              </div>
              <p className="mt-1 text-sm text-ink-500">{group.description}</p>

              <div className="mt-6 grid gap-5 md:grid-cols-2">
                {getServicesByGroup(group.id).map((s) => (
                  <div
                    key={s.slug}
                    className="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-card sm:grid-cols-12 sm:p-7"
                  >
                    <div className="sm:col-span-5">
                      <span className="grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
                        <Icon name={s.icon} className="h-6 w-6" />
                      </span>
                      {s.brand && (
                        <p className="mt-3 text-xs font-semibold tracking-wide text-brand-600">
                          {s.brand}
                        </p>
                      )}
                      <div className="mt-0.5 flex flex-wrap items-center gap-2">
                        <h3 className="text-lg font-bold text-ink-900">{s.name}</h3>
                        {s.comingSoon && (
                          <span className="rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold text-accent-700 ring-1 ring-inset ring-accent-200">
                            準備中
                          </span>
                        )}
                      </div>
                      <p className="mt-1 text-sm font-medium text-ink-600">{s.tagline}</p>
                      <Link
                        href={`/services/${s.slug}`}
                        className="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800"
                      >
                        詳しく見る
                        <Icon name="arrow-right" className="h-4 w-4" />
                      </Link>
                    </div>
                    <div className="sm:col-span-7">
                      <ul className="space-y-2">
                        {s.highlights.map((h) => (
                          <li key={h} className="flex items-start gap-2 text-sm text-ink-700">
                            <Icon name="check" className="mt-0.5 h-4 w-4 flex-none text-accent-600" />
                            {h}
                          </li>
                        ))}
                      </ul>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </Section>
      <CtaBanner />
    </>
  );
}
