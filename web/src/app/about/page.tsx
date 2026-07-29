import Link from "next/link";
import type { Metadata } from "next";
import { SitePage, Section } from "@/components/site-page";
import { SITE } from "@/lib/site";

export const metadata: Metadata = {
  title: "私たちについて",
};

export default function AboutPage() {
  return (
    <SitePage title="私たちについて" lead={SITE.tagline}>
      <div className="rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-sm leading-relaxed text-sand/95">
          岩沢海岸は、福島県双葉郡広野町の、静かで懐の深いビーチブレイク。
          震災と、その後の時間を越えて、いま海はまた人を迎えています。
          {SITE.name} は、この海を「初めての人」にも開くための拠点です。
        </p>
      </div>

      <Section heading="ひとつの入口で、海のすべてを">
        <p>
          学べる（オンラインスクール）、借りられる（ギアレンタル）、移動できる（駅・ICからのサポート）、
          案内される（ローカルガイド）。バラバラだった海への入口を、ひとつにまとめました。
          さらに、トッププロ・トップアマ・認定インストラクターによるスクールで、
          「怖い」を「楽しい」に変えていきます。
        </p>
      </Section>

      <Section heading="観光ではなく、関係人口を">
        <p>
          一度きりの観光ではなく、また来たくなる関係を。
          一般ユーザーからは料金をいただかず、コミュニティ・波情報・スキルの学び合いは無料で開いています。
          地元の人、これから始める人、県外から訪れる人が、自然につながる場所を目指します。
        </p>
      </Section>

      <Section heading="復興と、再接続のストーリー">
        <p>
          海と人、地域と外の人。一度離れてしまった距離を、波がまた近づけてくれる。
          その小さな再接続を、テクノロジーとローカルの温度で支えます。
        </p>
      </Section>

      <div className="mt-10 flex flex-wrap gap-3">
        <Link
          href="/instructors"
          className="rounded-full bg-ocean px-6 py-2.5 text-sm font-medium text-foam transition hover:bg-navy"
        >
          サーフィンスクールを見る
        </Link>
        <Link
          href="/contact"
          className="rounded-full border border-navy/15 px-6 py-2.5 text-sm font-medium text-navy/70 transition hover:border-ocean hover:text-ocean"
        >
          お問い合わせ
        </Link>
      </div>
    </SitePage>
  );
}
