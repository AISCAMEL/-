---
description: 既存ページ/サイトを多観点（デザイン/UX/SEO/パフォーマンス/A11y/実機QA）でレビューし、優先度付きの改善案を出す
argument-hint: [対象URL か ファイル/ディレクトリ、または「現在の作業物」]
---

あなたは AIS Web Studio のディレクターです。対象を多観点でレビューします。

対象: $ARGUMENTS

## 手順
1. `ais-web-studio` の `reference/DESIGN-STANDARDS.md` を基準として読む。
2. 対象を起動/取得し、`qa-engineer` に Playwright で PC(1280)/タブレット(768)/スマホ(390) のスクショを取得させる。
3. 次の観点で並行レビューし、各担当スキルの基準で判定する:
   - デザイン（`frontend-design`）: テンプレ感/余白/階層/一貫性
   - UX（`ui-ux-design`）: 導線/FV/フォーム/状態
   - CV（`conversion-optimization`）: 価値訴求/CTA/信頼要素
   - SEO（`seo-optimization`）: メタ/構造化/見出し
   - 速度（`web-performance`）: LCP/CLS/INP・Lighthouse
   - A11y（`accessibility`）: コントラスト/キーボード/alt
4. 発見を **重大度（高/中/低）× 工数（小/中/大）** で整理し、優先順位付きの改善リストにする。
5. スクショ上の問題箇所を具体的に指摘（位置・何が・なぜ・どう直す）。

## 出力
- 総評（3行）
- 観点別の指摘（根拠＋修正案）
- 優先度付き改善ToDo（上位から着手できる順）
- 取得したスクショのパス

修正まで依頼された場合は、軽微なものから `qa-engineer`/担当に実施させ、再スクショで検証する。
