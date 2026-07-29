import type { Metadata } from "next";
import { SitePage } from "@/components/site-page";

export const metadata: Metadata = {
  title: "よくある質問",
};

const FAQS: { q: string; a: string }[] = [
  {
    q: "まったくの初心者ですが大丈夫ですか？",
    a: "はい。岩沢海岸はメローな波が多く、初心者に向いています。プロ・インストラクターが、陸での基礎から海の入り方まで丁寧にサポートします。",
  },
  {
    q: "登録にお金はかかりますか？",
    a: "登録は無料です。コミュニティ・波情報・スキルの学び合いも無料でご利用いただけます。プロの受講（スクール）のみ月額制です。",
  },
  {
    q: "料金はどうやって支払いますか？",
    a: "スクールの受講は月額サブスクリプションです。決済は決済代行会社を通じて行い、カード情報は当サービスで保持しません（決済機能は順次導入予定）。",
  },
  {
    q: "道具を持っていません。",
    a: "ギアレンタル（ボード・ウェット・小物）があります。受講プランにレンタルが含まれる講師もいます。手ぶらで海に来られます。",
  },
  {
    q: "女性一人でも参加しやすいですか？",
    a: "女性・初心者に人気の講師も在籍しています。各講師のプロフィールと口コミを見て、安心できる相手を選べます。",
  },
  {
    q: "海が怖いのですが。",
    a: "その感覚はとても大切です。安全第一で、膝〜腰の深さから、無理のないペースで進めます。プロのブログでも安全の心得を発信しています。",
  },
  {
    q: "県外からでも行けますか？",
    a: "広野IC・最寄り駅からの移動サポートがあります。事前にオンラインで学べるので、来訪前の不安も減らせます。",
  },
  {
    q: "キャンセルはできますか？",
    a: "スクールの月額はマイページからいつでも解約できます。詳細は各プランの表示と利用規約をご確認ください。",
  },
];

export default function FaqPage() {
  return (
    <SitePage title="よくある質問" lead="初めての方から多くいただく質問をまとめました。">
      <div className="space-y-3">
        {FAQS.map((f, i) => (
          <details
            key={i}
            className="group rounded-2xl border border-navy/10 bg-white p-5 [&_summary::-webkit-details-marker]:hidden"
          >
            <summary className="flex cursor-pointer items-center justify-between font-medium text-navy">
              <span className="pr-4">{f.q}</span>
              <span className="text-ocean transition group-open:rotate-45">＋</span>
            </summary>
            <p className="mt-3 text-sm leading-relaxed text-navy/70">{f.a}</p>
          </details>
        ))}
      </div>
      <p className="mt-8 text-sm text-navy/60">
        解決しない場合は{" "}
        <a href="/contact" className="font-medium text-ocean hover:underline">
          お問い合わせ
        </a>
        からご連絡ください。
      </p>
    </SitePage>
  );
}
