# 合同会社アイズ — Web サイト & 業務システム

静的サイト（HTML/CSS/JS）＋ Google Apps Script（GAS）バックエンドで構成された、
**2ブランド**のサイトと業務システムのモノレポです。

| ブランド | 内容 | 入口 |
|---|---|---|
| **BUYMO** | 車買取サービス（集客LP・SEO・査定シミュ・問い合わせ／本部・加盟店システム・アカデミー） | `site/buymo.html` |
| **AUC-AGENT** | オークション代行（購入/出品代行・各種シミュレーター・会員/マイページ） | `site/index.html` |

会社情報：合同会社アイズ／福島県いわき市四倉町細谷字大町1番／古物商 第25121A010859号／info@aisjaltd.com

---

## ディレクトリ構成

```
site/            公開する静的サイト（このディレクトリを丸ごとホスティング）
  assets/        css / js / img
  genre/         BUYMO 買取ジャンル（ハブ＋25ジャンルLP＋ジャンル×エリア掛け合わせLP）
  area/          BUYMO 都道府県別SEO LP（ハブ＋47県）
  tools/         ページ生成・QA スクリプト（Node）
  tests/         スモークテスト（Playwright）/ verify（jsdom）
gas/             GAS バックエンド（doPost/doGet・通知・ステップメール・認証 等）
docs/            設計・運用ドキュメント、ワイヤー、料金表 等
netlify.toml     デプロイ設定（publish = site）
```

---

## ローカルでプレビュー

静的サイトなのでビルド不要。

```bash
cd site
python3 -m http.server 8000
# BUYMO:     http://localhost:8000/buymo.html
# AUC-AGENT: http://localhost:8000/index.html
```

---

## ページ生成（BUYMO のジャンル/エリア）

ジャンルは `site/assets/js/genres.js`、掛け合わせ対象は `site/tools/_cross.js` が唯一のデータソース。
編集したら再生成します。

```bash
cd site
node tools/gen-genre.js   # ジャンルハブ＋25ジャンルLP＋掛け合わせLP
node tools/gen-area.js    # 47都道府県LP＋ハブ＋sitemap.xml＋robots.txt
```

公開ドメイン確定後は `SITE_URL` を渡すと canonical / sitemap / robots が絶対URLになります。

```bash
SITE_URL=https://（本番ドメイン） node tools/gen-genre.js && node tools/gen-area.js
```

---

## QA（品質チェック）

```bash
cd site
node tools/check-links.js   # 内部リンク／アセット切れ
node tools/seo-check.js     # JSON-LD・title重複・canonical・OGP・データ整合
node tools/launch-check.js  # 公開準備の残り（ENDPOINT/GA4/SITE_URL/画像）を一覧
```

実機回帰（主要28ページをブラウザで開いてエラー0＋要素存在を検証）：

```bash
cd site/tests
npm install && npx playwright install chromium
node smoke.js
```

> `check-links` / `seo-check` / `smoke` は GitHub Actions（`.github/workflows/qa.yml`）で push 毎に自動実行されます。

---

## デプロイ

- 静的ホスティング（Netlify / Cloudflare Pages / GitHub Pages 等）に **`site/` を公開**（`netlify.toml`：publish = site）。
- 申込・業務データの自動化は `gas/` をデプロイし、各 JS の `ENDPOINT` に GAS の `/exec` URL を設定。
- 手順の詳細は **`docs/デプロイ手順.md`（BUYMO は C2 章）**。

---

## 主要ドキュメント

- `docs/プロジェクト状況.md` … 現状サマリ
- `docs/BUYMO_全体マップ.md` … ページ／モジュール／GAS／設定箇所／公開チェックリスト
- `docs/デプロイ手順.md` … 公開手順（AUC＝B章 / BUYMO＝C2章）
- `docs/BUYMO_認証設計.md`／`docs/BUYMO_業務システム設計.md`／`docs/BUYMO画像差し替えガイド.md`
