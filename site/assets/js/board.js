/* ============================================================
   BUYMO 看板ボード（案件カンバン）＋ 営業サマリー
   - ENDPOINT 未設定：localStorage でデモ動作（サンプル投入）
   - ENDPOINT 設定時：GAS doGet(action=cases) で取得 / doPost(type:"case") で更新
   - role=hq（本部：全件） / role=partner（加盟店：担当のみ who=店名）
   ============================================================ */
(function () {
  'use strict';
  var ENDPOINT = ''; // 例: https://script.google.com/macros/s/XXXX/exec（空ならデモ）
  var STAGES = ['新規受付', '査定中', '商談中', '契約', '入金待ち', '完了'];
  var KEY = 'buymo_cases';
  var qs = new URLSearchParams(location.search);
  var role = qs.get('role') || localStorage.getItem('buymo_role') || 'hq';
  var who = qs.get('who') || localStorage.getItem('buymo_who') || '';
  var cases = [];

  var roleLabel = { hq: '本部', partner: '加盟店', member: '会員' }[role] || '本部';
  var rEl = document.getElementById('roleLabel'); if (rEl) rEl.textContent = roleLabel + (who ? '／' + who : '');

  function seed() {
    return [
      { id: 'CS-7001', name: '佐藤 様', tel: '090-xxxx', genre: '廃車', assignee: 'いわき店', stage: '新規受付', amount: 0, memo: '不動車・引取希望' },
      { id: 'CS-7002', name: '田中 様', tel: '080-xxxx', genre: '事故車', assignee: 'いわき店', stage: '査定中', amount: 120000, memo: '前方損傷' },
      { id: 'CS-7003', name: '鈴木 様', tel: '070-xxxx', genre: 'SUV', assignee: '郡山店', stage: '商談中', amount: 1820000, memo: '他社相見積中' },
      { id: 'CS-7004', name: '山田 様', tel: '090-xxxx', genre: '軽', assignee: 'いわき店', stage: '契約', amount: 740000, memo: '' },
      { id: 'CS-7005', name: '高橋 様', tel: '080-xxxx', genre: 'EV', assignee: '郡山店', stage: '入金待ち', amount: 1180000, memo: '書類待ち' },
      { id: 'CS-7006', name: '伊藤 様', tel: '070-xxxx', genre: 'セダン', assignee: 'いわき店', stage: '完了', amount: 1500000, memo: '' }
    ];
  }
  function readLS() { try { var a = JSON.parse(localStorage.getItem(KEY)); return (a && a.length) ? a : seed(); } catch (e) { return seed(); } }
  function saveLS() { try { localStorage.setItem(KEY, JSON.stringify(cases)); } catch (e) {} }

  function load() {
    if (ENDPOINT) {
      window.__cases = function (d) { cases = (d && d.length) ? d : []; render(); };
      var s = document.createElement('script');
      s.src = ENDPOINT + '?action=cases' + (role === 'partner' && who ? '&assignee=' + encodeURIComponent(who) : '') + '&callback=__cases';
      s.onerror = function () { cases = readLS(); render(); };
      document.body.appendChild(s);
    } else { cases = readLS(); render(); }
  }

  function postCase(c) {
    if (!ENDPOINT) return;
    fetch(ENDPOINT, { method: 'POST', mode: 'no-cors', headers: { 'Content-Type': 'text/plain;charset=utf-8' },
      body: JSON.stringify({ type: 'case', id: c.id, name: c.name, phone: c.tel, genre: c.genre, assignee: c.assignee, stage: c.stage, amount: c.amount, memo: c.memo }) }).catch(function () {});
  }

  function visible() { return (role === 'partner' && who) ? cases.filter(function (c) { return c.assignee === who; }) : cases; }
  function yen(n) { return '¥' + (Number(n) || 0).toLocaleString('en-US'); }

  function render() {
    var board = document.getElementById('board');
    board.innerHTML = '';
    var list = visible();
    STAGES.forEach(function (stage) {
      var col = document.createElement('div');
      col.className = 'kb-col'; col.dataset.stage = stage;
      var items = list.filter(function (c) { return c.stage === stage; });
      col.innerHTML = '<div class="kb-col-head">' + stage + '<span class="kb-count">' + items.length + '</span></div>';
      var body = document.createElement('div'); body.className = 'kb-col-body';
      items.forEach(function (c) {
        var card = document.createElement('div');
        card.className = 'kb-card'; card.draggable = true; card.dataset.id = c.id;
        card.innerHTML = '<div class="kb-card-top"><span class="kb-id">' + c.id + '</span>' +
          (c.genre ? '<span class="kb-tag">' + c.genre + '</span>' : '') + '</div>' +
          '<div class="kb-name">' + (c.name || '') + '</div>' +
          '<div class="kb-meta">' + (c.assignee || '担当未定') + (c.amount ? '・' + yen(c.amount) : '') + '</div>' +
          (c.memo ? '<div class="kb-memo">' + c.memo + '</div>' : '');
        card.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', c.id); card.classList.add('dragging'); });
        card.addEventListener('dragend', function () { card.classList.remove('dragging'); });
        body.appendChild(card);
      });
      col.appendChild(body);
      col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('over'); });
      col.addEventListener('dragleave', function () { col.classList.remove('over'); });
      col.addEventListener('drop', function (e) {
        e.preventDefault(); col.classList.remove('over');
        var id = e.dataTransfer.getData('text/plain');
        move(id, stage);
      });
      board.appendChild(col);
    });
    summary();
  }

  function move(id, stage) {
    var c = cases.filter(function (x) { return x.id === id; })[0];
    if (!c || c.stage === stage) return;
    c.stage = stage; saveLS(); render(); postCase(c);
  }

  function summary() {
    var list = visible();
    var won = list.filter(function (c) { return ['契約', '入金待ち', '完了'].indexOf(c.stage) >= 0; });
    var amount = won.reduce(function (s, c) { return s + (Number(c.amount) || 0); }, 0);
    set('sumTotal', list.length + '件');
    set('sumWon', won.length + '件');
    set('sumAmount', yen(amount));
    set('sumDone', list.filter(function (c) { return c.stage === '完了'; }).length + '件');
  }
  function set(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }

  // 新規案件の追加
  var form = document.getElementById('addForm');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = document.getElementById('acName').value.trim();
      if (!name) return;
      var c = {
        id: 'CS-' + Date.now().toString().slice(-5),
        name: name, tel: document.getElementById('acTel').value.trim(),
        genre: document.getElementById('acGenre').value.trim(),
        assignee: (role === 'partner' && who) ? who : document.getElementById('acAssignee').value.trim(),
        stage: '新規受付', amount: 0, memo: ''
      };
      cases.unshift(c); saveLS(); render(); postCase(c); form.reset();
    });
  }

  load();
})();
