---
name: seo-optimization
description: 内部SEO・メタ情報・OGP・構造化データ(JSON-LD)・サイトマップ・内部リンク・見出し階層を最適化するとき。検索流入とクロール品質を高めるのに使う。
---

# SEO 最適化（内部・構造化）

共通基準は `../../reference/DESIGN-STANDARDS.md`、実装は `../../reference/TECH-STACK.md`（Next Metadata API）。速度は `web-performance` と併用。

## 作業手順
1. **メタ**: 各ページに固有の title（32字前後）/ description（120字前後）/ canonical を設定。
2. **OGP/Twitter**: og:title/description/image(1200×630)/url、twitter:card を付与。
3. **構造化データ(JSON-LD)**: 事業に応じ Organization / LocalBusiness / Product / Article / FAQ / BreadcrumbList 等を正確に。
4. **情報構造**: 1ページ1 h1、見出しは階層順(h1→h2→h3)、画像alt、パンくず。
5. **クロール**: `sitemap.xml`・`robots.txt`・内部リンク（関連ページ相互リンク）を整備。
6. 構造化データは Rich Results の観点で検証し、Lighthouse SEO を計測。

## 品質基準
- 重複title/descriptionなし、1ページ1 h1、構造化データのエラー0。
- Lighthouse SEO ≥ 95。モバイルフレンドリー。

## 禁止事項
- キーワード詰め込み・隠しテキスト・クローキング。
- 実体と異なる構造化データ（虚偽のreview/ratingなど）。
- 内容の薄い自動生成ページの量産。

## 確認チェックリスト
- [ ] 全ページ固有メタ＋OGP＋canonical
- [ ] JSON-LD が妥当（検証エラー0）
- [ ] h1単一・見出し階層・alt・パンくず
- [ ] sitemap/robots/内部リンク整備・Lighthouse SEO≥95
