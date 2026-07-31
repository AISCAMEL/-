// ============================================================
// 地域タイアップ（周辺スポット）
// ------------------------------------------------------------
// 海の一日を「まちごと」楽しむための提携事業者ディレクトリ。
// レンタカー・宿泊・食べる の3カテゴリ。掲載は無料で、事業者向け
// ログイン（/partners/business）から申込・管理できる（デモは受付のみ）。
// 商品(shop)と同じく、将来 Supabase の partners テーブルに載せ替える前提で
// カテゴリ定義とラベルをここに一元化する。
// ============================================================

export type PartnerCategory = "rentalcar" | "stay" | "eat";

export const PARTNER_CATEGORIES: { key: PartnerCategory; label: string; emoji: string }[] = [
  { key: "rentalcar", label: "レンタカー", emoji: "🚗" },
  { key: "stay", label: "宿泊", emoji: "🏨" },
  { key: "eat", label: "食べる", emoji: "🍽" },
];

export const PARTNER_CATEGORY_LABEL: Record<PartnerCategory, string> = Object.fromEntries(
  PARTNER_CATEGORIES.map((c) => [c.key, c.label]),
) as Record<PartnerCategory, string>;

export const PARTNER_CATEGORY_EMOJI: Record<PartnerCategory, string> = Object.fromEntries(
  PARTNER_CATEGORIES.map((c) => [c.key, c.emoji]),
) as Record<PartnerCategory, string>;

/** カテゴリごとの帯グラデ（画像未設定時のプレースホルダ背景） */
export const PARTNER_CATEGORY_GRADIENT: Record<PartnerCategory, string> = {
  rentalcar: "linear-gradient(150deg, #0a5f9c 0%, #1a8fcb 55%, #1fc0b0 100%)",
  stay: "linear-gradient(150deg, #0b2540 0%, #0e6fae 60%, #14c6a6 100%)",
  eat: "linear-gradient(150deg, #0e8ac7 0%, #24b3c9 55%, #f2e7d2 130%)",
};

export function isPartnerCategory(v: string): v is PartnerCategory {
  return PARTNER_CATEGORIES.some((c) => c.key === v);
}
