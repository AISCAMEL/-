---
name: animation-design
description: スクロール演出・入場アニメ・インタラクションを設計/実装するとき。Motion（Framer Motion）で「目的のある動き」を付け、性能とアクセシビリティを守るのに使う。
---

# アニメーション設計（Motion）

共通基準は `../../reference/DESIGN-STANDARDS.md`（ルール8）。性能は `web-performance` と併用。

## 作業手順
1. 各アニメに**目的を1つ**割り当てる（視線誘導/状態提示/階層強調/文脈の連続性）。目的がなければ付けない。
2. Motion で実装。入場は 150–400ms・ease-out、距離は小さく（8–24px / opacity）。
3. スクロール連動は `whileInView` + `viewport={{ once: true }}` を基本に、画面内1回のみ。
4. `prefers-reduced-motion` を尊重し、無効時は即時表示にフォールバック。
5. transform/opacity 中心で実装し、レイアウトを動かさない（CLS回避）。
6. スマホ実機でカクつき・発熱・CLSを確認。

## 品質基準
- 60fps 維持、CLS を悪化させない。
- 「なくても成立するが、あると理解しやすい」水準に収める。

## 禁止事項
- 装飾だけの動き・過剰パララックス・無限ループの派手な動き。
- 自動再生の激しい動き・点滅（光過敏に配慮）。
- reduced-motion 無視。

## 確認チェックリスト
- [ ] 各アニメに目的がある
- [ ] 入場時間/距離が基準内・once運用
- [ ] reduced-motion 対応・CLS悪化なし
- [ ] スマホ実機でなめらか
