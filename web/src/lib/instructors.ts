export type InstructorRank = "pro" | "top_amateur" | "instructor";

export const RANK_META: Record<
  InstructorRank,
  { label: string; badge: string; note: string }
> = {
  pro: { label: "プロ", badge: "bg-teal/15 text-teal", note: "トッププロサーファー" },
  top_amateur: { label: "トップアマ", badge: "bg-ocean/15 text-ocean", note: "トップアマチュア" },
  instructor: { label: "インストラクター", badge: "bg-navy/10 text-navy/70", note: "認定インストラクター" },
};

export function rankLabel(rank: InstructorRank): string {
  return RANK_META[rank]?.label ?? rank;
}

/** 星表示（★の数を文字で） */
export function stars(avg: number): string {
  const full = Math.round(avg);
  return "★★★★★".slice(0, full) + "☆☆☆☆☆".slice(0, 5 - full);
}

export function priceLabelMonthly(price: number | null): string {
  if (price == null) return "応相談";
  return `月額 ¥${price.toLocaleString("ja-JP")}〜`;
}
