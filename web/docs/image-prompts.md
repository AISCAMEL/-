# 画像生成プロンプト集（IWASAWA SURF BASE）

アプリが実際に参照している画像アセットに対応した生成プロンプトです。
生成後、各ファイルを指定パスに置けば表示に反映されます（配線が必要なものは印あり）。

- 画像モデルは英語プロンプトの方が精度が出やすいため、本文は英語で用意しています。
- **文字・ロゴは入れない**（AIは文字が崩れます）。必要な文言はアプリ側のテキストで重ねます。
- 出力は PNG または JPG。サイズは各項目の指定に合わせてください（多少前後してOK、比率が重要）。

---

## 0. 共通スタイル（すべてのプロンプトの先頭に付ける）

```
Bright, airy "morning sea" aesthetic. Japanese Pacific coast in Fukushima (Iwasawa beach vibe): soft early-morning light, clean azure-to-teal water, pale sand, gentle mellow waves. Natural, authentic, editorial photography — not stocky, not oversaturated. Realistic, high detail, shallow depth of field where suitable. No text, no letters, no logo, no watermark.
```

## 共通ネガティブ（対応ツールがあれば）

```
text, letters, typography, watermark, logo, signage text, distorted hands, extra fingers, deformed face, lowres, blurry, jpeg artifacts, oversaturated, HDR halo, cartoon, 3d render, cgi, plastic skin
```

---

## A. 広告バナー  `public/ads/`  ／ 2400×680（横長 ≈ 3.5:1）※配線済み

横長バナー。**片側に余白（コピースペース）**を残し、文字は入れない構図で。

### `public/ads/shop.png` — サーフショップ広告（WAVE RIDER 広野店）
```
[共通スタイル] Wide banner. Interior of a stylish surf shop near the beach: rows of surfboards standing along a bright wall, wetsuits on racks, warm daylight through large windows. Clean minimal composition with generous empty space on the right for text overlay. Lifestyle, inviting. 3.5:1 ultra-wide.
```

### `public/ads/wetsuit.png` — ウェットスーツ広告（AQUA wetsuits）
```
[共通スタイル] Wide banner. A neatly hung high-quality wetsuit close-up with water droplets, cool teal and navy tones, studio-meets-beach mood, soft gradient background. Lots of clean negative space on the left for text. 3.5:1 ultra-wide.
```

### `public/ads/cafe.png` — 海の宿／カフェ広告（岩沢の宿 うみやど）
```
[共通スタイル] Wide banner. A cozy seaside inn / cafe terrace overlooking the ocean at golden morning light, wooden deck, a warm cup of coffee on a table, surfboard leaning nearby. Relaxed, welcoming. Empty sky area on one side for text. 3.5:1 ultra-wide.
```

---

## B. 講座カバー  `public/courses/`  ／ 2400×1350（16:9）※配線済み

### `public/courses/beginner.png` — 「海デビュー 初心者コース」
```
[共通スタイル] 16:9 cover. A friendly beginner surfer standing in shallow, calm water holding a soft-top funboard, gentle mellow waves, bright encouraging morning light. Approachable and safe feeling. Space at top for a title overlay.
```

### `public/courses/prep.png` — 「来訪前の準備・事前学習」
```
[共通スタイル] 16:9 cover. Flat-lay of surf gear neatly arranged: wetsuit, leash, wax, towel, a notebook — top-down view on light wood, soft daylight. "Getting ready before you come to the beach" mood. Clean and organized.
```

### `public/courses/stepup.png` — 「ステップアップコース」
```
[共通スタイル] 16:9 cover. A surfer performing a clean bottom-turn / trimming along the face of a chest-high wave, dynamic but not extreme, morning light, spray backlit. Sense of progress and flow. Space for a title overlay.
```

---

## C. パートナー（岩沢まわり）  `public/partners/`  ／ 1200×675（16:9）※要配線

> 生成後、ファイルを置いたら教えてください。`demoPartners` の `image_url` を配線します
> （未設定の間はカテゴリ帯グラデ＋絵文字で表示されます）。

