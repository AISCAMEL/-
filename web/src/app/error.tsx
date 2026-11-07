"use client";

import Link from "next/link";
import { useEffect } from "react";

// 予期しないエラーのときの画面（再読み込みとトップへの導線）
export default function Error({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <main className="flex min-h-[70vh] flex-col items-center justify-center bg-foam px-6 text-center">
      <p className="text-4xl">🌊</p>
      <h1 className="mt-3 text-xl font-semibold text-navy">うまく表示できませんでした</h1>
      <p className="mt-2 text-sm text-navy/60">一時的な不具合の可能性があります。少し時間をおいて、もう一度お試しください。</p>
      <div className="mt-6 flex gap-2">
        <button
          type="button"
          onClick={reset}
          className="rounded-full bg-ocean px-5 py-2 text-sm font-medium text-foam hover:bg-navy"
        >
          もう一度読み込む
        </button>
        <Link href="/" className="rounded-full border border-navy/15 px-5 py-2 text-sm text-navy/70 hover:border-ocean">
          トップへ
        </Link>
      </div>
    </main>
  );
}
