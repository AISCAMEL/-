/* リード一覧：検索・フィルタ・担当割当・ステージ変更 */
(function () {
  'use strict';
  HQ.nav('leads');
  var all = [];
  var stores = HQ.getStores();

  // フィルタUIの選択肢
  var fStage = document.getElementById('fStage');
  HQ.STAGES.forEach(function (s) { var o = document.createElement('option'); o.value = s; o.textContent = s; fStage.appendChild(o); });
  var fStore = document.getElementById('fStore');
  stores.forEach(function (s) { var o = document.createElement('option'); o.value = s.name; o.textContent = s.name; fStore.appendChild(o); });

  // クエリでステージ初期フィルタ（ダッシュボードのアラートから）
  try { var q = new URLSearchParams(location.search).get('stage'); if (q) fStage.value = q; } catch (e) {}

  function storeOptions(sel) {
    return '<option value=""' + (!sel ? ' selected' : '') + '>— 未割当 —</option>' +
      stores.map(function (s) { return '<option' + (s.name === sel ? ' selected' : '') + '>' + HQ.esc(s.name) + '</option>'; }).join('');
  }
  function stageOptions(sel) {
    return HQ.STAGES.map(function (s) { return '<option' + (s === sel ? ' selected' : '') + '>' + HQ.esc(s) + '</option>'; }).join('');
  }

  function filtered() {
    var q = (document.getElementById('fSearch').value || '').trim().toLowerCase();
    var st = fStage.value, asg = fStore.value;
    return all.filter(function (c) {
      if (st && c.stage !== st) return false;
      if (asg && c.assignee !== asg) return false;
      if (q) {
        var hay = (c.id + ' ' + (c.name || '') + ' ' + (c.tel || '')).toLowerCase();
        if (hay.indexOf(q) < 0) return false;
      }
      return true;
    });
  }

  function render() {
    var list = filtered();
    document.getElementById('fCount').textContent = list.length + ' / ' + all.length + ' 件';
    document.getElementById('rows').innerHTML = list.map(function (c) {
      return '<tr data-id="' + HQ.esc(c.id) + '">' +
        '<td>' + HQ.esc(c.id) + '</td>' +
        '<td>' + HQ.esc(c.name) + '</td>' +
        '<td>' + HQ.esc(c.tel || '') + '<br><span class="td-sub">' + HQ.esc(c.email || '') + '</span></td>' +
        '<td>' + HQ.esc(c.genre || '') + '</td>' +
        '<td><select class="js-store">' + storeOptions(c.assignee) + '</select></td>' +
        '<td><select class="js-stage stage-sel s' + HQ.stageIdx(c.stage) + '">' + stageOptions(c.stage) + '</select></td>' +
        '<td>' + (c.amount ? HQ.yen(c.amount) : '—') + '</td>' +
        '</tr>';
    }).join('');
  }

  function findCase(id) { for (var i = 0; i < all.length; i++) if (all[i].id === id) return all[i]; return null; }

  document.getElementById('rows').addEventListener('change', function (e) {
    var tr = e.target.closest('tr'); if (!tr) return;
    var c = findCase(tr.getAttribute('data-id')); if (!c) return;
    if (e.target.classList.contains('js-store')) { c.assignee = e.target.value; }
    if (e.target.classList.contains('js-stage')) { c.stage = e.target.value; }
    HQ.upsertCase({ id: c.id, name: c.name, tel: c.tel, email: c.email, genre: c.genre, assignee: c.assignee, stage: c.stage, amount: c.amount, memo: c.memo });
    render();
  });

  ['fSearch', 'fStage', 'fStore'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', render);
    document.getElementById(id).addEventListener('change', render);
  });

  HQ.loadCases(function (list) { all = list; render(); });
})();
