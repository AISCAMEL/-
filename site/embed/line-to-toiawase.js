/* =====================================================================
   LINE → お問い合わせ 置き換え＋ポップアップ（在庫ページ以外の全ページ）
   設置先: WPCode → JSコードの追加 / Auto Insert・サイト全体フッター
   動作:
     - lin.ee へのリンクを「お問い合わせ」に変換し、クリックで
       お問い合わせフォームをポップアップ表示
     - 在庫（商品詳細 single-portfolio）ページは対象外
     - AIチャット内(#carmel-cb-root)のLINEリンクは対象外
     - ポップアップ内(iframe)ではヘッダー等を隠してフォームだけ表示
   ===================================================================== */
(function () {
  'use strict';

  // 在庫（商品詳細）ページは何もしない
  if (document.body.classList.contains('single-portfolio')) return;

  // ポップアップ（iframe）内で表示されている場合は、周辺パーツを隠して終了
  if (window.self !== window.top) {
    document.documentElement.classList.add('carmel-embedded');
    return;
  }

  var CONTACT_URL = 'https://carmelonline.jp/申込フォーム共通?embed=1';

  function buildModal() {
    if (document.getElementById('carmel-toi-modal')) return;
    var ov = document.createElement('div');
    ov.id = 'carmel-toi-modal';
    ov.className = 'carmel-toi-ov';
    ov.innerHTML =
      '<div class="carmel-toi-box" role="dialog" aria-modal="true" aria-label="お問い合わせ">' +
      '<button type="button" class="carmel-toi-x" aria-label="閉じる">×</button>' +
      '<div class="carmel-toi-head"><span>お問い合わせ</span></div>' +
      '<iframe class="carmel-toi-frame" title="お問い合わせフォーム" src="about:blank"></iframe>' +
      '</div>';
    document.body.appendChild(ov);
    ov.addEventListener('click', function (e) { if (e.target === ov) closeModal(); });
    ov.querySelector('.carmel-toi-x').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
  }

  function openModal() {
    buildModal();
    var ov = document.getElementById('carmel-toi-modal');
    var fr = ov.querySelector('.carmel-toi-frame');
    if (fr.getAttribute('src') === 'about:blank') fr.setAttribute('src', CONTACT_URL);
    document.documentElement.style.overflow = 'hidden';
    ov.classList.add('is-open');
  }

  function closeModal() {
    var ov = document.getElementById('carmel-toi-modal');
    if (!ov) return;
    ov.classList.remove('is-open');
    document.documentElement.style.overflow = '';
  }

  function convert(a) {
    if (!a || a.dataset.carmelToi) return;
    if (a.closest('#carmel-cb-root')) return; // AIチャットのLINEリンクは残す
    a.dataset.carmelToi = '1';

    var img = a.querySelector('img');
    if (img) {
      // 画像ボタン（ヘッダー等）はテキストボタンに差し替え
      a.classList.add('carmel-toi-textbtn');
      a.textContent = 'お問い合わせ';
    } else {
      var t = (a.textContent || '').replace(/LINE(で)?(無料)?(直接)?相談(する)?|LINE希望|LINE/g, 'お問い合わせ');
      a.textContent = /お問い合わせ/.test(t) ? t.trim() : 'お問い合わせ';
    }
    a.setAttribute('href', '#');
    a.setAttribute('role', 'button');
    a.removeAttribute('target');
    a.addEventListener('click', function (e) { e.preventDefault(); openModal(); });
  }

  function run() {
    document.querySelectorAll('a[href*="lin.ee"]').forEach(convert);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  setTimeout(run, 1200); // 遅延生成される導線にも対応
})();
