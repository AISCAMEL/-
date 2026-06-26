# BUYMO 画像差し替えガイド（後から画像を入れる手順）

現状は**絵文字＋プレースホルダーSVG**で組んでいます。下記スロットに実画像を入れれば、そのまま本番品質になります。
基本方針：**ファイル名を合わせて `site/assets/img/buymo/` に置く** か、HTMLの `src` を差し替えるだけ。

---

## 1. すでに `<img>` 化済み（ファイルを置くだけ）

| スロット | ファイル | 推奨サイズ | 形式 | 使用箇所 |
|---|---|---|---|---|
| ヒーローメイン画像 | `assets/img/buymo/hero.svg` → `hero.webp`(推奨)/`hero.png` | 1200×880（表示560幅・2倍） | WebP＋PNG | `buymo.html` ヒーロー |
| マスコット | `assets/img/buymo/mascot.svg` → `mascot.png` | 300×300・**背景透過** | PNG透過/WebP | ヒーロー右下 |

**差し替え方法（どちらか）**
- A) 同名で上書き：`hero.svg` を消し、`hero.png` を置いて、`buymo.html` の `src="...hero.svg"` を `hero.png` に変更。
- B) WebP対応（推奨）：`<img>` を `<picture>` に変更して WebP→PNG フォールバック。
  ```html
  <picture>
    <source srcset="assets/img/buymo/hero.webp" type="image/webp">
    <img class="hero-img-main" src="assets/img/buymo/hero.png" alt="..." width="600" height="440">
  </picture>
  ```

---

## 2. まだ絵文字のスロット（`<img>`化すると本番品質に）

現状は絵文字で表示。実写真にする場合は、各セクションの絵文字を下表のファイル参照 `<img>` に置換します（`assets/img/buymo/photo.svg` が汎用プレースホルダー）。

| スロット | 推奨ファイル | 推奨サイズ | 使用箇所（`buymo.html`） |
|---|---|---|---|
| 買取対象車（5枚） | `targets/sedan.jpg` `suv` `kei` `accident` `old` | 480×320 | 「こんな車も買取OK!」 |
| 買取実績（車写真8枚） | `results/01.jpg`〜 | 480×320 | 「買取実績」 |
| お客様の声アバター（4枚） | `voices/01.jpg`〜 | 120×120・正方形 | 「お客様の声」 |
| オフィス写真 | `office.jpg` | 800×600 | 「会社情報」🏢 |
| サービスエリア地図 | `map.png` | 任意（横長） | 「サービスエリア」🗾 |
| 各所のマスコット | `mascot.png`（共通） | 300×300透過 | 4大特長/選ばれる理由/FAQ 等の🐮 |

**置換例（対象車カードの絵文字 → 画像）**
```html
<!-- before -->
<div class="target-img sedan" role="img" aria-label="セダンのイメージ">🚘</div>
<!-- after -->
<img class="target-img" src="assets/img/buymo/targets/sedan.jpg"
     alt="セダンの買取イメージ" width="480" height="320" loading="lazy">
```

---

## 3. ブランド画像（既存・要差し替え）

| スロット | ファイル | 状態 |
|---|---|---|
| OGP画像（SNS共有） | `assets/img/ogp.png`（1200×630） | 既存はAUC-AGENT用 → BUYMO用に要作成 |
| favicon | `assets/img/favicon.svg` / `favicon-32.png` / `favicon-180.png` | 既存はAUC用 → BUYMOロゴで要作成 |
| ロゴ | ヘッダーは現在「🐮 BUYMO」テキスト | ロゴ画像化する場合は `assets/img/buymo/logo.svg` を作成しヘッダーの `.logo` を差し替え |

---

## 4. エリア／ジャンルLP（生成ページ）の画像

`area/*` `genre/` はジェネレーターで生成しています。共通ヘッダー/フッターやマスコットを画像化する場合は、
テンプレート（`site/tools/_layout.js`／`gen-area.js`／`gen-genre.js`）を編集してから再生成：
```bash
cd site && node tools/gen-area.js && node tools/gen-genre.js
```

---

## 5. 画像の最適化（推奨）

- **WebP**（フォールバックにPNG/JPG）。`loading="lazy"` と `width`/`height` 指定でCLS防止（既存スロットは指定済み）。
- 写真は適切に圧縮（目安：1枚 150KB 以下）。
- マスコット・ロゴは**透過PNG**または**SVG**。

---

## 6. まとめ：最短で“映える”優先順

1. **ヒーロー画像**（`hero`）→ 一番効く
2. **マスコット**（`mascot`）→ サイト全体の印象
3. **買取対象車・実績の写真**
4. **OGP / favicon のBUYMO化**
5. お客様の声アバター・オフィス・地図

`assets/img/buymo/` に置けば順次反映できます。迷ったら上から対応すればOKです。
