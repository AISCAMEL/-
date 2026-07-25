# 合同会社アイズ — AUC-AGENT 業務システム

静的サイト（HTML/CSS/JS）＋ Google Apps Script（GAS）バックエンドで構成された、
**AUC-AGENT**（オークション代行サービス）のサイト・業務システムです。

| ブランド | 内容 | 入口 |
|---|---|---|
| **AUC-AGENT** | オークション代行（購入/出品代行・各種シミュレーター・会員/マイページ） | `site/auc-agent.html` |

会社情報：合同会社アイズ／福島県いわき市四倉町細谷字大町1番／古物商 第25121A010859号／info@aisjaltd.com

---

## ディレクトリ構成

```
site/            公開する静的サイト（このディレクトリを丸ごとホスティング）
  assets/        css / js / img
  tools/         QA スクリプト（Node）
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
# AUC-AGENT: http://localhost:8000/auc-agent.html
```

---

## QA（品質チェック）

```bash
cd site
node tools/check-links.js   # 内部リンク／アセット切れ
node tools/seo-check.js     # JSON-LD・title重複・canonical・OGP・データ整合
node tools/launch-check.js  # 公開準備の残り（ENDPOINT/GA4/SITE_URL/画像）を一覧
```

実機回帰（主要ページをブラウザで開いてエラー0＋要素存在を検証）：

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
- 手順の詳細は **`docs/デプロイ手順.md`**。

---

## 関連リポジトリ

| リポジトリ | 内容 |
|---|---|
| [AISCAMEL/buymo](https://github.com/AISCAMEL/buymo) | BUYMO 車買取サービス（独立リポジトリ・buymo.me にデプロイ） |

---

## 主要ドキュメント

- `docs/プロジェクト状況.md` … 現状サマリ
- `docs/デプロイ手順.md` … 公開手順
