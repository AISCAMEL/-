import Link from "next/link";
import type { Metadata } from "next";
import { SitePage, Section } from "@/components/site-page";
import { AD_SLOTS, AD_ASPECT } from "@/lib/ads";
import { SITE } from "@/lib/site";

export const metadata: Metadata = {
  title: "広告掲載・スポンサーのご案内",
  description: `${SITE.name}のバナー広告枠・スポンサー掲載のご案内。波情報・全国サーフポイント・地域ガイドに掲載できます。`,
};

export default function AdsPage() {
  return (
    <SitePage
      title="広告掲載・スポンサーのご案内"
      lead="サーフィンを楽しむ人・これから始める人に、あなたのお店やサービスを届けませんか。"
    >
      <div className="rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">SPONSOR</p>
        <p className="mt-2 text-lg font-semibold">海に向かう人の「行く前」と「海の前」に届く広告枠</p>
        <ul className="mt-3 space-y-1 text-sm text-sand/95">
          <li>・毎日の波情報・潮回り・AI波解説を見に来るサーファー</li>
          <li>・全国のサーフポイントから旅先を探す人</li>
          <li>・岩沢まわりのレンタカー・宿・食事を調べる来訪者</li>
        </ul>
      </div>

      <Section heading="広告枠の一覧">
        <p>
          画像バナー（横長）を、下記の枠に掲載できます。1つの枠に複数の広告主がいる場合は、表示のたびに入れ替わります。
          すべての広告には「PR」表記が付きます。
        </p>
      </Section>

      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        {AD_SLOTS.map((s) => (
          <div
            key={s.key}
            id={`slot-${s.key}`}
            className="scroll-mt-24 rounded-2xl border border-navy/10 bg-white p-4 shadow-sm target:border-ocean target:ring-2 target:ring-ocean/30"
          >
            <div
              className={`flex ${AD_ASPECT} w-full items-center justify-center rounded-xl border-2 border-dashed border-ocean/30 bg-foam text-xs font-medium text-ocean`}
            >
              {s.size}px
            </div>
            <p className="mt-3 font-semibold text-navy">{s.label}</p>
            <p className="text-xs text-navy/60">{s.where}</p>
            <p className="mt-1.5 text-xs leading-relaxed text-navy/75">{s.note}</p>
          </div>
        ))}
      </div>

      <Section heading="入稿データ">
        <ul className="list-inside list-disc space-y-1">
          <li>画像サイズ：横1600 × 縦680px（同じ比率なら可）</li>
          <li>形式：JPG / PNG / WebP（1MB以内を推奨）</li>
          <li>リンク先：広告主さまのWebサイト・予約ページ・SNSなど（https のURL）</li>
          <li>掲載期間：開始日・終了日を指定できます（自動で掲載・終了）</li>
          <li>レポート：クリック数をお知らせします</li>
        </ul>
      </Section>

      <Section heading="掲載料金">
        <p>
          枠・期間によって異なります。地元のお店・事業者さまの応援プランもあります。
          お気軽にお問い合わせください。
        </p>
      </Section>

      <Section heading="掲載の流れ">
        <ol className="list-inside list-decimal space-y-1">
          <li>お問い合わせフォームから「広告掲載希望」とご連絡ください</li>
          <li>ご希望の枠・期間・料金をご案内します</li>
          <li>バナー画像とリンク先をご入稿ください（制作のご相談も可）</li>
          <li>内容を確認のうえ、掲載を開始します</li>
        </ol>
      </Section>

      <Section heading="掲載基準">
        <p>
          海を楽しむ人に安心して見てもらえるよう、法令・公序良俗に反するもの、誤解を招く表現、
          海の安全を軽視する内容などはお断りしています。サイトの内容（波情報・AIの回答など）が
          広告主さまの影響を受けることはありません。
        </p>
      </Section>

      <div className="mt-10 flex flex-wrap gap-3">
        <Link
          href="/contact"
          className="rounded-full bg-ocean px-6 py-2.5 text-sm font-medium text-foam transition hover:bg-navy"
        >
          広告掲載について問い合わせる
        </Link>
        <Link
          href="/partners/business"
          className="rounded-full border border-navy/15 px-6 py-2.5 text-sm font-medium text-navy/70 transition hover:border-ocean hover:text-ocean"
        >
          無料の店舗掲載（岩沢まわり）はこちら
        </Link>
      </div>
    </SitePage>
  );
}
