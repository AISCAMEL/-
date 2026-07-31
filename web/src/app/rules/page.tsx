import { Suspense } from "react";
import Link from "next/link";
import type { Metadata } from "next";
import { Brand } from "@/components/brand";
import { SiteFooter } from "@/components/site-footer";
import { RulesAck } from "@/components/rules/rules-ack";
import { SITE } from "@/lib/site";

export const metadata: Metadata = {
  title: "海に入る前に",
  description: `海のルール・マナー・安全の注意事項。ルールを知り、プロに習い、海に入る。${SITE.name} のスタンス。`,
};

const SAFETY = [
  { icon: "🌊", t: "離岸流（リップカレント）を知る", d: "沖へ流れる速い流れ。捕まったら岸に平行に泳いで脱出。無理に逆らわない。" },
  { icon: "📏", t: "自分のレベルに合った海況で", d: "サイズ・風・混雑を確認。大きい日・風の強い日は無理をしない。" },
  { icon: "👥", t: "できるだけ一人で入らない", d: "初めは仲間や講師と。体調が悪い日は入らない。" },
  { icon: "🩱", t: "装備と準備", d: "リーシュ必須。準備運動、寒さ・日焼け・水分対策を。" },
];

const RULES = [
  { t: "一本の波に、一人。", d: "ピーク（波の割れ始め）に近い人が優先。すでに誰かが乗っている波に前から入る『ドロップイン（前乗り）』は禁止です。" },
  { t: "パドルアウトは進路を避ける", d: "沖に出るときは、波に乗っている人の進路をふさがないよう、割れていない側や後ろへ回り込みます。" },
  { t: "スネーキング（順番の割り込み）をしない", d: "優先権を得るために内側へ回り込んで順番を奪う行為はマナー違反です。順番を守りましょう。" },
  { t: "初心者は空いた場所・インサイドで", d: "混雑したピークにいきなり入らず、空いたスペースやインサイド（岸寄り）で練習を。" },
];

const MANNERS = [
  { t: "挨拶と敬意", d: "海に入るとき、ローカルや先行者に挨拶を。敬意を持った振る舞いが、気持ちよく続けるコツです。" },
  { t: "譲り合い", d: "混雑時はお互い譲り合い。良い波を独り占めしない。" },
  { t: "環境とマナー", d: "ゴミは必ず持ち帰る。駐車は決められた場所に、着替えは人目に配慮して。地域あっての海です。" },
];

function Card({ icon, t, d }: { icon?: string; t: string; d: string }) {
  return (
    <div className="rounded-2xl border border-navy/10 bg-white p-5">
      <p className="font-semibold text-navy">
        {icon ? <span className="mr-1.5">{icon}</span> : null}
        {t}
      </p>
      <p className="mt-1.5 text-sm leading-relaxed text-navy/70">{d}</p>
    </div>
  );
}

function Step({ n, t, d, active }: { n: number; t: string; d: string; active?: boolean }) {
  return (
    <div className={`flex-1 rounded-2xl border p-4 text-center ${active ? "border-teal/50 bg-teal/10" : "border-white/15 bg-white/5"}`}>
      <div className={`mx-auto flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ${active ? "bg-teal text-navy" : "bg-white/15 text-foam"}`}>{n}</div>
      <p className="mt-2 text-sm font-semibold text-foam">{t}</p>
      <p className="mt-1 text-[0.7rem] text-sand/80">{d}</p>
    </div>
  );
}

export default function RulesPage() {
  return (
    <div className="flex min-h-screen flex-col bg-foam">
      <header className="border-b border-navy/10 bg-white px-6 py-4">
        <div className="mx-auto flex max-w-3xl items-center justify-between">
          <Brand />
          <Link href="/" className="text-sm text-navy/60 hover:text-ocean">トップへ</Link>
        </div>
      </header>

      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        {/* Hero + 3ステップのスタンス */}
        <section className="rounded-2xl bg-ocean-gradient p-6 text-foam">
          <p className="text-xs tracking-[0.3em] text-teal">BEFORE YOU SURF</p>
          <h1 className="mt-2 text-2xl font-semibold">海に入る前に。</h1>
          <p className="mt-2 text-sm leading-relaxed text-sand/90">
            道具を揃えて終わり、ではありません。かつて実店舗が伝えてきた海のルールとマナーを、
            このアプリで学べます。<strong className="text-foam">知って、習って、海に入る。</strong>
          </p>
          <div className="mt-5 flex gap-2">
            <Step n={1} t="ルールを知る" d="このページで学ぶ" active />
            <Step n={2} t="プロに習う" d="スクールで指導" />
            <Step n={3} t="海に入る" d="安全に、楽しく" />
          </div>
        </section>

        {/* 安全の注意事項 */}
        <h2 className="mt-8 text-sm font-semibold text-navy/70">⚠️ 安全の注意事項</h2>
        <div className="mt-3 grid gap-3 sm:grid-cols-2">
          {SAFETY.map((s) => <Card key={s.t} {...s} />)}
        </div>

        {/* ルール */}
        <h2 className="mt-8 text-sm font-semibold text-navy/70">📜 サーフィンのルール</h2>
        <div className="mt-3 space-y-3">
          {RULES.map((r) => <Card key={r.t} {...r} />)}
        </div>

        {/* マナー */}
        <h2 className="mt-8 text-sm font-semibold text-navy/70">🤝 海とローカルのマナー</h2>
        <div className="mt-3 space-y-3">
          {MANNERS.map((m) => <Card key={m.t} {...m} />)}
        </div>

        {/* 確認チェック → プロへ */}
        <div className="mt-8">
          <Suspense fallback={null}>
            <RulesAck />
          </Suspense>
        </div>

        {/* 補足導線 */}
        <div className="mt-6 grid gap-3 sm:grid-cols-2">
          <Link href="/instructors" className="rounded-2xl border border-navy/10 bg-white p-5 transition hover:border-ocean/40">
            <p className="font-semibold text-navy">🏄 プロに習う</p>
            <p className="mt-1 text-sm text-navy/60">はじめの一本は、認定インストラクターと一緒に。</p>
          </Link>
          <Link href="/community" className="rounded-2xl border border-navy/10 bg-white p-5 transition hover:border-ocean/40">
            <p className="font-semibold text-navy">💬 わからないことを聞く</p>
            <p className="mt-1 text-sm text-navy/60">コミュニティで、初心者の質問も歓迎です。</p>
          </Link>
        </div>
      </main>

      <SiteFooter />
    </div>
  );
}
