"use client";

import { useSyncExternalStore } from "react";

// お気に入りポイント（ログイン不要・この端末のブラウザに保存）
const KEY = "iwasawa-fav-spots";
const EVENT = "iwasawa-fav-change";

function read(): string {
  try {
    return localStorage.getItem(KEY) ?? "[]";
  } catch {
    return "[]";
  }
}

function subscribe(cb: () => void) {
  window.addEventListener("storage", cb);
  window.addEventListener(EVENT, cb);
  return () => {
    window.removeEventListener("storage", cb);
    window.removeEventListener(EVENT, cb);
  };
}

function parse(raw: string): string[] {
  try {
    const v = JSON.parse(raw);
    return Array.isArray(v) ? v.filter((x): x is string => typeof x === "string") : [];
  } catch {
    return [];
  }
}

/** お気に入りのポイントID一覧（サーバー描画時は空） */
export function useFavoriteSpots(): string[] {
  const raw = useSyncExternalStore(subscribe, read, () => "[]");
  return parse(raw);
}

export function toggleFavoriteSpot(id: string) {
  const list = parse(read());
  const next = list.includes(id) ? list.filter((x) => x !== id) : [id, ...list].slice(0, 20);
  try {
    localStorage.setItem(KEY, JSON.stringify(next));
  } catch {
    /* 保存できない環境（プライベートブラウズ等）では無視 */
  }
  window.dispatchEvent(new Event(EVENT));
}
