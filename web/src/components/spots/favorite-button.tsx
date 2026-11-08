"use client";

import { useFavoriteSpots, toggleFavoriteSpot } from "@/lib/favorites";

/** ポイントのお気に入り登録ボタン（ログイン不要） */
export function FavoriteButton({ spotId, name }: { spotId: string; name: string }) {
  const favs = useFavoriteSpots();
  const on = favs.includes(spotId);
  return (
    <button
      type="button"
      onClick={() => toggleFavoriteSpot(spotId)}
      aria-pressed={on}
      aria-label={on ? `${name}をお気に入りから外す` : `${name}をお気に入りに追加`}
      className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
        on ? "bg-white text-ocean" : "bg-white/15 text-foam hover:bg-white/25"
      }`}
    >
      {on ? "★ お気に入り" : "☆ お気に入りに追加"}
    </button>
  );
}
