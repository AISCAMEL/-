/* eslint-disable @next/next/no-img-element */
import Link from "next/link";
import type { Metadata } from "next";
import {
  PARTNER_CATEGORIES,
  PARTNER_CATEGORY_LABEL,
  PARTNER_CATEGORY_EMOJI,
  PARTNER_CATEGORY_GRADIENT,
  isPartnerCategory,
} from "@/lib/partners";
import { demoPartners, type DemoPartner } from "@/lib/demo";
import { SITE } from "@/lib/site";

export const metadata: Metadata = {
  title: "岩沢まわり｜レンタカー・宿泊・食べる",
  description:
    "海の一日を、まちごと楽しむ。岩沢海岸まわりのレンタカー・宿泊・食べるところと提携。掲載は無料です。",
};

type Props = { searchParams: Promise<{ category?: string }> };

export default async function PartnersPage({ searchParams }: Props) {
  const { category } = await searchParams;
  const active = category && isPartnerCategory(category) ? category : null;

  // デモ：提携事業者はサンプルデータ。将来は partners テーブルに載せ替え。
  const partners: DemoPartner[] = active
    ? demoPartners.filter((p) => p.category === active)
    : demoPartners;

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      {/* Hero */}
      <div className="rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">IWASAWA AREA GUIDE</p>
        <h1 className="mt-2 text-2xl font-semibold">岩沢まわり</h1>
        <p className="mt-1 text-sm text-sand/90">
          泊まる・借りる・食べる。海の一日を、まちごと楽しむ。
        </p>
      </div>

      {/* 事業者向け導線（掲載無料） */}
      <Link
        href="/partners/business"
        className="mt-4 flex items-center justify-between rounded-2xl border border-teal/30 bg-teal/10 px-5 py-4 transition hover:bg-teal/20"
      >
        <div>
          <p className="text-sm font-semibold text-navy">
            事業者の方へ：掲載は<span className="text-ocean">無料</span>です。
          </p>
          <p className="mt-0.5 text-xs text-navy/60">
            レンタカー・宿泊・飲食店の方は、事業者向けログインから掲載できます。
          </p>
        </div>
        <span className="shrink-0 text-teal">→</span>
      </Link>

      {/* 説明（会員特典） */}
      <p className="mt-4 rounded-xl border border-ocean/20 bg-white px-4 py-3 text-xs text-navy/60">
        ℹ️ {SITE.region.beach}まわりの提携スポットです。
        <span className="font-medium text-navy/80">会員はタイアップ特典</span>
        （割引やサービス）を受けられます。掲載内容・特典は各事業者の提供によるものです。
      </p>

      {/* カテゴリ */}
      <nav className="mt-6 flex flex-wrap gap-2">
        <Chip label="すべて" href="/partners" active={!active} />
        {PARTNER_CATEGORIES.map((c) => (
          <Chip
            key={c.key}
            label={`${c.emoji} ${c.label}`}
            href={`/partners?category=${c.key}`}
            active={active === c.key}
          />
        ))}
      </nav>

      {/* 一覧 */}
      <div className="mt-6 grid gap-4 sm:grid-cols-2">
        {partners.map((p) => (
          <PartnerCard key={p.id} partner={p} />
        ))}
      </div>

      <p className="mt-8 text-center text-xs text-navy/40">
        情報は予告なく変更される場合があります。ご利用の際は各事業者に直接ご確認ください。
      </p>
    </main>
  );
}

function PartnerCard({ partner: p }: { partner: DemoPartner }) {
  return (
    <div className="flex flex-col overflow-hidden rounded-2xl border border-navy/10 bg-white shadow-sm transition hover:border-ocean/40">
      {/* ビジュアル（画像 or カテゴリ帯グラデ＋絵文字） */}
      <div
        className="relative flex aspect-[16/9] items-center justify-center"
        style={p.image_url ? undefined : { backgroundImage: PARTNER_CATEGORY_GRADIENT[p.category] }}
      >
        {p.image_url ? (
          <img src={p.image_url} alt={p.name} className="h-full w-full object-cover" />
        ) : (
          <span className="text-5xl drop-shadow-sm" aria-hidden>
            {PARTNER_CATEGORY_EMOJI[p.category]}
          </span>
        )}
        <span className="absolute left-3 top-3 rounded-full bg-white/85 px-2.5 py-0.5 text-[0.65rem] font-medium text-navy backdrop-blur">
          {PARTNER_CATEGORY_LABEL[p.category]}
        </span>
        {p.featured ? (
          <span className="absolute right-3 top-3 rounded-full bg-teal px-2.5 py-0.5 text-[0.65rem] font-semibold text-navy">
            おすすめ
          </span>
        ) : null}
      </div>

      <div className="flex flex-1 flex-col p-4">
        <p className="text-[0.7rem] text-ocean">{p.area}</p>
        <p className="mt-0.5 font-semibold text-navy">{p.name}</p>
        <p className="mt-0.5 text-sm text-navy/70">{p.tagline}</p>
        <p className="mt-2 text-xs leading-relaxed text-navy/60">{p.description}</p>

        {p.perk ? (
          <p className="mt-3 rounded-lg bg-teal/10 px-3 py-2 text-xs font-medium text-navy">
            <span className="mr-1">🎁 会員特典</span>
            {p.perk}
          </p>
        ) : null}

        <dl className="mt-3 space-y-1 text-[0.7rem] text-navy/50">
          {p.access ? (
            <div className="flex gap-1.5">
              <dt className="shrink-0">📍</dt>
              <dd>{p.access}</dd>
            </div>
          ) : null}
          {p.hours ? (
            <div className="flex gap-1.5">
              <dt className="shrink-0">🕒</dt>
              <dd>{p.hours}</dd>
            </div>
          ) : null}
          {p.tel ? (
            <div className="flex gap-1.5">
              <dt className="shrink-0">☎️</dt>
              <dd>{p.tel}</dd>
            </div>
          ) : null}
        </dl>
      </div>
    </div>
  );
}

function Chip({ label, href, active }: { label: string; href: string; active: boolean }) {
  return (
    <Link
      href={href}
      className={`rounded-full border px-3.5 py-1.5 text-sm transition ${
        active
          ? "border-ocean bg-ocean text-foam"
          : "border-navy/15 bg-white text-navy/70 hover:border-ocean"
      }`}
    >
      {label}
    </Link>
  );
}
