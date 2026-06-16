"use client";

import { useState } from "react";
import { Icon } from "./Icon";
import type { Faq } from "@/content/faq";

export function Accordion({ items }: { items: Faq[] }) {
  const [open, setOpen] = useState<number | null>(0);

  return (
    <div className="divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
      {items.map((item, i) => {
        const isOpen = open === i;
        return (
          <div key={i}>
            <h3>
              <button
                type="button"
                onClick={() => setOpen(isOpen ? null : i)}
                aria-expanded={isOpen}
                className="flex w-full items-center justify-between gap-4 px-5 py-5 text-left sm:px-6"
              >
                <span className="flex items-start gap-3 text-base font-semibold text-ink-900">
                  <span className="mt-0.5 text-brand-600">Q.</span>
                  {item.q}
                </span>
                <Icon
                  name="chevron-down"
                  className={`h-5 w-5 flex-none text-brand-600 transition-transform duration-200 ${
                    isOpen ? "rotate-180" : ""
                  }`}
                />
              </button>
            </h3>
            <div
              className={`grid transition-all duration-200 ${
                isOpen ? "grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0"
              }`}
            >
              <div className="overflow-hidden">
                <p className="flex gap-3 px-5 pb-6 text-sm leading-relaxed text-ink-600 sm:px-6">
                  <span className="font-semibold text-accent-600">A.</span>
                  {item.a}
                </p>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}
