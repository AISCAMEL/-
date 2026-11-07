"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { TAB_NAV, MENU_NAV } from "@/lib/nav";

/** スマホ下部のタブバー＋全ページメニュー（PC幅では非表示） */
export function MobileTabBar() {
  const pathname = usePathname() ?? "/";
  const [menu, setMenu] = useState(false);

  if (pathname.startsWith("/admin")) return null;

  const active = (href: string) => (href === "/" ? pathname === "/" : pathname.startsWith(href));

  return (
    <>
      {menu ? (
        <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-label="メニュー">
          <button
            type="button"
            className="absolute inset-0 bg-navy/40"
            aria-label="メニューを閉じる"
            onClick={() => setMenu(false)}
          />
          <div
            className="chat-pop absolute inset-x-0 bottom-0 rounded-t-2xl bg-white px-4 pt-4 shadow-2xl"
            style={{ paddingBottom: "calc(5rem + env(safe-area-inset-bottom))" }}
          >
            <p className="text-xs font-semibold tracking-[0.3em] text-teal">MENU</p>
            <div className="mt-3 grid grid-cols-3 gap-2">
              {MENU_NAV.map((l) => (
                <Link
                  key={l.href}
                  href={l.href}
                  onClick={() => setMenu(false)}
                  className={`flex flex-col items-center gap-1 rounded-xl px-1 py-3 text-center text-xs ${
                    active(l.href) ? "bg-ocean/10 font-semibold text-ocean" : "bg-foam text-navy/80"
                  }`}
                >
                  <span className="text-xl" aria-hidden="true">
                    {l.icon}
                  </span>
                  {l.label}
                </Link>
              ))}
            </div>
          </div>
        </div>
      ) : null}

      <nav
        className="fixed inset-x-0 bottom-0 z-40 border-t border-navy/10 bg-white/95 backdrop-blur lg:hidden"
        style={{ paddingBottom: "env(safe-area-inset-bottom)" }}
        aria-label="メインメニュー"
      >
        <ul className="grid grid-cols-5">
          {TAB_NAV.map((l) => (
            <li key={l.href}>
              <Link
                href={l.href}
                onClick={() => setMenu(false)}
                className={`flex flex-col items-center gap-0.5 py-2 text-[0.65rem] ${
                  active(l.href) ? "font-semibold text-ocean" : "text-navy/60"
                }`}
                aria-current={active(l.href) ? "page" : undefined}
              >
                <span className="text-lg leading-none" aria-hidden="true">
                  {l.icon}
                </span>
                {l.label}
              </Link>
            </li>
          ))}
          <li>
            <button
              type="button"
              onClick={() => setMenu((v) => !v)}
              className={`flex w-full flex-col items-center gap-0.5 py-2 text-[0.65rem] ${
                menu ? "font-semibold text-ocean" : "text-navy/60"
              }`}
              aria-expanded={menu}
            >
              <span className="text-lg leading-none" aria-hidden="true">
                ☰
              </span>
              メニュー
            </button>
          </li>
        </ul>
      </nav>
    </>
  );
}
