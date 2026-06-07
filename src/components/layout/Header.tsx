"use client";

import Link from "next/link";
import { useState } from "react";
import { mainNav } from "@/content/navigation";
import { site } from "@/content/site";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";

export function Header() {
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur">
      <div className="container flex h-16 items-center justify-between gap-4">
        <Link href="/" className="flex items-center gap-2" aria-label={`${site.name} ホーム`}>
          <span className="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 font-bold text-white">
            A
          </span>
          <span className="flex flex-col leading-none">
            <span className="text-base font-bold tracking-tight text-ink-900">{site.name}</span>
            <span className="text-[10px] font-medium tracking-wider text-brand-600">
              {site.brandTagline}
            </span>
          </span>
        </Link>

        {/* デスクトップナビ */}
        <nav className="hidden items-center gap-1 lg:flex" aria-label="メインナビゲーション">
          {mainNav.map((item) => (
            <div key={item.href} className="group relative">
              <Link
                href={item.href}
                className="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-ink-700 transition-colors hover:text-brand-700"
              >
                {item.label}
                {item.children && <Icon name="chevron-down" className="h-3.5 w-3.5" />}
              </Link>
              {item.children && (
                <div className="invisible absolute left-0 top-full w-72 pt-2 opacity-0 transition-all group-hover:visible group-hover:opacity-100">
                  <div className="rounded-xl border border-slate-200 bg-white p-2 shadow-card">
                    {item.children.map((c) => (
                      <Link
                        key={c.href}
                        href={c.href}
                        className="block rounded-lg px-3 py-2.5 hover:bg-brand-50"
                      >
                        <span className="block text-sm font-semibold text-ink-900">{c.label}</span>
                        {c.description && (
                          <span className="block text-xs text-ink-500">{c.description}</span>
                        )}
                      </Link>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <Button href="/contact" size="md" className="hidden sm:inline-flex">
            無料相談
          </Button>
          <button
            type="button"
            className="grid h-10 w-10 place-items-center rounded-md text-ink-700 lg:hidden"
            onClick={() => setOpen(true)}
            aria-label="メニューを開く"
          >
            <Icon name="menu" className="h-6 w-6" />
          </button>
        </div>
      </div>

      {/* モバイルメニュー */}
      {open && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div
            className="absolute inset-0 bg-ink-900/50"
            onClick={() => setOpen(false)}
            aria-hidden="true"
          />
          <div className="absolute right-0 top-0 flex h-full w-80 max-w-[85%] flex-col bg-white shadow-xl">
            <div className="flex h-16 items-center justify-between border-b border-slate-200 px-5">
              <span className="font-bold text-ink-900">メニュー</span>
              <button
                type="button"
                className="grid h-10 w-10 place-items-center rounded-md text-ink-700"
                onClick={() => setOpen(false)}
                aria-label="メニューを閉じる"
              >
                <Icon name="close" className="h-6 w-6" />
              </button>
            </div>
            <nav className="flex-1 overflow-y-auto px-3 py-4" aria-label="モバイルナビゲーション">
              {mainNav.map((item) => (
                <div key={item.href} className="py-1">
                  <Link
                    href={item.href}
                    className="block rounded-lg px-3 py-2.5 text-base font-semibold text-ink-900 hover:bg-brand-50"
                    onClick={() => setOpen(false)}
                  >
                    {item.label}
                  </Link>
                  {item.children && (
                    <div className="ml-3 border-l border-slate-200 pl-2">
                      {item.children.map((c) => (
                        <Link
                          key={c.href}
                          href={c.href}
                          className="block rounded-lg px-3 py-2 text-sm text-ink-600 hover:bg-brand-50"
                          onClick={() => setOpen(false)}
                        >
                          {c.label}
                        </Link>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </nav>
            <div className="border-t border-slate-200 p-4">
              <Button href="/contact" size="lg" className="w-full" onClick={() => setOpen(false)}>
                無料で相談する
              </Button>
            </div>
          </div>
        </div>
      )}
    </header>
  );
}
