/* AIS Premium — front-end interactions */
(function () {
  function qs(s, r) { return (r || document).querySelector(s); }
  // モバイルメニュー
  var burger = qs('#ais-burger'), nav = qs('#ais-nav');
  if (burger && nav) {
    burger.addEventListener('click', function () { nav.classList.toggle('open'); });
    nav.addEventListener('click', function (e) { if (e.target.tagName === 'A') nav.classList.remove('open'); });
  }
  // AIコンシェルジュ
  var ccBtn = qs('#ais-cc-btn'), ccPanel = qs('#ais-cc-panel'), ccClose = qs('#ais-cc-close');
  if (ccBtn && ccPanel) {
    ccBtn.addEventListener('click', function () { ccPanel.classList.toggle('open'); });
    if (ccClose) ccClose.addEventListener('click', function () { ccPanel.classList.remove('open'); });
  }
  // スクロール出現
  var els = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    els.forEach(function (el) { el.classList.add('in'); });
  } else {
    var io = new IntersectionObserver(function (ent) {
      ent.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { threshold: 0.1, rootMargin: '0px 0px -6% 0px' });
    els.forEach(function (el) { io.observe(el); });
  }
  // ニュースのタブ（簡易フィルタ：data-cat 属性で絞り込み）
  var tabs = document.querySelectorAll('.ais-tabs button');
  tabs.forEach(function (b) {
    b.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.remove('on'); });
      b.classList.add('on');
      var cat = b.getAttribute('data-cat') || '';
      document.querySelectorAll('.ais-news-grid .ais-ncard').forEach(function (card) {
        var c = card.getAttribute('data-cat') || '';
        card.style.display = (!cat || cat === c) ? '' : 'none';
      });
    });
  });
})();
