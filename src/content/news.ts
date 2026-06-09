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
      "自動車事業（販売・買取・リース）を主力に、アプリ事業・GPS事業・FC事業を分かりやすく整理し、ご相談いただきやすい構成へと刷新しています。",
      "（本記事はサンプルです。実際のお知らせに差し替えてください。）",
    ],
    isPlaceholder: true,
  },
  {
    slug: "sample-column-buy-or-lease",
    title: "【コラム】クルマは「購入」と「リース」どちらがお得？",
    date: "2026-05-20",
    category: "コラム",
    excerpt:
      "クルマを導入する際の「購入」と「カーリース」の違いと、選び方の考え方を整理します。",
    body: [
      "「クルマは買うべきか、リースにすべきか」というご相談を多くいただきます。",
      "初期費用・維持の手間・利用年数などをふまえて選ぶのがポイントです。（本記事はサンプルです。）",
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
