# CARMEL 店舗振り分け機能 ― 移植キット（別プロジェクトへ）

この仕組み（**フォーム → GAS → スプレッドシート／Asana／メール／LINE を店舗ごとに振り分け**）を、
別のプロジェクト（買取・お問い合わせ・来店予約・会員システム等）へ移植するための一式です。

---

## 1. 全体像（型）

```
[フォーム(HTML)]                 [GAS 中継ハブ]                       [出力先]
?store=yamanashi  ──POST──▶  doPost(e)                     ┌─ スプレッドシート（店舗列つき）
 送信内容(JSON):             ├ payload.type で処理を振り分け │   ├─ Asana（店舗別プロジェクト/列）
  {                         │   （審査 / 保証人 / 買取 …）   │   ├─ 通知メール（店舗の宛先＋本店）
   type: '作業の種別',        ├ resolveStore_(meta.storeKey)  │   └─ 自動返信（店舗の差出人/電話/LINE）
   meta: { storeKey, store },│   → 店舗情報オブジェクト        │
   fields: [...],            └ 店舗情報で宛先・振り分けを決定 ─┘
   files:  [...]
  }
```

**再利用できる"3つの部品"**
| 部品 | ファイル | 役割 |
|---|---|---|
| ① 店舗マスター（GAS） | `carmel-stores.gs` | 店舗情報（名前/電話/LINE/通知先/Asana ID）の一元管理＋`resolveStore_()` |
| ② 店舗スニペット（フロント） | `carmel-store-snippet.js` | `?store=` 読み取り・バッジ表示・`meta`へ店舗を差し込み |
| ③ 本ガイド | この README | 組み込み手順 |

---

## 2. 移植手順（別プロジェクトへ）

### 手順A：GAS 側（受け取る側）
1. 移植先のGASプロジェクトに **`carmel-stores.gs` を丸ごと追加**（新規ファイルで貼り付け）。
2. その作業の `doPost(e)` で、店舗を解決して使う：
   ```javascript
   function doPost(e){
     var payload = JSON.parse(e.postData.contents || '{}');
     var st = resolveStore_(payload.meta && (payload.meta.storeKey || payload.meta.store)); // ★これだけ
     // 以降、st.label / st.emails / st.lineUrl / st.tel / st.asanaProjectId … を使って振り分け

     // 例1）スプレッドシートに店舗列を足す
     //   row['店舗'] = st.label;
     // 例2）通知メールを店舗宛＋本店へ
     //   MailApp.sendEmail(storeEmails_(st, 'carmelbuzzzzz@aisjaltd.com'), '【'+st.label+'】新規○○', body);
     // 例3）自動返信を店舗の差出人・電話・LINEで
     //   本文に st.tel / st.lineUrl / st.hours、差出人名に st.replyFromName
     // 例4）Asanaを店舗別プロジェクト/列へ（この作業用の section ID を店舗マスターに足すか、別途用意）
     //   projects:[st.asanaProjectId] → 作成後 sections/<その作業のsectionId>/addTask
   }
   ```
3. **複数の作業を1つのGASに同居**させる場合は、先頭で種別分岐：
   ```javascript
   if (payload.type === 'baikyaku')  return handleBaikyaku_(cfg, payload); // 買取
   if (payload.type === 'yoyaku')    return handleYoyaku_(cfg, payload);   // 来店予約
   // どの handler でも先頭で resolveStore_ を呼べば店舗振り分けが効く
   ```

### 手順B：フロント側（送る側＝フォーム）
1. フォームHTMLに **バッジ置き場**（任意）を1つ置く：
   ```html
   <div id="store-badge"></div>
   ```
2. **`carmel-store-snippet.js` を読み込む**（または中身を `<script>` で貼る）。
3. 送信時、`meta` に店舗を差し込んで送る：
   ```javascript
   var meta = CARMEL_withStore({ name: 氏名, email: メール /* ほか */ });
   var payload = { type:'baikyaku', meta: meta, fields: [...], files: [...] };
   fetch(GAS_URL, { method:'POST', headers:{'Content-Type':'text/plain;charset=utf-8'}, body: JSON.stringify(payload) });
   ```
4. 店舗別URL：`https://あなたのフォーム/?store=yamanashi` のように `?store=` を付けるだけ。

---

## 3. 新しい加盟店を増やすとき
- `carmel-stores.gs`（GAS）の `STORES` に1ブロック追加 → **新バージョンでデプロイ**
- `carmel-store-snippet.js`（フロント）の `STORES` に1行追加 → フォームを再公開
- 以上で `?store=新キー` が全作業で使えるようになる

> 省力化案：将来は **店舗マスターをスプレッドシートで管理**し、GASが起動時に読み込む方式にすると、コード編集なしで店舗追加が可能（初期構築は別途）。

---

## 4. 移植先の技術が GAS 以外（PHP / Node / 会員システム等）の場合
- **考え方は同じ**：「URLの `?store=` → 店舗キー → 店舗マスターで宛先・振り分けを決める」。
- `carmel-stores.gs` の `STORES` は**ただのデータ**なので、PHP配列・JSONなどに置き換えれば同じロジックを再現できます。
- 必要なら、移植先の言語に合わせた**店舗マスター＋resolveStore 相当**を作成します（ご相談ください）。

---

## 5. 注意点
- **店舗キー**（`yamanashi` 等）は GAS とフロントで**必ず一致**させる。
- Asana を使わない作業なら、店舗マスターの `asana*` は空 `''` のままでOK（その作業では無視される）。
- 既存の本番データ（スプレッドシート・Asana・メール）に影響する変更は、**テスト送信で1件確認**してから本番运用へ。
