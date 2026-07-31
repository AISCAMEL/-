import Link from "next/link";
import { Brand } from "@/components/brand";
import { SiteFooter } from "@/components/site-footer";
import { SITE } from "@/lib/site";

const SERVICES = [
  { title: "オンラインスクール", desc: "海に入る前の不安を、家で解消。" },
  { title: "ギアレンタル", desc: "ボードもウェットも、手ぶらで。" },
  { title: "移動サポート", desc: "駅・ICから海まで、来やすく。" },
  { title: "ローカルガイド", desc: "その日の海を、地元の目線で。" },
];

export default function Home() {
  return (
    <div className="flex min-h-screen flex-col">
      {/* ヘッダー */}
      <header className="flex items-center justify-between px-6 py-4">
        <Brand />
        <nav className="flex items-center gap-3 text-sm">
          <Link href="/login" className="text-navy/70 hover:text-ocean">
            ログイン
          </Link>
          <Link
            href="/signup"
            className="rounded-lg bg-ocean px-4 py-2 font-medium text-foam transition hover:bg-navy"
          >
            新規登録
          </Link>
        </nav>
      </header>

      {/* Hero */}
      <section className="bg-ocean-gradient flex flex-1 flex-col items-center justify-center px-6 py-24 text-center text-foam">
        <p className="text-sm tracking-[0.4em] text-teal">{SITE.name}</p>
        <h1 className="mt-6 text-4xl font-semibold leading-tight md:text-6xl">
          {SITE.tagline}
        </h1>
        <p className="mt-6 max-w-md text-sand/90">
          初めてでも、また来たくなる海へ。
          {SITE.region.beach}で、学んで・借りて・つながる。
        </p>
        <div className="mt-10 flex gap-3">
          <Link
            href="/signup"
            className="rounded-full bg-teal px-6 py-3 font-medium text-navy transition hover:bg-foam"
          >
            仲間になる
          </Link>
          <Link
            href="/login"
            className="rounded-full border border-foam/40 px-6 py-3 font-medium text-foam transition hover:border-foam"
          >
            ログイン
          </Link>
        </div>
      </section>

      {/* 4サービス */}
      <section className="bg-foam px-6 py-16">
        <div className="mx-auto max-w-4xl">
          <h2 className="text-center text-xl font-semibold text-navy">
            学べる / 借りられる / 移動できる / 案内される
          </h2>
          <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {SERVICES.map((s) => (
              <div
                key={s.title}
                className="rounded-2xl border border-navy/10 bg-white p-5 shadow-sm"
              >
                <h3 className="font-semibold text-ocean">{s.title}</h3>
                <p className="mt-2 text-sm text-navy/60">{s.desc}</p>
              </div>
            ))}
          </div>
          <div className="mt-10 flex flex-wrap justify-center gap-3 text-sm">
            <Link
              href="/instructors"
              className="rounded-full border border-ocean/30 px-5 py-2 font-medium text-ocean transition hover:bg-ocean hover:text-foam"
            >
              サーフィンスクール
            </Link>
            <Link
              href="/community"
              className="rounded-full border border-ocean/30 px-5 py-2 font-medium text-ocean transition hover:bg-ocean hover:text-foam"
            >
              コミュニティを見る
            </Link>
            <Link
              href="/courses"
              className="rounded-full border border-ocean/30 px-5 py-2 font-medium text-ocean transition hover:bg-ocean hover:text-foam"
            >
              オンライン講座
            </Link>
            <Link
              href="/shop"
              className="rounded-full border border-ocean/30 px-5 py-2 font-medium text-ocean transition hover:bg-ocean hover:text-foam"
            >
              オンラインショップ
            </Link>
            <Link
              href="/skills"
              className="rounded-full border border-ocean/30 px-5 py-2 font-medium text-ocean transition hover:bg-ocean hover:text-foam"
            >
              スキル掲示板
            </Link>
            <Link
              href="/waves"
              className="rounded-full border border-teal/40 px-5 py-2 font-medium text-teal transition hover:bg-teal hover:text-navy"
            >
              今日の波情報 🌊
            </Link>
            <Link
              href="/spots"
              className="rounded-full border border-ocean/30 px-5 py-2 font-medium text-ocean transition hover:bg-ocean hover:text-foam"
            >
              全国サーフポイント 🗾
            </Link>
            <Link
              href="/partners"
              className="rounded-full border border-ocean/30 px-5 py-2 font-medium text-ocean transition hover:bg-ocean hover:text-foam"
            >
              岩沢まわり 🚗🏨🍽
            </Link>
          </div>
          <div className="mt-8 rounded-2xl border border-teal/30 bg-teal/5 p-5 text-center">
            <p className="text-sm font-semibold text-navy">
              🌊 海に入る前に、ルールとマナーを。
            </p>
            <p className="mt-1 text-xs text-navy/60">
              道具を揃えて終わり、ではなく——知って、プロに習って、海に入る。
            </p>
            <Link
              href="/rules"
              className="mt-3 inline-block rounded-full bg-navy px-5 py-2 text-sm font-medium text-foam transition hover:bg-ocean"
            >
              海のルールを学ぶ →
            </Link>
          </div>
        </div>
      </section>

      {/* サーフィンスクール（プロ講師）*/}
      <section className="bg-ocean-gradient px-6 py-16 text-foam">
        <div className="mx-auto max-w-3xl text-center">
          <p className="text-xs tracking-[0.3em] text-teal">SURF SCHOOL</p>
          <h2 className="mt-3 text-2xl font-semibold">プロと、海へ。</h2>
          <p className="mt-3 text-sm text-sand/90">
            トッププロ・トップアマ・認定インストラクターが、
            初めての一本から丁寧にサポート。登録は無料、受講は月額制。
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3 text-sm">
            <Link
              href="/instructors"
              className="rounded-full bg-teal px-6 py-2.5 font-medium text-navy transition hover:bg-foam"
            >
              講師を探す
            </Link>
            <Link
              href="/articles"
              className="rounded-full border border-foam/40 px-6 py-2.5 font-medium text-foam transition hover:border-foam"
            >
              プロのブログを読む
            </Link>
          </div>
        </div>
      </section>

      <SiteFooter />
    </div>
  );
}
