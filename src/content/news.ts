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
      "自動車業界支援・創業支援・Web/開発支援の3領域を分かりやすく整理し、ご相談いただきやすい構成へと刷新しています。",
      "（本記事はサンプルです。実際のお知らせに差し替えてください。）",
    ],
    isPlaceholder: true,
  },
  {
    slug: "sample-column-automotive-dx",
    title: "【コラム】自動車販売店がDXで最初に取り組むべきこと",
    date: "2026-05-20",
    category: "コラム",
    excerpt:
      "自動車販売の現場でDXを進める際、最初の一歩として効果が出やすい取り組みを整理します。",
    body: [
      "「DXを進めたいが何から手をつければいいか分からない」という声を多くいただきます。",
      "まずは見込み客・在庫・顧客情報の一元化から始めるのが効果的です。（本記事はサンプルです。）",
    ],
    isPlaceholder: true,
  },
  {
    slug: "sample-column-subsidy",
    title: "【コラム】創業時に活用を検討したい補助金・支援制度",
    date: "2026-04-15",
    category: "コラム",
    excerpt:
      "創業・開業のタイミングで検討したい補助金や支援制度の考え方を解説します。",
    body: [
      "創業時には、さまざまな補助金・助成金や支援制度を活用できる可能性があります。",
      "制度は年度や条件で変わるため、最新情報の確認が重要です。（本記事はサンプルです。）",
    ],
    isPlaceholder: true,
  },
];

export function getNews(slug: string): NewsItem | undefined {
  return news.find((n) => n.slug === slug);
}
