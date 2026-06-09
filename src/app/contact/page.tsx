import type { Metadata } from "next";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { ContactForm } from "@/components/contact/ContactForm";
import { site } from "@/content/site";

export const metadata: Metadata = {
  title: "お問い合わせ・無料相談",
  description:
    "合同会社アイズへのお問い合わせ。自動車販売（カーメル）・買取（BUYMO）・リース（CARSHICO）・車両セキュリティ（天護）・レッカー、IT事業（APPREX）・WEB開発（WEB crews）・FC事業のご相談を承ります。初回相談・お見積りは無料です。",
  alternates: { canonical: "/contact" },
};

const examples = [
  "新車・中古車の購入や乗り換えを相談したい（カーメル）",
  "今の愛車の買取査定をお願いしたい（BUYMO）",
  "カーリースについて知りたい（CARSHICO）",
  "車のGPS・盗難対策を相談したい（天護）",
  "ノーコードでアプリを作りたい（APPREX）",
  "Webサイト・システム開発を相談したい（WEB crews）",
];

export default function ContactPage() {
  return (
    <>
      <PageHero
        eyebrow="Contact"
        title="まずは、お気軽にご相談ください"
        lead="「何から始めればいいか分からない」段階のご相談も歓迎です。無理な売り込みはいたしません。"
      />

      <Section tone="light">
        <div className="grid gap-12 lg:grid-cols-12">
          {/* 左: 相談の案内（CV改善要素） */}
          <div className="lg:col-span-5">
            <h2 className="text-xl font-bold text-ink-900">初回のご相談について</h2>
            <ul className="mt-5 space-y-3">
              {[
                ["相談・見積りは無料", "初回のご相談、お見積りは無料です。"],
                ["法人・個人どちらもOK", "個人のお客様から法人まで、お気軽にご相談ください。"],
                [`返信目安：${site.contact.replyTarget}`, "内容によりお時間をいただく場合があります。"],
                ["まとまっていなくてOK", "課題の整理からお手伝いします。"],
              ].map(([t, d]) => (
                <li key={t} className="flex items-start gap-3">
                  <Icon name="check" className="mt-0.5 h-5 w-5 flex-none text-accent-600" />
                  <span>
                    <span className="block text-sm font-semibold text-ink-900">{t}</span>
                    <span className="block text-sm text-ink-600">{d}</span>
                  </span>
                </li>
              ))}
            </ul>

            <div className="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-6">
              <h3 className="text-sm font-bold text-ink-900">こんなご相談を承っています</h3>
              <ul className="mt-3 space-y-2">
                {examples.map((e) => (
                  <li key={e} className="flex items-start gap-2 text-sm text-ink-600">
                    <span className="mt-1.5 h-1.5 w-1.5 flex-none rounded-full bg-brand-500" />
                    {e}
                  </li>
                ))}
              </ul>
            </div>

            <div className="mt-8 space-y-3 text-sm">
              <a href={`mailto:${site.contact.email}`} className="flex items-center gap-3 text-ink-700 hover:text-brand-700">
                <Icon name="mail" className="h-5 w-5 text-brand-600" />
                {site.contact.email}
              </a>
              <p className="flex items-center gap-3 text-ink-700">
                <Icon name="phone" className="h-5 w-5 text-brand-600" />
                {site.contact.tel}（{site.contact.telHours}）
              </p>
              <p className="text-xs text-ink-400">※ 連絡先は placeholder です。確定情報に差し替えてください。</p>
            </div>
          </div>

          {/* 右: フォーム */}
          <div className="lg:col-span-7">
            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-card sm:p-8">
              <ContactForm />
            </div>
          </div>
        </div>
      </Section>
    </>
  );
}
