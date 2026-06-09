import Link from "next/link";
import { footerNav } from "@/content/navigation";
import { site } from "@/content/site";

export function Footer() {
  return (
    <footer className="border-t border-ink-700 bg-ink-900 text-slate-300">
      <div className="container py-14">
        <div className="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <Link href="/" className="flex items-center gap-2">
              <span className="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 font-bold text-white">
                A
              </span>
              <span className="flex flex-col leading-none">
                <span className="text-base font-bold text-white">{site.name}</span>
                <span className="text-[10px] tracking-wider text-brand-300">
                  {site.brandTagline}
                </span>
              </span>
            </Link>
            <p className="mt-4 text-sm leading-relaxed text-slate-400">
              自動車の販売・買取・リース・カーレスキューを主軸に、ノーコードアプリ開発・Web/システム開発、GPS事業、FC事業を展開。クルマのことからデジタルまで、ワンストップでお応えします。
            </p>
          </div>

          {footerNav.map((col) => (
            <nav key={col.title} aria-label={col.title}>
              <h2 className="text-sm font-semibold text-white">{col.title}</h2>
              <ul className="mt-4 space-y-2.5">
                {col.items.map((item) => (
                  <li key={item.href}>
                    <Link
                      href={item.href}
                      className="text-sm text-slate-400 transition-colors hover:text-white"
                    >
                      {item.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </nav>
          ))}
        </div>

        <div className="mt-12 flex flex-col items-start justify-between gap-4 border-t border-ink-700 pt-6 sm:flex-row sm:items-center">
          <p className="text-xs text-slate-500">
            © {new Date().getFullYear()} {site.name} ({site.nameEn}). All rights reserved.
          </p>
          <div className="flex gap-5 text-xs text-slate-500">
            <Link href="/privacy" className="hover:text-white">
              プライバシーポリシー
            </Link>
            <Link href="/contact" className="hover:text-white">
              お問い合わせ
            </Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
