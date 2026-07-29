import Link from "next/link";
import { Brand } from "@/components/brand";
import { SITE } from "@/lib/site";

/**
 * 認証画面の共通シェル。
 * 左：朝の海のグラデーション＋ブランドメッセージ（感性寄り）
 * 右：フォーム（実務寄り）
 */
export function AuthShell({
  title,
  subtitle,
  children,
  footer,
}: {
  title: string;
  subtitle?: string;
  children: React.ReactNode;
  footer?: React.ReactNode;
}) {
  return (
    <main className="flex min-h-screen flex-col md:flex-row">
      <section className="bg-ocean-gradient relative flex flex-col justify-between p-8 text-foam md:w-1/2 md:p-12">
        <Brand light />
        <div className="hidden md:block">
          <p className="text-3xl font-semibold leading-snug">{SITE.tagline}</p>
          <p className="mt-4 max-w-sm text-sm text-sand/90">
            初めてでも、また来たくなる海へ。
            {SITE.region.beach}で、学んで・借りて・つながる。
          </p>
        </div>
        <p className="text-xs text-sand/70">{SITE.region.areaLabel}</p>
      </section>

      <section className="flex flex-1 items-center justify-center bg-foam px-6 py-12">
        <div className="w-full max-w-sm">
          <div className="mb-8 md:hidden">
            <Brand />
          </div>
          <h1 className="text-2xl font-semibold text-navy">{title}</h1>
          {subtitle ? <p className="mt-2 text-sm text-navy/60">{subtitle}</p> : null}
          <div className="mt-8">{children}</div>
          {footer ? <div className="mt-6 text-sm text-navy/70">{footer}</div> : null}
          <p className="mt-10 text-center text-xs text-navy/40">
            <Link href="/" className="hover:text-ocean">
              ← トップへ戻る
            </Link>
          </p>
        </div>
      </section>
    </main>
  );
}
