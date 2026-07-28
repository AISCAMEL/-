import Link from "next/link";
import type { Metadata } from "next";
import { SitePage } from "@/components/site-page";

export const metadata: Metadata = {
  title: "プレミアム｜IWASAWA SURF BASE",
  description: "オンライン講座（動画＋マニュアル）が見放題。月額プレミアム。",
};

const FREE = [
  "コミュニティ・波情報の閲覧・投稿",
  "スキル掲示板・スクールの申し込み",
  "オンライン講座の無料プレビュー",
  "海のルール学習・修了バッジ",
];

const PREMIUM = [
  "オンライン講座がすべて見放題（動画＋マニュアル）",
  "いい波プッシュ通知＋7日先の波予報",
  "スクール受講の優先予約",
  "現地パートナー（カフェ・宿）特典",
  "広告の非表示",
];

export default function PremiumPage() {
  return (
    <SitePage
      title="プレミアム"
      lead="オンライン講座が見放題。海での上達を、家からもっと早く。"
    >
      <div className="grid gap-4 md:grid-cols-2">
        {/* Free */}
        <div className="rounded-2xl border border-navy/10 bg-white p-6">
          <p className="text-sm font-semibold text-navy/60">無料プラン</p>
          <p className="mt-2 text-2xl font-semibold text-navy">¥0</p>
          <p className="text-xs text-navy/40">登録は無料</p>
          <ul className="mt-4 space-y-2 text-sm text-navy/70">
            {FREE.map((f) => (
              <li key={f} className="flex gap-2">
                <span className="text-teal">✓</span>
                {f}
              </li>
            ))}
          </ul>
        </div>

        {/* Premium */}
        <div className="rounded-2xl border-2 border-teal bg-white p-6 shadow-sm">
          <p className="text-sm font-semibold text-teal">✦ プレミアム</p>
          <p className="mt-2 text-2xl font-semibold text-navy">
            ¥980 <span className="text-sm font-normal text-navy/50">/ 月（税込）</span>
          </p>
          <p className="text-xs text-navy/40">いつでも解約できます</p>
          <ul className="mt-4 space-y-2 text-sm text-navy/80">
            {PREMIUM.map((p) => (
              <li key={p} className="flex gap-2">
                <span className="text-teal">✓</span>
                {p}
              </li>
            ))}
          </ul>
          <div className="mt-6 rounded-xl bg-teal/10 p-4 text-center">
            <p className="text-xs text-navy/60">
              サブスクリプション決済は Square で提供予定です（近日対応）。
            </p>
            <Link
              href="/contact"
              className="mt-3 inline-block rounded-full bg-ocean px-6 py-2.5 text-sm font-medium text-foam transition hover:bg-navy"
            >
              先行案内を受け取る（お問い合わせ）
            </Link>
          </div>
        </div>
      </div>

      <p className="mt-6 text-xs text-navy/40">
        ※ 料金・特典は例です。決済は決済代行会社（PSP）を通じて処理し、カード情報は当社で保持しません。
        課金条件は
        <Link href="/legal/tokushoho" className="text-ocean hover:underline">特定商取引法表記</Link>
        に準じます。
      </p>

      <div className="mt-8 text-center">
        <Link
          href="/courses"
          className="inline-block rounded-full border border-navy/15 px-6 py-2.5 text-sm font-medium text-navy/70 transition hover:border-ocean hover:text-ocean"
        >
          講座の内容を見る →
        </Link>
      </div>
    </SitePage>
  );
}
