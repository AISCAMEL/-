import Link from "next/link";
import { Brand } from "@/components/brand";
import { SiteFooter } from "@/components/site-footer";
import { MAIN_NAV } from "@/lib/nav";

/** 情報・法務ページの共通ガード（ヘッダー＋本文＋フッター） */
export function SitePage({
  title,
  lead,
  children,
}: {
  title: string;
  lead?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen flex-col bg-foam">
      <header className="border-b border-navy/10 bg-white px-6 py-4">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-3">
          <Brand />
          <nav className="flex items-center gap-3 text-sm">
            {MAIN_NAV.map((l) => (
              <Link key={l.href} href={l.href} className="hidden text-navy/70 hover:text-ocean lg:inline">
                {l.label}
              </Link>
            ))}
            <Link href="/" className="text-navy/60 hover:text-ocean lg:hidden">
              トップへ
            </Link>
          </nav>
        </div>
      </header>

      <main className="mx-auto w-full max-w-3xl flex-1 px-6 py-12">
        <h1 className="text-2xl font-semibold text-navy">{title}</h1>
        {lead ? <p className="mt-2 text-sm text-navy/60">{lead}</p> : null}
        <div className="mt-8">{children}</div>
      </main>

      <SiteFooter />
    </div>
  );
}

/** 見出し＋本文の節 */
export function Section({
  heading,
  children,
}: {
  heading: string;
  children: React.ReactNode;
}) {
  return (
    <section className="mt-8 first:mt-0">
      <h2 className="text-base font-semibold text-navy">{heading}</h2>
      <div className="mt-2 space-y-2 text-sm leading-relaxed text-navy/75">
        {children}
      </div>
    </section>
  );
}
