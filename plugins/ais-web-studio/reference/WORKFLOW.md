# 標準制作ワークフロー（14工程）

各工程には「担当エージェント・使用スキル・成果物・レビュー者」を定める。
`/web-new-site` コマンドはこの順で進行する。工程を飛ばさないこと。

| STEP | 工程 | 担当 | 使用スキル | 成果物 |
|---|---|---|---|---|
| 1 | 事業・サービス・ターゲット分析 | art-director | web-art-director | ヒアリング要約・ペルソナ・提供価値 |
| 2 | 競合・デザインリサーチ | art-director | web-art-director | 参照分析（模倣禁止）・差別化方針 |
| 3 | アートディレクション | art-director | web-art-director | コンセプト・世界観・ムードボード・原則 |
| 4 | サイトマップ・情報設計 | ui-ux-designer | ui-ux-design | サイトマップ・各ページ目的・導線 |
| 5 | ワイヤーフレーム | ui-ux-designer | ui-ux-design | 主要ページのワイヤー（PC/SP） |
| 6 | デザインシステム構築 | web-designer | frontend-design | トークン・コンポーネント・タイポ規定 |
| 7 | トップページ制作 | web-designer / frontend-developer | frontend-design, responsive-design | 実装済みトップ |
| 8 | 下層ページ制作 | frontend-developer | frontend-design | 各下層ページ |
| 9 | レスポンシブ対応 | frontend-developer | responsive-design | PC/タブレット/SP最適化 |
| 10 | アニメーション実装 | motion-designer | animation-design | 目的ある演出 |
| 11 | SEO・表示速度最適化 | seo-specialist | seo-optimization, web-performance | メタ/構造化/CWV改善 |
| 12 | 実機検証 | qa-engineer | quality-assurance, accessibility | PC/タブレット/SPスクショ・検証レポート |
| 13 | 修正・改善 | 各担当 | 該当スキル | 修正差分・再検証 |
| 14 | 本番公開 | frontend-developer | web-performance | Vercelデプロイ・公開後確認 |

## 進め方の原則

- **各工程の終わりに担当エージェントがレビュー**し、合格するまで次へ進めない。
- STEP7以降は毎回 **Playwright で PC(1280)/タブレット(768)/スマホ(390) のスクショ**を取り、目視で崩れ・階層・余白を確認する。
- 問題は「発見→自分で修正→再検証」までを1サイクルとして回す。
- ビルドエラー・リンク切れ・フォーム不具合・レスポンシブ崩れを**残さない**。

## レビュー・ゲート（通過条件）

1. **デザインゲート**（STEP3,6,7後）: DESIGN-STANDARDS 10ルール適合
2. **UXゲート**（STEP5,9後）: 導線・操作性・モバイルタップ基準
3. **技術ゲート**（STEP11,14前）: ビルド成功・CWV・リンク健全性
4. **品質ゲート**（STEP12）: 3デバイス実機スクショ・A11y・DoD全項目
