"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { addToCart, type CartLine } from "@/lib/shop";

export function AddToCart({ line }: { line: CartLine }) {
  const router = useRouter();
  const [qty, setQty] = useState(1);
  const [added, setAdded] = useState(false);

  function add() {
    addToCart({ ...line, qty });
    setAdded(true);
    setTimeout(() => setAdded(false), 1600);
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-3">
        <span className="text-sm text-navy/60">数量</span>
        <div className="flex items-center rounded-lg border border-navy/15">
          <button
            onClick={() => setQty((q) => Math.max(1, q - 1))}
            className="px-3 py-1.5 text-navy/60 hover:text-ocean"
            aria-label="減らす"
          >
            −
          </button>
          <span className="w-8 text-center text-sm">{qty}</span>
          <button
            onClick={() => setQty((q) => q + 1)}
            className="px-3 py-1.5 text-navy/60 hover:text-ocean"
            aria-label="増やす"
          >
            ＋
          </button>
        </div>
      </div>
      <button
        onClick={add}
        className="w-full rounded-lg bg-ocean px-4 py-3 font-medium text-foam transition hover:bg-navy"
      >
        {added ? "カートに入れました ✓" : "カートに入れる"}
      </button>
      <button
        onClick={() => router.push("/cart")}
        className="w-full rounded-lg border border-navy/15 px-4 py-2.5 text-sm font-medium text-navy/70 transition hover:border-ocean hover:text-ocean"
      >
        カートを見る →
      </button>
    </div>
  );
}
