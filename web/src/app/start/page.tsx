import Link from "next/link";
import type { Metadata } from "next";
import { SitePage, Section } from "@/components/site-page";
import { SITE } from "@/lib/site";

export const metadata: Metadata = {
  title: "はじめての方へ｜サーフィンを始める5ステップ",
  description: `はじめてのサーフィンを、${SITE.region.beach}で。海のルール、無料レッスン、波と潮の見方、スクール選び、行く準備までを順番に。`,
};

const STEPS = [
  {
    n: 1,
    title: "海のルールと安全を知る",
    body: "優先権・離岸流・リーシュなど、最初に知っておきたいことを5分で。ここだけは必ず。",
    links: [{ href: "/rules", label: "海に入る前に（ルール）" }],
  },
  {
    n: 2,
    title: "家で予習する（無料あり）",
    body: "パドリングやテイクオフの基本を動画で。第1回は無料で見られます。",
    links: [{ href: "/courses", label: "オンライン講座を見る" }],
  },
  {
    n: 3,
    title: "波と潮の見方を覚える",
    body: "「コシ〜ハラ」「オフショア」「大潮」など、波情報の読み方。初心者はヒザ〜コシ・風が弱い日が目安です。",
    links: [
      { href: "/waves", label: `今日の${SITE.region.beachShort}の波` },
      { href: "#words", label: "用語をチェック" },
    ],
  },
  {
    n: 4,
    title: "スクールで最初の一本を",
    body: "はじめての海は、プロと一緒がいちばん安全で上達も早い。ボードやウェットのレンタルもあります。",
    links: [{ href: "/instructors", label: "スクール・講師を選ぶ" }],
  },
  {
    n: 5,
    title: "行く準備をする",
    body: "レンタカー・宿・ごはんは周辺ガイドで。持ち物は「タオル・水・日焼け止め・着替え」があれば大丈夫。",
    links: [
      { href: "/partners", label: "周辺ガイド" },
      { href: "/faq", label: "よくある質問" },
    ],
  },
];

const WORDS: { w: string; d: string }[] = [
  { w: "ヒザ・コシ・ハラ・ムネ・カタ・アタマ", d: "波の大きさを体の高さで表す言い方。初心者はヒザ〜コシくらいが安心。" },
  { w: "オフショア", d: "陸から海へ吹く風。波の面が整い、乗りやすい。" },
  { w: "オンショア", d: "海から陸へ吹く風。波の面が乱れやすい。" },
  { w: "うねり・周期", d: "遠くから届く波のエネルギー。周期（秒）が長いほどパワーがあり、まとまった波になりやすい。" },
  { w: "満潮・干潮", d: "海面がいちばん高い／低い時刻。1日に2回ずつ来る。ポイントによって合う潮が違う。" },
  { w: "大潮・小潮", d: "月の満ち欠けで変わる潮の動き。大潮は干満の差が大きく、潮の流れも速い。" },
  { w: "離岸流", d: "岸から沖へ向かう強い流れ。流されたら岸と平行に泳いで抜ける。" },
  { w: "ピーク・優先権", d: "波が崩れ始める場所がピーク。ピークに一番近い人が優先。前乗りはしない。" },
  { w: "リーシュコード", d: "足とボードをつなぐ紐。ボードが流れて人に当たるのを防ぐ。必ず付ける。" },
];

export default function StartPage() {
  return (
    <SitePage title="はじめての方へ" lead="サーフィンをやってみたい人が、安心して最初の一本に乗るまでの5ステップ。">
      <ol className="space-y-3">
        {STEPS.map((s) => (
          <li key={s.n} className="flex gap-4 rounded-2xl border border-navy/10 bg-white p-5 shadow-sm">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ocean text-sm font-bold text-foam">
              {s.n}
            </span>
            <div>
              <p className="font-semibold text-navy">{s.title}</p>
              <p className="mt-1 text-sm leading-relaxed text-navy/70">{s.body}</p>
              <div className="mt-2 flex flex-wrap gap-2">
                {s.links.map((l) => (
                  <Link
                    key={l.href}
                    href={l.href}
                    className="rounded-full border border-ocean/30 px-3 py-1 text-xs font-medium text-ocean transition hover:bg-ocean/10"
                  >
                    {l.label} →
                  </Link>
                ))}
              </div>
            </div>
          </li>
        ))}
      </ol>

      <div id="words" className="scroll-mt-24">
        <Section heading="これだけ覚えればOK：波情報の用語">
          <dl className="mt-2 divide-y divide-navy/5 rounded-2xl border border-navy/10 bg-white">
            {WORDS.map((x) => (
              <div key={x.w} className="px-4 py-3">
                <dt className="text-sm font-semibold text-navy">{x.w}</dt>
                <dd className="mt-0.5 text-sm text-navy/70">{x.d}</dd>
              </div>
            ))}
          </dl>
        </Section>
      </div>

      <div className="mt-10 rounded-2xl bg-ocean-gradient p-6 text-center text-foam">
        <p className="font-semibold">わからないことは、AIの「なみすけ」に聞いてみよう</p>
        <p className="mt-1 text-sm text-sand/90">画面右下の「AIに聞く」から、波・潮・持ち物まで何でも相談できます。</p>
        <div className="mt-4 flex flex-wrap justify-center gap-2">
          <Link href="/signup" className="rounded-full bg-teal px-5 py-2 text-sm font-medium text-navy hover:bg-foam">
            仲間になる（無料）
          </Link>
          <Link href="/instructors" className="rounded-full border border-foam/40 px-5 py-2 text-sm text-foam hover:border-foam">
            スクールを見る
          </Link>
        </div>
      </div>
    </SitePage>
  );
}
