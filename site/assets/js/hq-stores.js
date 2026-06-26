/* 加盟店管理：一覧＋実績（案件数/確定売上）＋追加＋状況切替 */
(function () {
  'use strict';
  HQ.nav('stores');
  var stores = HQ.getStores();
  var cases = [];

  function statsFor(name) {
    var cs = cases.filter(function (c) { return c.assignee === name; });
    var sales = cs.filter(function (c) { return c.stage === '完了'; }).reduce(function (s, c) { return s + (Number(c.amount) || 0); }, 0);
    var active = cs.filter(function (c) { return c.stage !== '完了'; }).length;
    return { total: cs.length, active: active, sales: sales };
  }

  function render() {
    var grid = document.getElementById('storeGrid');
    grid.innerHTML = stores.map(function (s, i) {
      var st = statsFor(s.name);
      var on = s.status === '稼働中';
      return '<div class="store-card">' +
        '<div class="store-head"><span class="store-name">🏪 ' + HQ.esc(s.name) + '</span>' +
          '<button class="store-status ' + (on ? 'on' : 'off') + '" data-i="' + i + '">' + HQ.esc(s.status) + '</button></div>' +
        '<p class="store-meta">📍 ' + HQ.esc(s.area || '—') + '<br>📞 ' + HQ.esc(s.tel || '—') + '</p>' +
        '<div class="store-stats">' +
          '<div><span class="ss-num">' + st.total + '</span><span class="ss-label">案件</span></div>' +
          '<div><span class="ss-num">' + st.active + '</span><span class="ss-label">進行中</span></div>' +
          '<div><span class="ss-num">' + HQ.yen(st.sales) + '</span><span class="ss-label">確定売上</span></div>' +
        '</div></div>';
    }).join('');
  }

  document.getElementById('storeGrid').addEventListener('click', function (e) {
    var btn = e.target.closest('.store-status'); if (!btn) return;
    var i = Number(btn.getAttribute('data-i'));
    stores[i].status = stores[i].status === '稼働中' ? '準備中' : '稼働中';
    HQ.saveStores(stores); render();
  });

  document.getElementById('addStore').addEventListener('submit', function (e) {
    e.preventDefault();
    var name = document.getElementById('sName').value.trim();
    if (!name) return;
    stores.push({ name: name, area: document.getElementById('sArea').value.trim(), tel: document.getElementById('sTel').value.trim(), status: '準備中' });
    HQ.saveStores(stores); e.target.reset(); render();
  });

  HQ.loadCases(function (list) { cases = list; render(); });
})();
