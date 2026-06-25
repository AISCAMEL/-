/* ============================================================
   BUYMO 車買取 LP — インタラクション
   ============================================================ */
(function () {
  'use strict';

  /* ---- 0. 年式・都道府県の動的生成 ---- */
  var yearSel = document.getElementById('f-year');
  if (yearSel) {
    var nowYear = new Date().getFullYear();
    for (var y = nowYear; y >= nowYear - 30; y--) {
      var o = document.createElement('option');
      o.textContent = y + '年';
      o.value = String(y);
      yearSel.appendChild(o);
    }
  }
  var prefSel = document.getElementById('f-pref');
  if (prefSel) {
    var prefs = ['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県'];
    prefs.forEach(function (p) {
      var o = document.createElement('option');
      o.textContent = p; o.value = p; prefSel.appendChild(o);
    });
  }

  /* ---- 1. ハンバーガーメニュー ---- */
  var hb = document.getElementById('hamburger');
  var gnav = document.getElementById('gnav');
  if (hb && gnav) {
    hb.addEventListener('click', function () {
      var open = gnav.classList.toggle('open');
      hb.classList.toggle('open', open);
      hb.setAttribute('aria-expanded', String(open));
      hb.setAttribute('aria-label', open ? 'メニューを閉じる' : 'メニューを開く');
    });
    gnav.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') {
        gnav.classList.remove('open');
        hb.classList.remove('open');
        hb.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---- 2. スムーズスクロール（scroll-behavior フォールバック） ---- */
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var id = a.getAttribute('href');
      if (id === '#' || id.length < 2) return;
      var t = document.querySelector(id);
      if (!t) return;
      e.preventDefault();
      var top = t.getBoundingClientRect().top + window.pageYOffset - 84;
      window.scrollTo({ top: top, behavior: 'smooth' });
    });
  });

  /* ---- 3. FAQ アコーディオン ---- */
  document.querySelectorAll('.acc-q').forEach(function (q) {
    q.addEventListener('click', function () {
      var item = q.parentElement;
      var a = item.querySelector('.acc-a');
      var open = q.getAttribute('aria-expanded') === 'true';
      q.setAttribute('aria-expanded', String(!open));
      item.classList.toggle('open', !open);
      a.style.maxHeight = open ? null : a.scrollHeight + 40 + 'px';
    });
  });

  /* ---- 4. スクロールアニメーション（Intersection Observer） ---- */
  var reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('in'); });
  }

  /* ---- 5. お客様の声カルーセル ---- */
  var track = document.getElementById('voiceTrack');
  var dotsWrap = document.getElementById('voiceDots');
  if (track && dotsWrap) {
    var cards = track.children;
    var index = 0, timer = null;

    function perView() {
      var w = window.innerWidth;
      if (w <= 767) return 1;
      if (w <= 991) return 2;
      return 3;
    }
    function pages() {
      return Math.max(1, cards.length - perView() + 1);
    }
    function go(i) {
      var max = pages() - 1;
      index = i < 0 ? max : (i > max ? 0 : i);
      var card = cards[0];
      var style = getComputedStyle(card);
      var step = card.offsetWidth + parseFloat(style.marginRight || 0);
      track.style.transform = 'translateX(' + (-step * index) + 'px)';
      Array.prototype.forEach.call(dotsWrap.children, function (d, di) {
        d.classList.toggle('active', di === index);
      });
    }
    function buildDots() {
      dotsWrap.innerHTML = '';
      for (var i = 0; i < pages(); i++) {
        var b = document.createElement('button');
        b.setAttribute('role', 'tab');
        b.setAttribute('aria-label', (i + 1) + '番目のレビューへ');
        (function (i) { b.addEventListener('click', function () { go(i); restart(); }); })(i);
        dotsWrap.appendChild(b);
      }
    }
    function restart() {
      if (timer) clearInterval(timer);
      timer = setInterval(function () { go(index + 1); }, 4500);
    }
    buildDots(); go(0); restart();

    var carousel = document.getElementById('voiceCarousel');
    carousel.addEventListener('mouseenter', function () { if (timer) clearInterval(timer); });
    carousel.addEventListener('mouseleave', restart);

    var resizeT;
    window.addEventListener('resize', function () {
      clearTimeout(resizeT);
      resizeT = setTimeout(function () { buildDots(); go(0); }, 200);
    });
  }

  /* ---- 6. フォームバリデーション ---- */
  var form = document.getElementById('quoteForm');
  if (form) {
    var note = document.getElementById('formNote');

    function setErr(field, msg) {
      var el = field;
      var box = form.querySelector('.err[data-for="' + el.id + '"]');
      if (msg) { el.classList.add('invalid'); if (box) box.textContent = msg; }
      else { el.classList.remove('invalid'); if (box) box.textContent = ''; }
      return !msg;
    }

    function validateField(el) {
      var v = (el.value || '').trim();
      if (el.hasAttribute('required') && !v) return setErr(el, '入力してください');
      if (el.type === 'email' && v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return setErr(el, 'メールアドレスの形式が正しくありません');
      if (el.id === 'f-tel' && v && !/^[0-9０-９\-]{10,13}$/.test(v)) return setErr(el, '電話番号を正しく入力してください');
      if (el.type === 'number' && v && Number(v) < 0) return setErr(el, '0以上で入力してください');
      return setErr(el, '');
    }

    var fields = form.querySelectorAll('input,select');
    fields.forEach(function (el) {
      el.addEventListener('blur', function () { validateField(el); });
      el.addEventListener('input', function () { if (el.classList.contains('invalid')) validateField(el); });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var ok = true, first = null;
      fields.forEach(function (el) {
        if (!validateField(el)) { ok = false; if (!first) first = el; }
      });
      if (!ok) {
        note.textContent = '未入力・誤りの項目があります。ご確認ください。';
        note.className = 'form-note ng';
        if (first) first.focus();
        return;
      }
      note.textContent = '送信しました。担当者より最短即日でご連絡いたします。ありがとうございました。';
      note.className = 'form-note ok';
      form.reset();
      // 実運用では gas/WebApp.gs の ENDPOINT へ POST 送信
    });
  }

  /* ---- 7. トップに戻るボタン ---- */
  var toTop = document.getElementById('toTop');
  if (toTop) {
    window.addEventListener('scroll', function () {
      if (window.pageYOffset > 200) toTop.hidden = false;
      else toTop.hidden = true;
    }, { passive: true });
    toTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
})();
