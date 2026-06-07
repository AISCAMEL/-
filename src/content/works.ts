export type Work = {
  slug: string;
  title: string;
  /** 業界・領域タグ */
  category: "automotive" | "startup" | "web-development";
  categoryLabel: string;
  /** 一覧カードの要約 */
  summary: string;
  /** 主な成果（数値はダミー） */
  result: string;
  /** ダミーデータかどうか（true の間はUI上に「サンプル」と明示） */
  isPlaceholder: boolean;
};

/**
 * 注意: 以下はすべて構成確認用のダミー（placeholder）データです。
 * 実績が確定したら isPlaceholder を false にし、内容を差し替えてください。
 * 構造はそのままに、CMS から配列を流し込めるよう設計しています。
 */
export const works: Work[] = [
  {
    slug: "sample-automotive-dx",
    title: "中古車販売店のDX化と在庫連動サイト構築",
    category: "automotive",
    categoryLabel: "自動車業界支援",
    summary:
      "紙・電話中心だった商談・在庫管理をデジタル化し、在庫連動のWebサイトと問い合わせ導線を整備。",
    result: "問い合わせ数 約2.0倍（※サンプル数値）",
    isPlaceholder: true,
  },
  {
    slug: "sample-startup-funding",
    title: "新規開業の事業計画策定と資金調達支援",
    category: "startup",
    categoryLabel: "創業・起業支援",
    summary:
      "事業計画の策定から資金調達の準備、補助金活用の検討までを一貫して支援し、スムーズな開業を実現。",
    result: "開業準備期間を短縮（※サンプル）",
    isPlaceholder: true,
  },
  {
    slug: "sample-web-renewal",
    title: "BtoB企業のサイトリニューアルと集客強化",
    category: "web-development",
    categoryLabel: "Web・開発支援",
    summary:
      "事業内容が伝わるサイトへ全面リニューアル。SEOと広告を組み合わせ、問い合わせ獲得の導線を最適化。",
    result: "オーガニック流入 約1.8倍（※サンプル数値）",
    isPlaceholder: true,
  },
];
