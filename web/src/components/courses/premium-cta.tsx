import Link from "next/link";

/** プレミアム（サブスク）への誘導。決済は将来のPSP実装。 */
export function PremiumCTA({ compact = false }: { compact?: boolean }) {
  if (compact) {
    return (
      <Link
        href="/premium"
        className="inline-flex items-center gap-1.5 rounded-full bg-teal px-4 py-2 text-sm font-medium text-navy transition hover:bg-ocean hover:text-foam"
      >
        ✦ プレミアムで全編見る
      </Link>
    );
  }
  return (
    <div className="rounded-2xl border border-teal/30 bg-teal/10 p-6 text-center">
      <p className="text-base font-semibold text-navy">
        ✦ このレッスンはプレミアム限定です
      </p>
      <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-navy/70">
        オンライン講座（動画＋マニュアル）は、月額プレミアムで全編見放題。
        来訪前に家で予習して、海での上達を早めましょう。
      </p>
      <Link
        href="/premium"
        className="mt-4 inline-block rounded-full bg-ocean px-6 py-2.5 text-sm font-medium text-foam transition hover:bg-navy"
      >
        プレミアムを見る →
      </Link>
    </div>
  );
}
