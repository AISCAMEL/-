import Link from "next/link";
import { Brand } from "@/components/brand";

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
        <p className="text-sm tracking-[0.4em] text-teal">IWASAWA SURF BASE</p>
        <h1 className="mt-6 text-4xl font-semibold leading-tight md:text-6xl">
          福島の波を、
          <br />
          もっと近くに。
        </h1>
        <p className="mt-6 max-w-md text-sand/90">
          初めてでも、また来たくなる海へ。
          岩沢海岸で、学んで・借りて・つながる。
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
          <p className="mt-10 text-center text-xs text-navy/40">
            ※ コミュニティ・スキル掲示板・波情報は順次公開予定です。
          </p>
        </div>
      </section>

      <footer className="bg-navy px-6 py-8 text-center text-xs text-sand/60">
        IWASAWA SURF BASE｜福島県双葉郡広野町・岩沢海岸エリア
      </footer>
    </div>
  );
}
