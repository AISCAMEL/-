import Link from "next/link";
import { SITE } from "@/lib/site";

/** 全ページ共通フッター（規約・情報・サービスへの導線） */
export function SiteFooter() {
  return (
    <footer className="bg-navy px-6 py-12 text-sand/70">
      <div className="mx-auto grid max-w-4xl gap-8 sm:grid-cols-4">
        <div>
          <p className="text-sm font-semibold tracking-[0.18em] text-foam">{SITE.wordmark.line1}</p>
          <p className="mt-1 text-xs tracking-[0.35em] text-teal">{SITE.wordmark.line2}</p>
          <p className="mt-4 text-xs leading-relaxed text-sand/60">
            {SITE.tagline}
            <br />
            {SITE.region.areaLabel}
          </p>
        </div>

        <FooterCol title="サービス" links={[
          { href: "/instructors", label: "サーフィンスクール" },
          { href: "/courses", label: "オンライン講座" },
          { href: "/community", label: "コミュニティ" },
          { href: "/skills", label: "スキル掲示板" },
          { href: "/waves", label: "波情報" },
          { href: "/spots", label: "全国サーフポイント" },
          { href: "/partners", label: "岩沢まわり（周辺ガイド）" },
        ]} />

        <FooterCol title="情報" links={[
          { href: "/rules", label: "海に入る前に（ルール）" },
          { href: "/about", label: "私たちについて" },
          { href: "/faq", label: "よくある質問" },
          { href: "/articles", label: "プロのブログ" },
          { href: "/contact", label: "お問い合わせ" },
        ]} />

        <FooterCol title="規約・法務" links={[
          { href: "/legal/tokushoho", label: "特定商取引法表記" },
          { href: "/legal/privacy", label: "プライバシーポリシー" },
          { href: "/legal/terms", label: "利用規約" },
        ]} />
      </div>

      <div className="mx-auto mt-10 max-w-4xl border-t border-white/10 pt-6 text-center text-xs text-sand/50">
        © {SITE.name}
      </div>
    </footer>
  );
}

function FooterCol({
  title,
  links,
}: {
  title: string;
  links: { href: string; label: string }[];
}) {
  return (
    <div>
      <p className="text-xs font-semibold text-foam/90">{title}</p>
      <ul className="mt-3 space-y-2 text-xs">
        {links.map((l) => (
          <li key={l.href}>
            <Link href={l.href} className="text-sand/60 transition hover:text-teal">
              {l.label}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
