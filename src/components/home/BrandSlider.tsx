"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Section, SectionHeading } from "@/components/ui/Section";
import { Icon } from "@/components/ui/Icon";
import { services } from "@/content/services";

const slides = services.filter((s) => s.brand);
const AUTOPLAY_MS = 5000;

/** ブランドを1枚ずつ見せる自動スライダー（手動操作・自動送り対応） */
export function BrandSlider() {
  const [index, setIndex] = useState(0);
  const [paused, setPaused] = useState(false);
  const count = slides.length;

  const go = useCallback(
    (next: number) => setIndex(((next % count) + count) % count),
    [count],
  );

  useEffect(() => {
    if (paused) return;
    const reduce =
      typeof window !== "undefined" &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduce) return;
    const id = setInterval(() => setIndex((i) => (i + 1) % count), AUTOPLAY_MS);
    return () => clearInterval(id);
  }, [paused, count]);

  return (
    <Section tone="light">
      <SectionHeading
        eyebrow="Brands"
        title="アイズのブランド"
        lead="それぞれの専門性で、お客様のニーズにお応えします。"
        align="center"
      />

      <div
        className="relative mx-auto mt-12 max-w-4xl"
        onMouseEnter={() => setPaused(true)}
        onMouseLeave={() => setPaused(false)}
        role="group"
        aria-roledescription="カルーセル"
        aria-label="ブランド紹介"
      >
        <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-card">
          <div
            className="flex transition-transform duration-500 ease-out"
            style={{ transform: `translateX(-${index * 100}%)` }}
          >
            {slides.map((s) => (
              <article
                key={s.slug}
                className="grid w-full flex-none items-center gap-6 p-8 sm:grid-cols-[auto_1fr] sm:p-10"
                aria-hidden={slides[index].slug !== s.slug}
              >
                <span className="grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
                  <Icon name={s.icon} className="h-8 w-8" />
                </span>
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm font-bold tracking-wide text-brand-600">
                      {s.brand}
                    </p>
                    {s.comingSoon && (
                      <span className="rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold text-accent-700 ring-1 ring-inset ring-accent-200">
                        準備中
                      </span>
                    )}
                  </div>
                  <h3 className="mt-1 text-xl font-bold text-ink-900">{s.name}</h3>
                  <p className="mt-3 text-sm leading-relaxed text-ink-600">{s.summary}</p>
                  <Link
                    href={`/services/${s.slug}`}
                    className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800"
                  >
                    詳しく見る
                    <Icon name="arrow-right" className="h-4 w-4" />
                  </Link>
                </div>
              </article>
            ))}
          </div>
        </div>

        {/* 前後ボタン */}
        <button
          type="button"
          onClick={() => go(index - 1)}
          aria-label="前のブランド"
          className="absolute left-0 top-1/2 grid h-10 w-10 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-slate-200 bg-white text-ink-700 shadow-card transition hover:text-brand-700"
        >
          <Icon name="arrow-right" className="h-5 w-5 rotate-180" />
        </button>
        <button
          type="button"
          onClick={() => go(index + 1)}
          aria-label="次のブランド"
          className="absolute right-0 top-1/2 grid h-10 w-10 -translate-y-1/2 translate-x-1/2 place-items-center rounded-full border border-slate-200 bg-white text-ink-700 shadow-card transition hover:text-brand-700"
        >
          <Icon name="arrow-right" className="h-5 w-5" />
        </button>

        {/* ドット */}
        <div className="mt-6 flex justify-center gap-2">
          {slides.map((s, i) => (
            <button
              key={s.slug}
              type="button"
              onClick={() => go(i)}
              aria-label={`${s.brand} を表示`}
              aria-current={i === index}
              className={`h-2 rounded-full transition-all ${
                i === index ? "w-6 bg-brand-600" : "w-2 bg-slate-300 hover:bg-slate-400"
              }`}
            />
          ))}
        </div>
      </div>
    </Section>
  );
}
