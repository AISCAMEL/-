"use client";

import Link from "next/link";
import { useFavoriteSpots } from "@/lib/favorites";
import { getSpotById, SPOT_LEVEL_LABEL } from "@/lib/spots";

/** お気に入りのポイント一覧（登録があるときだけ表示） */
export function FavoriteSpots({ className = "" }: { className?: string }) {
  const spots = useFavoriteSpots()
    .map((id) => getSpotById(id))
    .filter((s) => s != null);
  if (spots.length === 0) return null;
  return (
    <section className={`rounded-2xl border border-navy/10 bg-white p-4 shadow-sm ${className}`}>
      <h2 className="text-sm font-semibold text-navy">★ お気に入りのポイント</h2>
      <div className="mt-2 flex flex-wrap gap-2">
        {spots.map((s) => (
          <Link
            key={s.id}
            href={`/spots/${s.id}`}
            className="rounded-full border border-ocean/25 bg-foam px-3 py-1.5 text-sm text-navy transition hover:border-ocean"
          >
            {s.name}
            <span className="ml-1 text-[0.65rem] text-navy/45">
              {s.prefecture}・{SPOT_LEVEL_LABEL[s.level]}
            </span>
          </Link>
        ))}
      </div>
      <p className="mt-2 text-[0.65rem] text-navy/40">この端末のブラウザに保存されています（ログイン不要）。</p>
    </section>
  );
}
