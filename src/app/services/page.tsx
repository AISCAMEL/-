import type { Metadata } from "next";
import Link from "next/link";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { services } from "@/content/services";

export const metadata: Metadata = {
  title: "事業・サービス｜自動車・アプリ・GPS・FC",
  description:
    "合同会社アイズの事業一覧。自動車事業（販売・買取・リース）を主力に、アプリ事業（自社アプリ・Web・システム開発）、GPS事業、FC事業を展開しています。",
  alternates: { canonical: "/services" },
};

export default function ServicesPage() {
  return (
    <>
      <PageHero
        eyebrow="Business"
        title="アイズの事業・サービス"
        lead="自動車事業（販売・買取・リース）を主力に、アプリ・GPS・FCの各事業を展開。クルマのことからデジタルまで、ワンストップでお応えします。"
      />
      <Section tone="light">
        <div className="grid gap-8">
          {services.map((s, i) => (
            <div
              key={s.slug}
              className="grid gap-6 rounded-2xl border border-slate-200 bg-white p-7 shadow-card md:grid-cols-12 md:p-9"
            >
              <div className="md:col-span-4">
                <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
                  <Icon name={s.icon} className="h-7 w-7" />
                </span>
                <p className="mt-4 text-sm font-semibold text-brand-600">
                  0{i + 1} / {s.tagline}
                </p>
                <h2 className="mt-1 text-2xl font-bold text-ink-900">{s.name}</h2>
                {s.isPlaceholder && (
                  <span className="mt-2 inline-block rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold text-amber-800">
                    内容 要確認（準備中）
                  </span>
                )}
                <p className="mt-3 text-sm leading-relaxed text-ink-600">{s.summary}</p>
                <Link
                  href={`/services/${s.slug}`}
                  className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800"
                >
                  このサービスの詳細
                  <Icon name="arrow-right" className="h-4 w-4" />
                </Link>
              </div>
              <div className="md:col-span-8">
                <div className="grid gap-3 sm:grid-cols-2">
                  {s.offerings.map((o) => (
                    <div
                      key={o.title}
                      className="rounded-xl border border-slate-100 bg-slate-50 p-4"
                    >
                      <h3 className="text-sm font-bold text-ink-900">{o.title}</h3>
                      <p className="mt-1.5 text-xs leading-relaxed text-ink-600">{o.description}</p>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>
      </Section>
      <CtaBanner />
    </>
  );
}
