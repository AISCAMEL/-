"use client";

import { useEffect } from "react";

/** サービスワーカーを登録し、PWAとしてインストール可能にする。 */
export function PwaRegister() {
  useEffect(() => {
    if (typeof window === "undefined") return;
    if (!("serviceWorker" in navigator)) return;
    if (process.env.NODE_ENV !== "production") return; // 開発時は登録しない

    const onLoad = () => {
      navigator.serviceWorker.register("/sw.js").catch(() => {
        // 失敗してもアプリ自体は動くので握りつぶす
      });
    };
    window.addEventListener("load", onLoad);
    return () => window.removeEventListener("load", onLoad);
  }, []);

  return null;
}
