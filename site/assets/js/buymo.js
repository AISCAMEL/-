/* ============================================================
   BUYMO 車買取 LP — インタラクション
   ============================================================ */
(function () {
  'use strict';

  /* =======================================================
     GAS 送信先（査定・問い合わせ・パートナー応募 共通）
     gas/WebApp.gs をデプロイしたら、その /exec URL をここに設定。
     空欄のままならデモ動作（送信せず完了メッセージのみ）。
     送信は type:"contact" として既存ハンドラに格納されます
     （GAS側の改修は不要。詳細は message にまとめて送信）。
     ======================================================= */
  var ENDPOINT = 'https://script.google.com/macros/s/AKfycbztlrkyIt0WE5xNHmF8qy9EnRvPJ8dzxzU_13HvvEpuiDd2v9GBTnh36A9pivVq_er4Bw/exec';

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
    // エリアLPから ?pref=◯◯県 で来た場合は都道府県を自動選択
    try {
      var qp = new URLSearchParams(window.location.search).get('pref');
      if (qp && prefs.indexOf(qp) !== -1) prefSel.value = qp;
    } catch (e) { /* noop */ }
  }

  /* ---- ジャンル/エリアLPからの来訪を可視化（?genre= / ?pref=） ---- */
  var ctxEl = document.getElementById('formContext');
  if (ctxEl) {
    try {
      var sp = new URLSearchParams(window.location.search);
      var gGenre = sp.get('genre');
      var gPref = sp.get('pref');
      if (gGenre || gPref) {
        var parts = [];
        if (gPref) parts.push(gPref);
        if (gGenre) parts.push(gGenre);
        ctxEl.innerHTML = '📋 <strong>' + parts.join('／') + '</strong> についてのお問い合わせとして承ります。';
        ctxEl.hidden = false;
        // 種別が未選択なら「無料査定の申し込み」を初期選択
        var typeSel = document.getElementById('f-type');
        if (typeSel && !typeSel.value) typeSel.value = '無料査定の申し込み';
        // お問い合わせ内容に書き出しを補助（空のときのみ）
        var msg = document.getElementById('f-message');
        if (msg && !msg.value && gGenre) {
          msg.value = '「' + gGenre + '」について査定を希望します。\n（年式・走行距離・状態などをご記入ください）\n';
        }
      }
    } catch (e) { /* noop */ }
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
    window._buymoIO = io;
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('in'); });
  }

  /* ---- 4.5 buymo-data.js からお客様の声・買取実績を描画 ---- */
  (function () {
    function avatarHtml(src, name) {
      if (!src) return '';
      if (src.indexOf('/') !== -1 || src.indexOf('http') === 0) {
        return '<img src="' + src + '" alt="' + name + '" class="avatar-img" width="48" height="48">';
      }
      return '<span class="avatar" aria-hidden="true">' + src + '</span>';
    }
    function yen(n) { return '¥' + (n || 0).toLocaleString('en-US'); }
    function stars(n) { return '★'.repeat(Math.max(1, Math.min(5, n || 5))); }

    var voices = window.BUYMO_VOICES;
    var voiceTrack = document.getElementById('voiceTrack');
    var voicesNote = document.getElementById('voicesNote');
    if (voiceTrack && voices && voices.length) {
      var hasDemo = voices.some(function (v) { return v._demo; });
      if (voicesNote) voicesNote.hidden = !hasDemo;
      voiceTrack.innerHTML = voices.map(function (v) {
        return '<article class="card voice-card">' +
          '<div class="voice-head">' + avatarHtml(v.avatar, v.name) + '<div><p class="voice-name">' + v.name + '</p><p class="voice-meta">' + v.meta + '</p></div></div>' +
          '<p class="stars" aria-label="5段階評価で' + (v.stars || 5) + '">' + stars(v.stars) + '</p>' +
          '<p class="voice-body">' + v.body + '</p>' +
          '</article>';
      }).join('');
    }

    var results = window.BUYMO_RESULTS;
    var resultGrid = document.getElementById('resultGrid');
    var resultsNote = document.getElementById('resultsNote');
    if (resultGrid && results && results.length) {
      var hasDemoR = results.some(function (r) { return r._demo; });
      if (resultsNote) resultsNote.hidden = !hasDemoR;
      resultGrid.innerHTML = results.map(function (r) {
        var isImg = r.icon && (r.icon.indexOf('/') !== -1 || r.icon.indexOf('http') === 0);
        var iconHtml = isImg
          ? '<img src="' + r.icon + '" alt="' + r.name + '" class="result-photo">'
          : '<div class="result-img" aria-hidden="true">' + r.icon + '</div>';
        return '<article class="card result-card reveal">' +
          iconHtml +
          '<h3>' + r.name + '</h3>' +
          '<p class="result-year">' + r.year + '</p>' +
          '<p class="result-price">' + yen(r.price) + '</p>' +
          '<p class="result-area">📍' + r.area + '</p>' +
          '</article>';
      }).join('');
      // 動的追加した .reveal 要素も Intersection Observer に登録する
      resultGrid.querySelectorAll('.reveal').forEach(function (el) {
        if (window._buymoIO) window._buymoIO.observe(el);
        else el.classList.add('in');
      });
    }
  })();

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
      if (el.type === 'checkbox') {
        if (el.hasAttribute('required') && !el.checked) return setErr(el, '同意が必要です');
        return setErr(el, '');
      }
      var v = (el.value || '').trim();
      if (el.hasAttribute('required') && !v) return setErr(el, '入力してください');
      if (el.type === 'email' && v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return setErr(el, 'メールアドレスの形式が正しくありません');
      if (el.id === 'f-tel' && v && !/^[0-9０-９\-]{10,13}$/.test(v)) return setErr(el, '電話番号を正しく入力してください');
      if (el.type === 'number' && v && Number(v) < 0) return setErr(el, '0以上で入力してください');
      return setErr(el, '');
    }

    var fields = form.querySelectorAll('input,select,textarea');
    fields.forEach(function (el) {
      el.addEventListener('blur', function () { validateField(el); });
      el.addEventListener('input', function () { if (el.classList.contains('invalid')) validateField(el); });
      el.addEventListener('change', function () { if (el.classList.contains('invalid')) validateField(el); });
    });

    // フォーム内容→GASペイロード（type:"contact"・詳細は message に整形）
    function buildPayload() {
      var get = function (id) { var el = document.getElementById(id); return el ? (el.value || '').trim() : ''; };
      var labels = {
        type: '種別', maker: '車種', year: '年式', mileage: '走行距離(km)',
        pref: '都道府県', addr: 'ご住所', company: '会社名・屋号', message: 'お問い合わせ内容'
      };
      var lines = [];
      Object.keys(labels).forEach(function (id) {
        var v = get('f-' + id);
        if (v) lines.push(labels[id] + '：' + v);
      });
      var params = {};
      try { new URLSearchParams(window.location.search).forEach(function (v, k) { params[k] = v; }); } catch (e) {}
      var source = 'BUYMO ' + (document.title || '') + ' [' + window.location.pathname + ']';
      if (params.genre) lines.push('ジャンル：' + params.genre);
      if (params.est) lines.push('シミュレーター概算：' + params.est);
      return {
        type: 'buymo',
        source: source,
        genre: params.genre || '',
        name: get('f-name'),
        email: get('f-email'),
        phone: get('f-tel'),
        message: lines.join('\n') + '\n— ' + source
      };
    }

    function sendLead(payload) {
      if (!ENDPOINT) return Promise.resolve({ ok: true, demo: true });
      return fetch(ENDPOINT, {
        method: 'POST',
        mode: 'no-cors',
        headers: { 'Content-Type': 'text/plain;charset=utf-8' }, // プリフライト回避
        body: JSON.stringify(payload)
      }).then(function () { return { ok: true }; });
    }

    var submitBtn = form.querySelector('button[type="submit"]');

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

      if (submitBtn) { submitBtn.disabled = true; }
      note.textContent = '送信しています…';
      note.className = 'form-note';

      var payload = buildPayload();
      if (window.BuymoGA) BuymoGA.track('generate_lead', { genre: payload.genre || '', source: payload.source || '' });
      sendLead(payload).then(function () {
        note.textContent = '送信しました。ありがとうございます。ページを移動します…';
        note.className = 'form-note ok';
        form.reset();
        // 送信完了（サンクス）ページへ遷移。来訪元ジャンルを引き継ぐ。
        var q = payload.genre ? ('?genre=' + encodeURIComponent(payload.genre)) : '';
        window.location.href = 'buymo-thanks.html' + q;
      }).catch(function () {
        note.textContent = '送信に失敗しました。お手数ですが 050-1784-2929 へお電話ください。';
        note.className = 'form-note ng';
      }).then(function () {
        if (submitBtn) { submitBtn.disabled = false; }
      });
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

  /* ---- 8. 追従CTAバー（スクロールで表示／フォーム表示中は隠す） ---- */
  var sticky = document.getElementById('stickyCta');
  if (sticky) {
    var formEl = document.getElementById('form');
    var formInView = false;
    function updateSticky() {
      var show = window.pageYOffset > 600 && !formInView;
      sticky.classList.toggle('show', show);
      sticky.setAttribute('aria-hidden', String(!show));
      document.body.classList.toggle('has-sticky', show);
    }
    if ('IntersectionObserver' in window && formEl) {
      new IntersectionObserver(function (es) {
        formInView = es[0].isIntersecting; updateSticky();
      }, { threshold: 0 }).observe(formEl);
    }
    window.addEventListener('scroll', updateSticky, { passive: true });
    updateSticky();
  }
})();
