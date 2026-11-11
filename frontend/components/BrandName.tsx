'use client';

import { useBrand } from '@/lib/brand';

// 製品名をテーマから表示する（汎用＝AIオペレーター24 / 自動車＝カーコール24）。
export function BrandName() {
  return <>{useBrand().productName}</>;
}
