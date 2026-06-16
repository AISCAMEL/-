import type { SupplierProduct } from "../domain/product.js";

export interface ComplianceIssue {
  code: string;
  message: string;
  severity: "block" | "warn";
}

/** 出品をブロックすべきキーワード（簡易辞書・運用で拡充する）。 */
const BLOCKED_KEYWORDS: { keyword: string; code: string; message: string }[] = [
  { keyword: "ブランド", code: "brand_risk", message: "ブランド品の疑い。真贋・許諾を確認" },
  { keyword: "偽", code: "counterfeit", message: "偽造品の疑い" },
  { keyword: "医薬", code: "pharma", message: "医薬品は輸入規制対象" },
  { keyword: "電池", code: "battery", message: "電池単体は輸送・PSE 規制に注意" },
  { keyword: "技適", code: "giteki", message: "技適未取得の無線機器の可能性" },
];

const WARN_KEYWORDS: { keyword: string; code: string; message: string }[] = [
  { keyword: "食品", code: "food", message: "食品は検疫・表示規制対象" },
  { keyword: "化粧品", code: "cosmetics", message: "化粧品は薬機法の対象" },
];

/**
 * 出品前の規約・法令チェック。block が1件でもあれば出品不可。
 * 本実装は骨組み。運用ではブランド辞書・カテゴリ判定で強化する。
 */
export function validateForListing(product: SupplierProduct): ComplianceIssue[] {
  const haystack = `${product.title} ${product.description ?? ""}`;
  const issues: ComplianceIssue[] = [];

  for (const { keyword, code, message } of BLOCKED_KEYWORDS) {
    if (haystack.includes(keyword)) {
      issues.push({ code, message, severity: "block" });
    }
  }
  for (const { keyword, code, message } of WARN_KEYWORDS) {
    if (haystack.includes(keyword)) {
      issues.push({ code, message, severity: "warn" });
    }
  }
  if (product.imageUrls.length === 0) {
    issues.push({ code: "no_image", message: "画像が無い商品は出品不可", severity: "block" });
  }
  return issues;
}

export const canPublish = (issues: ComplianceIssue[]): boolean =>
  !issues.some((i) => i.severity === "block");
