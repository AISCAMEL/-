export type Work = {
  slug: string;
  title: string;
  /** 事業グループ（services の group に対応） */
  category: "mobility" | "it" | "business";
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
    slug: "sample-carmel-buymo",
    title: "中古車のご提案（カーメル）と買取（BUYMO）での乗り換え",
    category: "mobility",
    categoryLabel: "自動車販売・買取",
    summary:
      "ご予算・ご用途をうかがい最適な中古車をご提案（カーメル）。今お乗りの車の買取（BUYMO）と合わせて、スムーズな乗り換えを実現。",
    result: "ご希望条件での乗り換えを実現（※サンプル）",
    isPlaceholder: true,
  },
  {
    slug: "sample-carshico-lease",
    title: "法人向けカーリース（CARSHICO）の導入",
    category: "mobility",
    categoryLabel: "カーリース",
    summary:
      "初期費用を抑えたい法人のお客様へ、月々定額のカーリース（CARSHICO）を導入。費用の見通しを立てやすく社用車を整備。",
    result: "初期費用を大幅に圧縮（※サンプル）",
    isPlaceholder: true,
  },
  {
    slug: "sample-apprex-app",
    title: "ノーコード（APPREX）でのアプリ開発・リリース",
    category: "it",
    categoryLabel: "IT事業（APPREX）",
    summary:
      "APPREXのノーコード開発で、企画からリリースまでをスピーディに対応。低コストでアプリを形にし、公開後の改善まで支援。",
    result: "短期間・低コストでリリース（※サンプル）",
    isPlaceholder: true,
  },
];