### `public/partners/hirono-rentacar.png` — ひろのレンタカー（駅前・ボード積載可）
```
[共通スタイル] 16:9. A compact car / minivan parked in front of a small-town train station, a surfboard being loaded onto a roof rack, bright morning, casual friendly mood. Rural Japanese coastal town.
```

### `public/partners/iwaki-mobility.png` — いわきモビリティ久之浜（EV・24時間）
```
[共通スタイル] 16:9. A clean modern EV car at a self-service rental lot at early dawn, charging station visible, quiet coastal town, cool blue morning tones. Convenient, minimal.
```

### `public/partners/umiyado.png` — 岩沢の宿 うみやど（海際・徒歩3分）
```
[共通スタイル] 16:9. A small cozy Japanese seaside guesthouse exterior, wooden entrance, surfboards leaning by the door, ocean visible in the background, warm morning light. Homey and welcoming.
```

### `public/partners/guesthouse-nami.png` — ゲストハウス なぎ（ドミトリー）
```
[共通スタイル] 16:9. A bright, tidy hostel common room with a shared kitchen, bunk beds visible, plants, large windows with soft daylight, relaxed backpacker-surfer vibe.
```

### `public/partners/cafe-shiosai.png` — 海カフェ しおさい（浜汁・テラス）
```
[共通スタイル] 16:9. A seaside cafe terrace with a warm bowl of soup and a slice of chiffon cake on a wooden table, ocean view, surfboard leaning on the railing, gentle morning light. Comforting.
```

### `public/partners/shokudo-isohei.png` — 磯平食堂（漁港直送・海鮮定食）
```
[共通スタイル] 16:9. A generous Japanese seafood set meal (sashimi, rice, miso soup) on a rustic table at a harbor-side diner, hearty and appetizing, natural daylight. "Hungry surfer's favorite" mood.
```

---

## D. インストラクター（顔写真）  `public/instructors/`  ／ 800×800（正方形）※要配線

> 実在の特定個人ではなく、一般的な人物ポートレートとして生成してください。
> 生成後、`demoInstructors` の `avatar_url` を配線します。

### `public/instructors/endo.png` — 遠藤 海斗（プロ・男性・18年）
```
[共通スタイル] Square portrait. A Japanese man in his late 30s, tanned, friendly confident smile, casual surf brand tee, ocean blurred behind, natural morning light. Approachable pro-surfer/coach look. Head-and-shoulders.
```

### `public/instructors/sato.png` — 佐藤 みなみ（トップアマ・女性）
```
[共通スタイル] Square portrait. A Japanese woman in her late 20s, warm gentle smile, sun-kissed, casual beachwear, ocean blurred behind, soft daylight. Friendly, reassuring instructor for beginners. Head-and-shoulders.
```

### `public/instructors/kudo.png` — 工藤 亮（認定インストラクター・男性・安全重視）
```
[共通スタイル] Square portrait. A calm, dependable Japanese man in his 40s, kind expression, rash guard or polo, beach background, natural light. Safety-first, family-friendly coach vibe. Head-and-shoulders.
```

---

## E.（任意）トップのヒーロー背景  ／ 2400×1600 以上

> 今はグラデーション背景。写真にしたい場合の候補です（要配線）。
```
[共通スタイル] Wide hero shot of Iwasawa-like beach at dawn: long empty shoreline, gentle glassy waves, soft pink-and-teal morning sky, a lone surfer silhouette walking toward the water carrying a board. Cinematic, calm, aspirational. Leave the upper area relatively clean for headline text.
```

---

## 置き場所まとめ

| 用途 | パス | サイズ / 比率 | 配線 |
|---|---|---|---|
| 広告バナー ×3 | `public/ads/*.png` | 2400×680 / 3.5:1 | 済（上書きで反映） |
| 講座カバー ×3 | `public/courses/*.png` | 2400×1350 / 16:9 | 済（上書きで反映） |
| パートナー ×6 | `public/partners/*.png` | 1200×675 / 16:9 | 要（依頼ください） |
| 講師 ×3 | `public/instructors/*.png` | 800×800 / 1:1 | 要（依頼ください） |
| ヒーロー（任意） | `public/hero.png` 等 | 2400×1600 / 3:2 | 要（依頼ください） |
