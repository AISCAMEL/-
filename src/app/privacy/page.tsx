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
    h: "1. 事業者情報",
    p: `${site.name}（以下「当社」といいます）は、当社が運営するウェブサイトおよび提供するサービスにおける個人情報の取り扱いについて、以下のとおりプライバシーポリシー（以下「本ポリシー」といいます）を定めます。\n所在地：${site.contact.address}\n連絡先：${site.contact.email}`,
  },
  {
    h: "2. 個人情報の取得について",
    p: "当社は、お問い合わせ・お見積り・サービス提供にあたり、適法かつ公正な手段により、お名前・会社名・メールアドレス・電話番号など、必要な範囲で個人情報を取得します。",
  },
  {
    h: "3. 個人情報の利用目的",
    p: "取得した個人情報は、次の目的の範囲内で利用します。\n・お問い合わせ・ご相談への対応\n・サービスのご提供、ご案内、お見積りの作成\n・契約の履行および履行に関するご連絡\n・サービスの品質向上、新サービスのご案内\n・上記に付随する業務の遂行",
  },
  {
    h: "4. 個人情報の第三者提供",
    p: "当社は、次のいずれかに該当する場合を除き、あらかじめご本人の同意を得ることなく、個人情報を第三者に提供しません。\n・法令に基づく場合\n・人の生命、身体または財産の保護のために必要があり、本人の同意を得ることが困難な場合\n・サービス提供に必要な範囲で業務委託先に取り扱いを委託する場合（この場合、当社は委託先を適切に監督します）",
  },
  {
    h: "5. 個人情報の管理・安全管理措置",
    p: "当社は、個人情報の漏えい・滅失・毀損の防止その他の安全管理のため、必要かつ適切な措置を講じます。本サイトの送信フォーム等では、必要に応じて通信の暗号化（SSL/TLS）を行います。",
  },
  {
    h: "6. アクセス解析・Cookieについて",
    p: "当社のウェブサイトでは、利用状況の把握やサービス改善のために、Cookieを利用したアクセス解析ツールを使用する場合があります。これにより個人を特定できる情報を取得することはありません。ブラウザの設定によりCookieを無効化することも可能です。",
  },
  {
    h: "7. 開示・訂正・利用停止等の請求",
    p: `ご本人からの個人情報の開示・訂正・追加・削除・利用停止等のご請求に対し、ご本人であることを確認のうえ、法令に従い適切に対応します。ご請求は ${site.contact.email} までご連絡ください。`,
  },
  {
    h: "8. 本ポリシーの変更",
    p: "当社は、法令の改正やサービス内容の変更等に応じて、本ポリシーを予告なく変更することがあります。変更後の本ポリシーは、本ページに掲載した時点から効力を生じるものとします。",
  },
  {
    h: "9. お問い合わせ窓口",
    p: `本ポリシーおよび個人情報の取り扱いに関するお問い合わせは、${site.contact.email} までご連絡ください。`,
  },
];

export default function PrivacyPage() {
  return (
    <>
      <PageHero eyebrow="Privacy Policy" title="プライバシーポリシー" />
      <Section tone="light">
        <div className="mx-auto max-w-3xl">
          <div className="space-y-8">
            {sections.map((s) => (
              <section key={s.h}>
                <h2 className="text-lg font-bold text-ink-900">{s.h}</h2>
                <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-600">
                  {s.p}
                </p>
              </section>
            ))}
          </div>
          <div className="mt-10 border-t border-slate-200 pt-6 text-sm text-ink-500">
            <p>制定日：2026年6月15日</p>
            <p className="mt-1">{site.name}</p>
          </div>
        </div>
      </Section>
    </>
  );
}
