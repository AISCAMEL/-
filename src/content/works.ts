export type Work = {
  slug: string;
  title: string;
  /** 業界・領域タグ（services のスラッグに対応） */
  category: "automotive" | "app" | "gps" | "fc";
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
    slug: "sample-automotive-sales",
    title: "ご予算に合わせた中古車のご提案と買取・乗り換え",
    category: "automotive",
    categoryLabel: "自動車事業",
    summary:
      "ご予算・ご用途をうかがい最適な中古車をご提案。今お乗りの車の買取と合わせて、スムーズな乗り換えを実現。",
    result: "ご希望条件での乗り換えを実現（※サンプル）",
    isPlaceholder: true,
  },
  {
    slug: "sample-automotive-lease",
    title: "法人向けカーリースの導入支援",
    category: "automotive",
    categoryLabel: "自動車事業",
    summary:
      "初期費用を抑えたい法人のお客様へ、メンテナンス込みのカーリースを導入。月々定額で社用車を整備。",
    result: "初期費用を大幅に圧縮（※サンプル）",
    isPlaceholder: true,
  },
  {
    slug: "sample-app-development",
    title: "自社サービスのアプリ開発・リリース",
    category: "app",
    categoryLabel: "アプリ事業",
    summary:
      "企画・UI設計から開発・リリースまでを一貫して対応。公開後の運用・改善まで継続的に支援。",
    result: "予定どおりリリースを実現（※サンプル）",
    isPlaceholder: true,
  },
];
