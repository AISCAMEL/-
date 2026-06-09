import type { Metadata } from "next";
import { PageHero } from "@/components/layout/PageHero";
import { Section } from "@/components/ui/Section";
import { site } from "@/content/site";

export const metadata: Metadata = {
  title: "プライバシーポリシー",
  description: `${site.name}のプライバシーポリシー（個人情報の取り扱い）について。`,
  alternates: { canonical: "/privacy" },
  robots: { index: false, follow: true },
};

const sections = [
  {
    h: "1. 個人情報の取得について",
    p: "当社は、お問い合わせやサービス提供にあたり、適法かつ公正な手段により、必要な範囲で個人情報を取得します。",
  },
  {
    h: "2. 個人情報の利用目的",
    p: "取得した個人情報は、お問い合わせへの対応、サービスのご提供・ご案内、これらに付随する業務のために利用します。",
  },
  {
    h: "3. 個人情報の第三者提供",
    p: "当社は、法令に基づく場合を除き、ご本人の同意なく個人情報を第三者に提供しません。",
  },
  {
    h: "4. 個人情報の管理",
    p: "当社は、個人情報の漏えい・滅失・毀損の防止その他の安全管理のために必要かつ適切な措置を講じます。",
  },
  {
    h: "5. 開示・訂正・削除等の請求",
    p: "ご本人からの個人情報の開示・訂正・削除等のご請求に対し、法令に従い適切に対応します。",
  },
  {
    h: "6. お問い合わせ窓口",
    p: `本ポリシーに関するお問い合わせは、${site.contact.email} までご連絡ください。`,
  },
];

export default function PrivacyPage() {
  return (
    <>
      <PageHero eyebrow="Privacy Policy" title="プライバシーポリシー" />
      <Section tone="light">
        <div className="mx-auto max-w-3xl">
          <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            ※ 本ページは雛形（placeholder）です。実際の運用方針・法令に合わせて内容を確定してください。
          </p>
          <div className="mt-8 space-y-8">
            {sections.map((s) => (
              <section key={s.h}>
                <h2 className="text-lg font-bold text-ink-900">{s.h}</h2>
                <p className="mt-2 text-sm leading-relaxed text-ink-600">{s.p}</p>
              </section>
            ))}
          </div>
          <p className="mt-10 text-sm text-ink-500">制定日：20XX年X月X日（placeholder）</p>
        </div>
      </Section>
    </>
  );
}
