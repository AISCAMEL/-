# AIS Web Studio — プロフェッショナルWeb制作チーム（Claude Code プラグイン）

一般的なテンプレートではなく、**デザイン性・操作性・マーケティング性能・技術品質**を兼ね備えたWebサイトを、
再現性高く制作するための「制作チーム」をまるごとパッケージ化したプラグインです。
一度入れれば、複数の企業サイト・LP・サービスサイト・SaaSサイト制作に再利用できます。

## 含まれるもの

### 専門エージェント（7体）— `agents/`
| エージェント | 役割 |
|---|---|
| art-director | コンセプト・世界観・ブランド設計 |
| web-designer | レイアウト・配色・タイポ・デザインシステム |
| ui-ux-designer | 情報設計・導線・CV・ワイヤー |
| frontend-developer | Next.js/React/Tailwind 実装・デプロイ |
| seo-specialist | 内部SEO・構造化データ・速度 |
| motion-designer | スクロール演出・アニメーション |
| qa-engineer | 実機検証・品質管理 |

### 専門スキル（10個）— `skills/`
web-art-director / frontend-design / ui-ux-design / responsive-design /
animation-design / seo-optimization / web-performance / accessibility /
conversion-optimization / quality-assurance

各スキルは **①作業手順 ②品質基準 ③禁止事項 ④確認チェックリスト** で構成。
共通の品質基準は `reference/DESIGN-STANDARDS.md` に集約し、重複を作りません。
コピー品質は公式 `stop-slop`、数値可視化は公式 `dataviz`、配色は公式 `theme-factory/artifact-design` を活用します。

### スラッシュコマンド — `commands/`
- `/web-new-site [案件概要]` … 14工程の標準ワークフローで新規制作
- `/web-review [URL/対象]` … 多観点レビューと優先度付き改善案

### 共通ドキュメント — `reference/`
- `DESIGN-STANDARDS.md` … 品質基準の単一ソース（10のゴールデンルール・DoD）
- `WORKFLOW.md` … 標準14工程と品質ゲート
- `TECH-STACK.md` … Next.js/TS/Tailwind/shadcn/Motion/Lucide/Vercel の規約

## 技術スタック（標準）
Next.js（App Router）＋ TypeScript ＋ Tailwind CSS ＋ shadcn/ui ＋ Motion ＋ Lucide Icons ＋ Vercel。
※静的HTMLサイトの制作・レビューにも、品質/SEO/QAスキルはそのまま使えます。

## インストール（他リポジトリでも再利用）

このリポジトリ（`AISCAMEL/-`）がマーケットプレースを兼ねています。

```
# ワンステップ（Claude Code 2.1.275 以降）
/plugin install ais-web-studio --marketplace AISCAMEL/-

# もしくは2ステップ
/plugin marketplace add AISCAMEL/-
/plugin install ais-web-studio
```

導入後、サブエージェントは自動で選択され、スキルは関連作業時に自動起動します。
明示起動したい場合はコマンド（`/web-new-site` 等）やスキル名で呼び出せます。

## 使い方の例
```
/web-new-site カーメルの新車販売LP。ターゲットは地方のファミリー層、CVは来店予約。
/web-review https://example.com/   ← 既存サイトの改善点を多観点で洗い出す
```

## 品質の約束（Definition of Done）
- 10のゴールデンルール適合／トークンで全ページ一貫
- PC・タブレット・スマホの実機スクショで崩れ0
- コントラストAA・キーボード操作・alt
- ビルド成功・リンク切れ0・フォーム正常
- Lighthouse（Performance/SEO/Best/Accessibility）≥ 90
