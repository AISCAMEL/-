'use client';

import { useEffect, useState } from 'react';

// ブランドテーマ（オールマイティー＝汎用 / automotive＝自動車専用）。
// 実際の配色は globals.css の CSS変数（--brand 等）を data-brand で切替。
// ここでは製品名・キャッチコピー・初期テンプレ範囲など「文言/設定」を持つ。
export type BrandKey = 'allmighty' | 'automotive';

export interface Brand {
  key: BrandKey;
  productName: string;
  tagline: string;
  // 新規店舗オンボーディングで初期表示する業種テンプレの範囲（空＝全業種）
  defaultTemplateKeys: string[];
}

export const BRANDS: Record<BrandKey, Brand> = {
  allmighty: {
    key: 'allmighty',
    productName: 'AIオペレーター24',
    tagline: 'どんな業種でも、電話をAIが受付。',
    defaultTemplateKeys: [], // 全業種
  },
  automotive: {
    key: 'automotive',
    productName: 'カーコール24',
    tagline: '自動車業界専門のAI電話受付。',
    defaultTemplateKeys: ['car_sales', 'car_maintenance', 'car_bodyshop', 'car_buyout', 'car_dealer'],
  },
};

export function brandFromKey(key: string | undefined | null): Brand {
  return key === 'automotive' ? BRANDS.automotive : BRANDS.allmighty;
}

// クライアントで現在のブランドキーを返す。
// 判定は head の先行スクリプトが data-brand に反映済み。ここはそれを読むだけ。
// SSR/初回描画は allmighty を返し（サーバーと一致）、マウント後に実値へ。
export function useBrandKey(): BrandKey {
  const [key, setKey] = useState<BrandKey>('allmighty');
  useEffect(() => {
    const k = document.documentElement.getAttribute('data-brand');
    if (k === 'automotive') setKey('automotive');
  }, []);
  return key;
}

export function useBrand(): Brand {
  return brandFromKey(useBrandKey());
}
