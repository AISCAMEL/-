---
name: quality-assurance
description: 公開前の品質検証を行うとき。ブラウザ実機でPC/タブレット/スマホのスクショを取得し、表示崩れ・リンク切れ・フォーム不具合・レスポンシブ崩れ・ビルドエラーを検出して潰すのに使う。
---

# 品質保証（実機検証）

判定基準は `../../reference/DESIGN-STANDARDS.md`（§8 Definition of Done）と `../../reference/WORKFLOW.md`（品質ゲート）。
`accessibility`・`web-performance` と併用する。

## 作業手順（Playwright 実機スクショ）
1. ローカル/プレビューを起動（例 `npm run dev` or `python3 -m http.server`）。
2. 同梱 Chromium で各デバイスのフルページスクショを取得:
   ```js
   const { chromium } = require('playwright');
   const sizes = [['pc',1280,900],['tablet',768,1024],['sp',390,844]];
   // 各 size で page.setViewportSize → fullPage スクショを保存（deviceScaleFactor:2）
   ```
3. スクショを**目視**し、崩れ・はみ出し・余白不整合・階層の弱さ・画像欠落を洗い出す。
4. 機能検証: 全リンク到達（リンク切れ0）、フォーム送信/バリデーション/エラー表示、ナビ/ハンバーガー動作。
5. 横断検証: キーボード操作、コントラスト、alt、`npm run build`（型/ESLint/ビルド）。
6. **軽微な崩れは自分で修正→再スクショ**。構造的問題は担当エージェントへ差し戻し。
7. 検証レポート（項目/結果/残課題/スクショパス）を出力。

## 品質基準（DoD）
- 3デバイスで崩れ0・横スクロール0・リンク切れ0・フォーム正常。
- コントラストAA・キーボード操作可・alt付与。
- build 成功・型/ESLint 0・Lighthouse 各90以上。

## 禁止事項
- スクショ未取得での「OK」判定。
- 既知の崩れ/リンク切れを残したままの合格。
- DoD未達での公開許可。

## 確認チェックリスト
- [ ] PC/タブレット/スマホ スクショ取得・目視済み
- [ ] リンク切れ0・フォーム正常
- [ ] A11y（コントラスト/キーボード/alt）OK
- [ ] build 成功・型/lint 0・Lighthouse≥90
- [ ] 残課題と対応状況を明記
