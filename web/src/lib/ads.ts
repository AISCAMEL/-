// ============================================================
// スポンサー広告枠（掲載面の一覧）
// ------------------------------------------------------------
// ad_banners.placement に入る値と、その枠が出るページ・推奨サイズ。
// 新しい枠を増やすときは、ここに足して <AdBanner placement="..."> を置く。
// ============================================================

export type AdPlacement =
  | "top"
  | "waves"
  | "spots"
  | "spot"
  | "area"
  | "partners"
  | "feed"
  | "blog"
  | "footer";

export type AdSlot = {
  key: AdPlacement;
  label: string;
  where: string;
  /** 推奨画像サイズ（px） */
  size: string;
  note: string;
};

/** 横長バナーの比率（1600×680 ≒ 40:17） */
export const AD_ASPECT = "aspect-[40/17]";

export const AD_SLOTS: AdSlot[] = [
  { key: "top", label: "トップページ", where: "トップページ中段", size: "1600×680", note: "サイトの顔。いちばん多くの人の目に入る枠" },
  { key: "waves", label: "波情報（岩沢）", where: "岩沢海岸の波情報ページ", size: "1600×680", note: "毎日チェックする常連サーファー向け" },
  { key: "spots", label: "全国ポイント一覧", where: "全国サーフポイントガイドの一覧", size: "1600×680", note: "全国のサーファーが旅先を探すページ" },
  { key: "spot", label: "ポイント詳細", where: "各サーフポイントのページ（全50）", size: "1600×680", note: "波予報・潮回りと一緒に見られる枠" },
  { key: "area", label: "エリア詳細", where: "各エリアのページ（全26）", size: "1600×680", note: "エリアの旅行・宿泊・ショップ向け" },
  { key: "partners", label: "周辺ガイド", where: "岩沢海岸周辺のレンタカー・宿・食事のページ", size: "1600×680", note: "来訪前の人が見る地域ガイド" },
  { key: "feed", label: "コミュニティ", where: "コミュニティのフィード", size: "1600×680", note: "地元・常連メンバー向け" },
  { key: "blog", label: "ブログ", where: "プロのブログ一覧", size: "1600×680", note: "読み物を楽しむ人向け" },
  { key: "footer", label: "スポンサー（全ページ）", where: "全ページ下部のスポンサー欄（最大4社）", size: "1600×680", note: "サイト全体で表示される協賛枠" },
];

export const AD_PLACEMENT_LABEL: Record<string, string> = {
  ...Object.fromEntries(AD_SLOTS.map((s) => [s.key, s.label])),
  all: "全体（すべての枠）",
};

export function isAdPlacement(v: string): v is AdPlacement | "all" {
  return v === "all" || AD_SLOTS.some((s) => s.key === v);
}
