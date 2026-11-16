---
name: web-performance
description: 表示速度とCore Web Vitals（LCP/CLS/INP）を最適化するとき。画像/フォント/バンドル/レンダリング戦略の改善や、Lighthouse計測・改善に使う。
---

# Web パフォーマンス最適化

共通基準は `../../reference/DESIGN-STANDARDS.md`、構成は `../../reference/TECH-STACK.md`。

## 作業手順
1. **計測が先**: Lighthouse / CWV を取り、ボトルネックを特定してから直す。
2. **画像**: `next/image`＋WebP/AVIF、適切な sizes、LCP画像は `priority`、遅延読込。
3. **フォント**: `next/font` でセルフホスト＋`display:swap`、字体サブセット。
4. **JS**: 不要依存を削る、動的import、クライアントコンポーネント最小化（RSC活用）。
5. **CLS対策**: 画像/広告/埋め込みに寸法指定、Webフォントのレイアウトシフト抑制。
6. **キャッシュ/配信**: 静的化(SSG/ISR)・適切なCache-Control・CDN(Vercel)。
7. 改善後に再計測し、効果を確認。

## 品質基準（目標値）
- LCP < 2.5s / CLS < 0.1 / INP < 200ms（モバイル実機相当）。
- Lighthouse Performance ≥ 90。初期JSは必要最小限。

## 禁止事項
- 計測なしの当て推量チューニング。
- 巨大ライブラリの安易な導入・全ページ client component 化。
- 寸法未指定の画像/埋め込み（CLSの温床）。

## 確認チェックリスト
- [ ] LCP/CLS/INP が目標内
- [ ] 画像/フォント/バンドル最適化済み
- [ ] Lighthouse Performance ≥ 90
- [ ] 改善前後の数値を記録
