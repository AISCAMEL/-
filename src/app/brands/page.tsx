import type { Metadata } from "next";
import Link from "next/link";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { CtaBanner } from "@/components/layout/CtaBanner";
import { services } from "@/content/services";

export const metadata: Metadata = {
  title: "ブランド一覧",
  description:
    "合同会社アイズが展開するブランド一覧。自動車販売「カーメル」、買取「BUYMO」、オンライン車販売「CARSHICO」、車両セキュリティ「天護 TENGO」、ノーコードアプリ開発「APPREX」、サブスクWeb制作「WEB crews」、AI電話応対「AIオペレーター24」。",
  alternates: { canonical: "/brands" },
};

export default function BrandsPage() {
  const branded = services.filter((s) => s.brand);

  return (
    <>
      <PageHero
        eyebrow="Brands"
        title="ブランド一覧"
        lead="合同会社アイズは、事業ごとにブランドを展開しています。それぞれの専門性で、お客様のニーズにお応えします。"
      />
      <Section tone="light">
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {branded.map((s) => (
            <Link
              key={s.slug}
              href={`/services/${s.slug}`}
              className="group flex flex-col rounded-2xl border border-slate-200 bg-white p-7 shadow-card transition-all hover:-translate-y-1 hover:border-brand-200 hover:shadow-card-hover"
            >
              <span className="grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
                <Icon name={s.icon} className="h-6 w-6" />
              </span>
              <p className="mt-5 text-lg font-bold tracking-wide text-ink-900">{s.brand}</p>
              <p className="mt-1 text-sm font-semibold text-brand-600">{s.name}</p>
              <p className="mt-3 flex-1 text-sm leading-relaxed text-ink-600">{s.tagline}</p>
              <span className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">
                詳しく見る
                <Icon
                  name="arrow-right"
                  className="h-4 w-4 transition-transform group-hover:translate-x-1"
                />
              </span>
            </Link>
          ))}
        </div>

        <p className="mt-8 text-sm text-ink-500">
          ※ このほか、レッカー事業（カーレスキュー）、FC事業（カーメル／BUYMO の加盟募集）も展開しています。
          詳しくは{" "}
          <Link href="/services" className="font-semibold text-brand-700 hover:underline">
            事業紹介
          </Link>
          をご覧ください。
        </p>
      </Section>
      <CtaBanner />
    </>
  );
}
