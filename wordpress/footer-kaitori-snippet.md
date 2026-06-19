# フッターの「お問い合わせ」列 → 「買取フォーム」へ変更（carmelonline.jp 全ページ）

各ページ共通フッターの **「お問い合わせ」列**（LINEで相談する／お電話 050-1793-5554／受付時間）を
取り外し、代わりに **「買取フォーム」列**（無料査定依頼フォームへのボタン）に差し替えます。

> このサイトは WordPress（WPBakery）です。テーマの footer の HTML マークアップを直接確認できないため、
> CSS クラス名に依存せず、**見出しテキスト「お問い合わせ」を手がかりに**該当列を特定して差し替えます。
> どのテーマ・どのページでも動作します。

---

## ★ 最初に1か所だけ設定

下のスニペット冒頭の `FORM_URL` を、**実際の無料査定依頼（買取）フォームのページ URL** に変更してください。

- プレビュー用 URL は `/?page_id=2786`（= 無料査定依頼ページ）。
- 公開済みページの正式なパーマリンク（例：`https://carmelonline.jp/sell/` など）が分かる場合は、そちらを設定するのが確実です。
- 分からない場合は、ひとまず `/?page_id=2786` のままでも動作します。

---

## 手順A（推奨）：WPCode で JavaScript スニペットを追加

1. WordPress 管理画面 → **Code Snippets（WPCode）** → **+ Add Snippet** → **Add Your Custom Code (New Snippet)**
2. **Code Type** で **「JavaScript Snippet」** を選択
3. 下記コードを貼り付け
4. **Insertion** → *Auto Insert* / **Location** → **Site Wide Footer**（`wp_footer`）
5. 右上のトグルを **Active** にして **Save Snippet**

```javascript
(function () {
  // ★ 買取（無料査定依頼）フォームのページ URL に変更してください
  var FORM_URL  = '/?page_id=2786';
  var BTN_LABEL = '無料査定依頼フォーム';   // ボタン文言
  var HEADING   = '買取フォーム';           // 列見出し

  function swap() {
    var footer =
      document.querySelector('footer') ||
      document.querySelector('#colophon') ||
      document.querySelector('.site-footer') ||
      document.querySelector('.footer');
    if (!footer || footer.dataset.kaitoriDone) return;

    // フッター内で、見出しテキストがちょうど「お問い合わせ」の要素を探す
    var candidates = footer.querySelectorAll('h1,h2,h3,h4,h5,h6,p,div,span,strong,b');
    var heading = null;
    for (var i = 0; i < candidates.length; i++) {
      // 子要素を含まず、テキストが「お問い合わせ」のものだけを見出しとみなす
      var el = candidates[i];
      if (el.children.length === 0 && (el.textContent || '').trim() === 'お問い合わせ') {
        heading = el;
        break;
      }
    }
    if (!heading) return; // 見つからなければ何もしない（既存フッターを壊さない）

    // 見出しの親（=列コンテナ）を差し替え対象にする
    var col = heading.parentElement;
    if (!col) return;

    // 列の中身を「買取フォーム」見出し＋ボタンに作り替える
    col.innerHTML = '';

    var h = document.createElement(heading.tagName);
    if (heading.className) h.className = heading.className; // 元の見出しスタイルを継承
    h.textContent = HEADING;
    col.appendChild(h);

    var btn = document.createElement('a');
    btn.href = FORM_URL;
    btn.className = 'carmel-kaitori-btn';
    btn.textContent = BTN_LABEL;
    col.appendChild(btn);

    footer.dataset.kaitoriDone = '1';
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', swap);
  } else {
    swap();
  }
})();
```

---

## 手順B：ボタンの見た目（CSS）

管理画面 → **外観 → カスタマイズ → 追加CSS**（または上部バーの **Custom CSS**、もしくは WPCode の「CSS Snippet」）に貼り付け：

```css
/* フッター 買取フォーム ボタン */
.carmel-kaitori-btn {
  display: inline-block;
  margin-top: 8px;
  padding: 12px 22px;
  background: #f5821f;          /* サイトのオレンジCTAに合わせる */
  color: #fff !important;
  font-weight: 700;
  text-decoration: none;
  border-radius: 9999px;
  line-height: 1.4;
  transition: opacity .2s ease;
}
.carmel-kaitori-btn:hover {
  opacity: .85;
  color: #fff !important;
}
```

---

## 動作確認

1. 任意のページ（TOP・記事・固定ページなど）を開く
2. フッター下部の列が **「会社情報」までは従来どおり**、その横が
   **「買取フォーム」＋〔無料査定依頼フォーム〕ボタン**に変わっていることを確認
3. ボタンを押して買取（無料査定依頼）フォームへ遷移することを確認
4. スマホ表示でも崩れていないか確認

## 注意・補足

- このスニペットは見出し「お問い合わせ」が**完全一致**した場合のみ差し替えます。
  万一フッター見出しの表記が異なる場合（例：「お問合せ」など）は、JS 内の
  `=== 'お問い合わせ'` を実際の表記に合わせてください。
- テーマの `footer.php` を直接編集できる場合は、該当列のマークアップを
  直接書き換えるのが最も堅実です（テーマ更新で消えない子テーマ推奨）。
  その場合は上記 JS は不要です。
- このファイルはリポジトリ内の控え（適用は WordPress 管理画面で行います）。
