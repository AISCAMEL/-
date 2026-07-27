export type SkillCategory = "school" | "repair" | "photo" | "guide" | "other";

export const SKILL_CATEGORIES: { key: SkillCategory; label: string; hint: string }[] = [
  { key: "school", label: "スクール", hint: "サーフィンを教える" },
  { key: "repair", label: "リペア", hint: "ボード修理・メンテ" },
  { key: "photo", label: "フォト", hint: "サーフ写真・動画" },
  { key: "guide", label: "ガイド", hint: "海・スポット案内" },
  { key: "other", label: "その他", hint: "海にまつわるスキル" },
];

export const SKILL_CATEGORY_LABEL: Record<SkillCategory, string> = Object.fromEntries(
  SKILL_CATEGORIES.map((c) => [c.key, c.label]),
) as Record<SkillCategory, string>;

export function isSkillCategory(value: string): value is SkillCategory {
  return SKILL_CATEGORIES.some((c) => c.key === value);
}

/** 価格表示。MVP は未設定(null)が基本。即納・断定表現は避け、相談前提で見せる。 */
export function priceLabel(price: number | null): string {
  if (price == null) return "応相談";
  return `¥${price.toLocaleString("ja-JP")}〜`;
}
