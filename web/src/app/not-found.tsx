import Link from "next/link";
import type { Metadata } from "next";
import { SitePage } from "@/components/site-page";
import { Namisuke } from "@/components/chat/namisuke";

export const metadata: Metadata = { title: "ページが見つかりません" };

export default function NotFound() {
  return (
    <SitePage title="ページが見つかりません" lead="URLが変わったか、ページが削除された可能性があります。">
      <div className="flex flex-col items-center rounded-2xl bg-white p-8 text-center shadow-sm">
        <Namisuke mood="thinking" size={110} />
        <p className="mt-3 text-sm text-navy/70">あれ…この波は見つからなかったみたい。</p>
        <div className="mt-6 flex flex-wrap justify-center gap-2">
          <Link href="/" className="rounded-full bg-ocean px-5 py-2 text-sm font-medium text-foam hover:bg-navy">
            トップへ
          </Link>
          <Link href="/waves" className="rounded-full border border-navy/15 px-5 py-2 text-sm text-navy/70 hover:border-ocean">
            今日の波
          </Link>
          <Link href="/spots" className="rounded-full border border-navy/15 px-5 py-2 text-sm text-navy/70 hover:border-ocean">
            全国サーフポイント
          </Link>
        </div>
      </div>
    </SitePage>
  );
}
