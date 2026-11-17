/* 合同会社アイズ コーポレート｜フロント用スクリプト */
(function () {
  // スマホ用メニューの開閉
  var t = document.getElementById('navToggle'), n = document.getElementById('nav');
  if (t && n) {
    t.addEventListener('click', function () { n.classList.toggle('open'); });
    n.addEventListener('click', function (e) {
      if (e.target.classList.contains('nav-link')) n.classList.remove('open');
    });
  }
  // スクロールで要素をふわっと表示（控えめ・1要素ずつ）
  var els = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    els.forEach(function (el) { el.classList.add('in'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
  els.forEach(function (el) { io.observe(el); });
})();
