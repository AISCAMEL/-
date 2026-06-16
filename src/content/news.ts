export type NewsCategory = "お知らせ" | "コラム" | "プレスリリース";

export type NewsItem = {
  slug: string;
  title: string;
  date: string; // YYYY-MM-DD
  category: NewsCategory;
  excerpt: string;
  /** 本文（簡易。将来はCMS/MDXへ移行） */
  body: string[];
  isPlaceholder: boolean;
};

/**
 * 注意: 以下は構成確認用のダミー（placeholder）データです。
 * 実運用時は CMS から取得、または記事を追記してください。
 */
export const news: NewsItem[] = [
  {
    slug: "sample-website-renewal",
    title: "コーポレートサイトをリニューアルしました",
    date: "2026-06-07",
    category: "お知らせ",
    excerpt:
      "事業内容がより分かりやすく伝わるよう、コーポレートサイトを全面リニューアルしました。",
    body: [
      "この度、合同会社アイズのコーポレートサイトを全面リニューアルいたしました。",
      "自動車販売「カーメル」・買取「BUYMO」・オンライン車販売「CARSHICO」・車両セキュリティ「天護」・レッカー、IT事業「APPREX」、サブスクWeb制作「WEB crews」、FC事業を分かりやすく整理し、ご相談いただきやすい構成へと刷新しています。",
      "（本記事はサンプルです。実際のお知らせに差し替えてください。）",
    ],
    isPlaceholder: true,
  },
  {
    slug: "sample-column-online-car-buying",
    title: "【コラム】クルマをオンラインで買うという選択",
    date: "2026-05-20",
    category: "コラム",
    excerpt:
      "新車をオンラインで注文して自宅で受け取る、新しいクルマの買い方とそのメリットを整理します。",
    body: [
      "「店舗に行く時間がない」「近くに販売店がない」といった理由から、オンラインでのクルマ購入に関心を持つ方が増えています。",
      "オンライン車販売（CARSHICO）なら、新車の注文から手続きまでをオンラインで完結し、ご自宅まで納車できます。（本記事はサンプルです。）",
    ],
    isPlaceholder: true,
  },
  {
    slug: "sample-column-car-buyback",
    title: "【コラム】愛車を少しでも高く売るためのポイント",
    date: "2026-04-15",
    category: "コラム",
    excerpt:
      "クルマの買取査定で、少しでも高く売るために知っておきたいポイントを解説します。",
    body: [
      "買取査定の金額は、車両の状態や時期、需要によって変わります。",
      "売却のタイミングや書類の準備など、事前にできることがあります。（本記事はサンプルです。）",
    ],
    isPlaceholder: true,
  },
];

export function getNews(slug: string): NewsItem | undefined {
  return news.find((n) => n.slug === slug);
}
