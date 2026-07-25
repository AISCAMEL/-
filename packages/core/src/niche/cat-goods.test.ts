import { describe, expect, it } from "vitest";
import { CAT_GOODS, allKeywords } from "./cat-goods.js";

describe("CAT_GOODS preset", () => {
  it("代表キーワードとサブカテゴリを持つ", () => {
    expect(CAT_GOODS.id).toBe("cat-goods");
    expect(CAT_GOODS.searchKeywords.length).toBeGreaterThan(0);
    expect(CAT_GOODS.subCategories.length).toBeGreaterThan(0);
  });

  it("ペットフード・サプリは block 指定", () => {
    const blocks = CAT_GOODS.complianceNotes.filter((n) => n.severity === "block");
    expect(blocks.some((b) => b.topic.includes("ペットフード"))).toBe(true);
    expect(blocks.some((b) => b.topic.includes("サプリ"))).toBe(true);
  });

  it("allKeywords は重複を除去して全キーワードを返す", () => {
    const all = allKeywords(CAT_GOODS);
    expect(new Set(all).size).toBe(all.length);
    expect(all).toContain("キャットタワー");
    expect(all.length).toBeGreaterThan(CAT_GOODS.searchKeywords.length);
  });
});
