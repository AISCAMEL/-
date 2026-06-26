/* ============================================================
   BUYMO かんたん査定シミュレーター（買取概算）
   車種クラス・年式・走行距離・状態 → 概算買取額レンジを表示。
   ※あくまで目安。係数は CONFIG を編集すれば調整できます。
   使い方: BuymoSim.init('container-id')
   ============================================================ */
window.BuymoSim = (function () {
  'use strict';

  // 区分別の買取上限の目安（おおむね1年落ち相当・円）
  var CLASS_BASE = {
    '軽自動車': 900000,
    'コンパクト': 1000000,
    'セダン': 1300000,
    'ミニバン': 1600000,
    'SUV': 1800000,
    '輸入車・高級車': 3000000,
    'トラック・商用車': 1500000
  };
  // 走行距離（代表値km）
  var MILEAGE = [
    ['〜1万km', 5000], ['1〜3万km', 20000], ['3〜5万km', 40000],
    ['5〜8万km', 65000], ['8〜10万km', 90000], ['10〜15万km', 125000], ['15万km〜', 170000]
  ];
  // 状態係数
  var CONDITION = {
    '良好（目立つ傷なし）': 1.0,
    '普通（小傷・経年相応）': 0.85,
    '修復歴・事故車': 0.55,
    '不動車・廃車予定': 0.22
  };

  function round(n) {
    if (n >= 100000) return Math.round(n / 10000) * 10000;
    return Math.max(5000, Math.round(n / 1000) * 1000);
  }
  function yen(n) { return '¥' + (n || 0).toLocaleString('en-US'); }

  function estimate(cls, year, km, condFactor) {
    var base = CLASS_BASE[cls] || 1000000;
    var now = new Date().getFullYear();
    var age = Math.max(0, now - Number(year));
    var depr = Math.max(0.06, Math.pow(0.88, age));          // 年式による減価
    var mfac = Math.max(0.2, 1 - (Number(km) / 10000) * 0.05); // 走行距離ペナルティ
    var point = base * depr * mfac * condFactor;
    var min = round(point * 0.88), max = round(point * 1.12);
    if (max < min) max = min;
    return { min: Math.max(5000, min), max: Math.max(10000, max) };
  }

  function opt(v, label) { return '<option value="' + v + '">' + (label || v) + '</option>'; }

  function init(rootId) {
    var root = document.getElementById(rootId);
    if (!root) return;
    var now = new Date().getFullYear();
    var years = ''; for (var y = now; y >= now - 30; y--) years += opt(y, y + '年');
    var classes = ''; for (var c in CLASS_BASE) classes += opt(c);
    var miles = MILEAGE.map(function (m, i) { return opt(i, m[0]); }).join('');
    var conds = ''; for (var k in CONDITION) conds += opt(k);

    root.innerHTML =
      '<div class="bsim">' +
        '<div class="bsim-grid">' +
          '<label>車種クラス<select id="bsCls">' + classes + '</select></label>' +
          '<label>年式<select id="bsYear">' + years + '</select></label>' +
          '<label>走行距離<select id="bsMile">' + miles + '</select></label>' +
          '<label>状態<select id="bsCond">' + conds + '</select></label>' +
        '</div>' +
        '<button type="button" class="bsim-btn" id="bsCalc">概算をみる</button>' +
        '<div class="bsim-result" id="bsResult" hidden>' +
          '<p class="bsim-cap">概算の買取目安</p>' +
          '<p class="bsim-range"><span id="bsMin"></span> 〜 <span id="bsMax"></span></p>' +
          '<p class="bsim-note">※ AIによる概算の目安です。実際の金額は車両状態により異なります。正確な金額は無料査定で。</p>' +
          '<a class="bsim-cta" id="bsCta" href="buymo-contact.html">この内容で無料査定を依頼 ›</a>' +
        '</div>' +
      '</div>';

    document.getElementById('bsCalc').addEventListener('click', function () {
      var cls = document.getElementById('bsCls').value;
      var year = document.getElementById('bsYear').value;
      var km = MILEAGE[Number(document.getElementById('bsMile').value)][1];
      var condKey = document.getElementById('bsCond').value;
      var r = estimate(cls, year, km, CONDITION[condKey] || 1);
      document.getElementById('bsMin').textContent = yen(r.min);
      document.getElementById('bsMax').textContent = yen(r.max);
      var q = '?genre=' + encodeURIComponent(cls) + '&est=' + encodeURIComponent(yen(r.min) + '〜' + yen(r.max));
      document.getElementById('bsCta').href = 'buymo-contact.html' + q;
      if (window.BuymoGA) BuymoGA.track('simulate', { car_class: cls, min: r.min, max: r.max });
      var res = document.getElementById('bsResult');
      res.hidden = false;
      if (res.scrollIntoView) res.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  }

  return { init: init, estimate: estimate };
})();
