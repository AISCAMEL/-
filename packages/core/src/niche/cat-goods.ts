import type { ScoreResult } from "../research/screening.js";

/** 特定ジャンルに特化した運用プリセット。 */
export interface NichePreset {
  id: string;
  name: string;
  /** 市場調査・仕入れ検索に使う代表キーワード。 */
  searchKeywords: string[];
  /** サブカテゴリ別のキーワード。 */
  subCategories: { name: string; keywords: string[] }[];
  /** スクリーニング推奨設定。 */
  screening: { minMarginRate: number; minGrade: ScoreResult["grade"] };
  /** ジャンル特有の規約・法令の注意点。 */
  complianceNotes: { topic: string; note: string; severity: "block" | "warn" }[];
}

/**
 * 猫グッズ特化プリセット。
 * 中国輸入×無在庫で扱いやすく、規制が軽めの雑貨・おもちゃを中心に構成。
 * ペットフード・サプリ・電気製品は規制が重いため warn/block で明示する。
 */
export const CAT_GOODS: NichePreset = {
  id: "cat-goods",
  name: "猫グッズ",
  searchKeywords: [
    "猫 おもちゃ",
    "猫 爪とぎ",
    "キャットタワー",
    "猫 ベッド",
    "猫 首輪",
    "猫 トイレ",
    "猫 食器",
    "猫 ブラシ",
    "猫 トンネル",
    "猫 雑貨",
  ],
  subCategories: [
    { name: "おもちゃ", keywords: ["猫 おもちゃ", "猫じゃらし", "けりぐるみ", "電動 おもちゃ 猫", "ボール 猫"] },
    { name: "爪とぎ・タワー", keywords: ["猫 爪とぎ", "キャットタワー", "キャットウォーク", "爪とぎ ダンボール"] },
    { name: "ベッド・ハウス", keywords: ["猫 ベッド", "猫 ハウス", "猫 ハンモック", "猫 クッション"] },
    { name: "首輪・ハーネス", keywords: ["猫 首輪", "猫 ハーネス", "猫 リード", "迷子札"] },
    { name: "トイレ・猫砂まわり", keywords: ["猫 トイレ", "猫 トイレ マット", "猫砂 スコップ", "システムトイレ"] },
    { name: "食器・給餌", keywords: ["猫 食器", "猫 給水器", "自動給餌器", "早食い防止 食器"] },
    { name: "グルーミング", keywords: ["猫 ブラシ", "猫 爪切り", "抜け毛 取り", "猫 歯ブラシ"] },
    { name: "雑貨・アパレル", keywords: ["猫 雑貨", "猫 モチーフ グッズ", "猫 アクセサリー", "猫 エコバッグ"] },
  ],
  screening: { minMarginRate: 0.3, minGrade: "B" },
  complianceNotes: [
    {
      topic: "ペットフード・おやつ・猫草",
      note: "ペットフード安全法の対象。輸入時の届出・成分表示が必要。無在庫での取扱いは慎重に",
      severity: "block",
    },
    {
      topic: "サプリ・動物用医薬品・ノミダニ駆除",
      note: "動物用医薬品・医薬部外品は輸入規制対象。原則扱わない",
      severity: "block",
    },
    {
      topic: "電気製品（自動給餌器・自動トイレ・ヒーター・こたつ）",
      note: "PSE 適合が必要な場合あり。無認証品の出品は不可",
      severity: "warn",
    },
    {
      topic: "おもちゃの小型部品・塗料",
      note: "誤飲リスクや有害物質（鉛等）に注意。品質確認を推奨",
      severity: "warn",
    },
    {
      topic: "またたび・キャットニップ",
      note: "通常は雑貨として可。過剰摂取注意の表記を推奨",
      severity: "warn",
    },
  ],
};

/** プリセットの全キーワード（重複除去）を返す。 */
export function allKeywords(preset: NichePreset): string[] {
  const set = new Set<string>(preset.searchKeywords);
  for (const sub of preset.subCategories) {
    for (const kw of sub.keywords) set.add(kw);
  }
  return [...set];
}
