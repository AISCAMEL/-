# 合同会社アイズ｜コーポレートサイト（corporate/）

会社全体のコーポレートサイト（aisjaltd.com 想定）です。
**自動車事業：IT事業 = 8：2** の比重で構成しています。

- 静的サイト（HTML / CSS / JS のみ・ビルド不要）
- 既存の AUC-AGENT サイト（`../site/`）とは**完全に独立**。現行の公開には一切影響しません。

---

## 🎨 デザインを変えるには（ここが一番大事）

色・フォント・角丸・余白などの「見た目の設定」は、すべて1つのファイルに集約しています。

```
corporate/assets/css/theme.css   ← このファイルだけ書き換えれば全体が変わります
```

### 例
- 全体を青基調にしたい → `--brand` と `--accent` の色コードを変える
- やわらかい印象にしたい → `--radius`（角丸）を大きくする
- 文字を大きくしたい → `--font-size-base` を `17px` などに

### AI（Claude）への頼み方の例
> 「`corporate/assets/css/theme.css` を、白×ネイビー×シルバーの上品な配色に変えて」
> 「`theme.css` だけでポップ（明るい黄×青）にして」

theme.css には **プリセット**（`.theme-blue` / `.theme-dark` / `.theme-pop`）も用意しています。
`<html>` タグに `class="theme-blue"` を付けると、そのプリセットに切り替わります。

---

## 👀 手元で確認する（プレビュー）

```bash
cd corporate
python3 -m http.server 8080
# ブラウザで http://localhost:8080 を開く
```

---

## 🚀 公開（デプロイ）するには

このフォルダは自己完結しているので、次のどちらでも公開できます。

1. **専用リポジトリに切り出す**（推奨・AISの運用に合わせる）
   `corporate/` の中身をそのまま新リポジトリへ移し、Netlify で `publish = "."` で公開。
2. **同じリポジトリから別サイトとして公開**
   Netlify でもう1サイト作り、publish ディレクトリを `corporate` に指定。
   （現行の AUC-AGENT 公開＝ `site` とは別サイトになるため、互いに影響しません）

独自ドメイン `aisjaltd.com` を、公開したサイトに割り当ててください。

---

## 📁 構成

```
corporate/
├── index.html              コーポレートTOP（車8割・IT2割）
├── README.md               このファイル
└── assets/
    ├── css/
    │   ├── theme.css        ★テーマ設定（色・フォント・形）= ここを変える
    │   └── style.css        レイアウト本体（theme.css の値を参照）
    └── img/
        ├── logo.svg         ロゴ（アイズ）
        └── favicon.svg      ファビコン

```

## ✏️ 各ブランドのリンク差し替え

`index.html` の事業紹介カードは、現在 `href="#"`（仮）です。
各ブランドの公式ページができたら、その URL に差し替えてください
（該当箇所の近くにコメントで案内しています）。
