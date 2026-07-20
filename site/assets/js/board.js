/* ============================================================
   BUYMO 看板ボード（案件カンバン）＋ 営業サマリー＋ 案件詳細パネル/対応履歴
   データは HQ（hq-common.js）経由で共有。
   role=hq（本部：全件） / role=partner（加盟店：担当のみ who=店名）
   ============================================================ */
(function () {
  'use strict';
  var STAGES = HQ.STAGES;
  var qs = new URLSearchParams(location.search);
  var role = qs.get('role') || localStorage.getItem('buymo_role') || 'hq';
  var who = qs.get('who') || localStorage.getItem('buymo_who') || '';
  var cases = [];

  var roleLabel = { hq: '本部', partner: '加盟店', member: '会員' }[role] || '本部';
  var rEl = document.getElementById('roleLabel'); if (rEl) rEl.textContent = roleLabel + (who ? '／' + who : '');

  function visible() { return (role === 'partner' && who) ? cases.filter(function (c) { return c.assignee === who; }) : cases; }
  // 滞留判定（受付から5日以上・未完了の初期〜商談ステージ）
  var STALE_DAYS = 5, EARLY = ['新規受付', '査定中', '商談中', '後追い'];
  function daysSince(d) { if (!d) return 0; var t = new Date(String(d).replace(/\//g, '-') + 'T00:00:00'); if (isNaN(t)) return 0; return Math.floor((new Date() - t) / 86400000); }
  function isStale(c) { return EARLY.indexOf(c.stage) >= 0 && daysSince(c.date) >= STALE_DAYS; }
  function findCase(id) { for (var i = 0; i < cases.length; i++) if (cases[i].id === id) return cases[i]; return null; }
  function clearAssignee(id) {
    var c = findCase(id); if (!c || !c.assignee) return;
    addHistory(c, '担当解除：' + c.assignee); c.assignee = ''; save(c); render();
  }
  function assignCase(id, storeName) {
    var c = findCase(id); if (!c) return;
    addHistory(c, '担当割当：' + (c.assignee || '未割当') + ' → ' + storeName);
    c.assignee = storeName; save(c); render();
    if (panelId === id) fillPanel(c);
  }
  function nowStr() { var d = new Date(); function p(n) { return ('0' + n).slice(-2); } return d.getFullYear() + '/' + p(d.getMonth() + 1) + '/' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()); }
  function addHistory(c, m) { c.history = c.history || []; c.history.unshift({ t: nowStr(), m: m }); }
  function save(c) { HQ.upsertCase(c); }

  function render() {
    var board = document.getElementById('board');
    board.innerHTML = '';
    var list = visible();

    /* 未割当カラム（本部のみ） */
    if (role === 'hq') {
      var unassigned = list.filter(function (c) { return !c.assignee; });
      var ucol = document.createElement('div');
      ucol.className = 'kb-col kb-col-unassigned'; ucol.dataset.stage = '__unassigned__';
      ucol.innerHTML = '<div class="kb-col-head">未割当<span class="kb-count">' + unassigned.length + '</span></div>';
      var ubody = document.createElement('div'); ubody.className = 'kb-col-body';
      unassigned.forEach(function (c) {
        var card = document.createElement('div');
        card.className = 'kb-card'; card.draggable = true; card.dataset.id = c.id;
        var storeOpts = '<option value="">担当を選択</option>' +
          HQ.getStores().map(function (s) { return '<option value="' + HQ.esc(s.name) + '">' + HQ.esc(s.name) + '</option>'; }).join('');
        card.innerHTML = '<div class="kb-card-top"><span class="kb-id">' + c.id + '</span>' +
          (c.genre ? '<span class="kb-tag">' + HQ.esc(c.genre) + '</span>' : '') + '</div>' +
          '<div class="kb-name">' + HQ.esc(c.name || '') + '</div>' +
          '<div class="kb-meta">' + (c.date ? '<span class="kb-date">📅' + HQ.esc(c.date) + '</span>' : '') + '</div>' +
          '<div class="kb-assign-row"><select class="kb-assign-sel" data-id="' + HQ.esc(c.id) + '">' + storeOpts + '</select></div>';
        var sel = card.querySelector('.kb-assign-sel');
        sel.addEventListener('change', function () { if (sel.value) assignCase(c.id, sel.value); });
        sel.addEventListener('click', function (e) { e.stopPropagation(); });
        card.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', c.id); card.classList.add('dragging'); });
        card.addEventListener('dragend', function () { card.classList.remove('dragging'); });
        card.addEventListener('click', function (e) { if (!card.classList.contains('dragging') && !e.target.closest('.kb-assign-sel')) openPanel(c.id); });
        ubody.appendChild(card);
      });
      ucol.appendChild(ubody);
      ucol.addEventListener('dragover', function (e) { e.preventDefault(); ucol.classList.add('over'); });
      ucol.addEventListener('dragleave', function () { ucol.classList.remove('over'); });
      ucol.addEventListener('drop', function (e) { e.preventDefault(); ucol.classList.remove('over'); clearAssignee(e.dataTransfer.getData('text/plain')); });
      board.appendChild(ucol);
    }

    STAGES.forEach(function (stage) {
      var col = document.createElement('div');
      col.className = 'kb-col'; col.dataset.stage = stage;
      var items = list.filter(function (c) { return c.stage === stage; });
      col.innerHTML = '<div class="kb-col-head">' + stage + '<span class="kb-count">' + items.length + '</span></div>';
      var body = document.createElement('div'); body.className = 'kb-col-body';
      items.forEach(function (c) {
        var card = document.createElement('div');
        card.className = 'kb-card' + (isStale(c) ? ' stale' : ''); card.draggable = true; card.dataset.id = c.id;
        var hist = (c.history && c.history.length) ? '<span class="kb-hist">📝' + c.history.length + '</span>' : '';
        var staleTag = isStale(c) ? '<span class="kb-stale">滞留' + daysSince(c.date) + '日</span>' : '';
        card.innerHTML = '<div class="kb-card-top"><span class="kb-id">' + c.id + '</span>' +
          (c.genre ? '<span class="kb-tag">' + HQ.esc(c.genre) + '</span>' : '') + staleTag + hist + '</div>' +
          '<div class="kb-name">' + HQ.esc(c.name || '') + '</div>' +
          '<div class="kb-meta">' + (c.date ? '<span class="kb-date">📅' + HQ.esc(c.date) + '</span>' : '') + HQ.esc(c.assignee || '担当未定') + (c.amount ? '・' + HQ.yen(c.amount) : '') + '</div>' +
          (c.memo ? '<div class="kb-memo">' + HQ.esc(c.memo) + '</div>' : '');
        card.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', c.id); card.classList.add('dragging'); });
        card.addEventListener('dragend', function () { card.classList.remove('dragging'); });
        card.addEventListener('click', function (e) { if (!card.classList.contains('dragging')) openPanel(c.id); });
        body.appendChild(card);
      });
      col.appendChild(body);
      col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('over'); });
      col.addEventListener('dragleave', function () { col.classList.remove('over'); });
      col.addEventListener('drop', function (e) { e.preventDefault(); col.classList.remove('over'); move(e.dataTransfer.getData('text/plain'), stage); });
      board.appendChild(col);
    });
    summary();
  }

  function move(id, stage) {
    var c = findCase(id);
    if (!c || c.stage === stage) return;
    var wasUnassigned = !c.assignee;
    addHistory(c, 'ステージ変更：' + c.stage + ' → ' + stage);
    c.stage = stage; save(c); render();
    if (panelId === id) fillPanel(c);
    if (wasUnassigned) openPanel(id);
  }

  function summary() {
    var list = visible();
    var won = list.filter(function (c) { return HQ.WON.indexOf(c.stage) >= 0; });
    var amount = won.reduce(function (s, c) { return s + (Number(c.amount) || 0); }, 0);
    set('sumTotal', list.length + '件'); set('sumWon', won.length + '件');
    set('sumAmount', HQ.yen(amount)); set('sumDone', list.filter(function (c) { return c.stage === '完了'; }).length + '件');
  }
  function set(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }

  /* ---- 後追いテンプレート ---- */
  var FU_LABEL = { reminder: '査定リマインダー', market: '相場変動のご案内', campaign: 'キャンペーン', reopen: '再検討のお願い', custom: 'カスタム' };
  var FU_MSG = {
    reminder: '先日はBUYMOにお問い合わせいただきありがとうございました。その後、お車の売却についてご検討いただけましたでしょうか？最新の査定額を改めてご案内できますので、お気軽にご連絡ください。',
    market:   '現在、お車の買取相場が上昇しています。今が売り時かもしれません。BUYMOの無料査定をぜひご利用ください。',
    campaign: '期間限定！BUYMOの査定額アップキャンペーン実施中です。この機会にぜひお問い合わせください。',
    reopen:   '以前ご案内した査定から、さらに良い条件をご提示できる可能性がございます。改めてご検討いただけますでしょうか？',
    custom:   ''
  };
  function addDaysStr(n) {
    var d = new Date(); d.setDate(d.getDate() + n);
    function p(x) { return ('0' + x).slice(-2); }
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
  }
  function renderFollowups(c) {
    var el = document.getElementById('cpFuList'); if (!el) return;
    var fus = (c.followups || []).slice().sort(function (a, b) { return a.at > b.at ? 1 : -1; });
    el.innerHTML = fus.length ? fus.map(function (fu) {
      var sc = { sent: 'fu-sent', cancelled: 'fu-cancelled', pending: 'fu-pending' }[fu.status] || 'fu-pending';
      var sl = { sent: '送信済み', cancelled: '取消済', pending: '予定' }[fu.status] || '予定';
      return '<li class="fu-item ' + sc + '">' +
        '<span class="fu-date">' + HQ.esc(fu.at) + '</span>' +
        '<span class="fu-lbl">' + HQ.esc(FU_LABEL[fu.template] || fu.template || '') + '</span>' +
        '<span class="fu-pill ' + sc + '">' + sl + '</span>' +
        (fu.status === 'pending' ? '<button class="fu-del" data-fuid="' + HQ.esc(fu.id) + '">取消</button>' : '') +
        '</li>';
    }).join('') : '<li class="cp-empty">後追いスケジュールはありません。</li>';
    el.querySelectorAll('.fu-del').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var c2 = findCase(panelId); if (!c2) return;
        var fuId = btn.getAttribute('data-fuid');
        (c2.followups || []).forEach(function (f) { if (f.id === fuId) f.status = 'cancelled'; });
        addHistory(c2, '後追い取消：' + fuId); save(c2); renderFollowups(c2);
      });
    });
  }
  function scheduleFu() {
    var c = findCase(panelId); if (!c) return;
    var dateVal = document.getElementById('cpFuDate').value; if (!dateVal) return;
    var at = dateVal.replace(/-/g, '/');
    var tmpl = document.getElementById('cpFuTmpl').value;
    var msg = document.getElementById('cpFuMsg').value.trim() || FU_MSG[tmpl] || '';
    var fu = { id: 'FU-' + Date.now().toString().slice(-6), at: at, template: tmpl, msg: msg, status: 'pending' };
    c.followups = c.followups || [];
    c.followups.push(fu);
    addHistory(c, '後追い追加：' + at + '（' + (FU_LABEL[tmpl] || tmpl) + '）');
    save(c); HQ.postFollowup(c.id, fu.id, fu.at, tmpl, msg);
    document.getElementById('cpFuDate').value = ''; document.getElementById('cpFuMsg').value = '';
    renderFollowups(c);
    flash(document.getElementById('cpFuAdd'), '追加しました ✓');
  }

  /* ---- 詳細パネル ---- */
  var panel, panelId = null;
  function ensurePanel() {
    if (panel) return;
    panel = document.createElement('div');
    panel.className = 'case-panel'; panel.id = 'casePanel';
    panel.innerHTML =
      '<div class="cp-overlay" data-close></div>' +
      '<aside class="cp-body" role="dialog" aria-label="案件詳細">' +
        '<div class="cp-head"><span id="cpId" class="cp-id"></span><button class="cp-close" data-close aria-label="閉じる">×</button></div>' +
        '<div class="cp-fields">' +
          '<label>お名前<input id="cpName"></label>' +
          '<label>連絡先<input id="cpTel"></label>' +
          '<label>メール<input id="cpEmail"></label>' +
          '<label>ジャンル<input id="cpGenre"></label>' +
          '<label>担当（加盟店）<select id="cpAssignee"></select></label>' +
          '<label>ステージ<select id="cpStage"></select></label>' +
          '<label>金額<input id="cpAmount" type="number" min="0"></label>' +
          '<label class="cp-full">メモ<textarea id="cpMemo" rows="2"></textarea></label>' +
        '</div>' +
        '<button class="cp-save" id="cpSave">保存する</button>' +
        '<div class="cp-hist-area"><h3>対応履歴</h3>' +
          '<div class="cp-note-add"><input id="cpNote" placeholder="対応メモを記録（例：電話で出張日程を調整）"><button id="cpNoteBtn">記録</button></div>' +
          '<ol class="cp-timeline" id="cpTimeline"></ol>' +
        '</div>' +
        '<div class="cp-fu-area">' +
          '<h3>後追いスケジュール</h3>' +
          '<div class="cp-fu-presets">' +
            '<button class="cp-fu-pre" data-days="3">3日後</button>' +
            '<button class="cp-fu-pre" data-days="7">1週間</button>' +
            '<button class="cp-fu-pre" data-days="14">2週間</button>' +
            '<button class="cp-fu-pre" data-days="30">1ヶ月</button>' +
            '<button class="cp-fu-pre" data-days="90">3ヶ月</button>' +
            '<button class="cp-fu-pre" data-days="180">6ヶ月</button>' +
            '<button class="cp-fu-pre" data-days="365">1年後</button>' +
          '</div>' +
          '<div class="cp-fu-form">' +
            '<div class="cp-fu-row">' +
              '<input type="date" id="cpFuDate" />' +
              '<select id="cpFuTmpl">' +
                '<option value="reminder">査定リマインダー</option>' +
                '<option value="market">相場変動のご案内</option>' +
                '<option value="campaign">キャンペーンのご案内</option>' +
                '<option value="reopen">再検討のお願い</option>' +
                '<option value="custom">カスタムメッセージ</option>' +
              '</select>' +
            '</div>' +
            '<textarea id="cpFuMsg" rows="2" placeholder="送信メッセージ（テンプレート選択で自動入力）"></textarea>' +
            '<button id="cpFuAdd">後追いを追加</button>' +
          '</div>' +
          '<ol class="cp-fu-list" id="cpFuList"></ol>' +
        '</div>' +
      '</aside>';
    document.body.appendChild(panel);
    panel.addEventListener('click', function (e) { if (e.target.hasAttribute('data-close')) closePanel(); });
    document.getElementById('cpSave').addEventListener('click', savePanel);
    document.getElementById('cpNoteBtn').addEventListener('click', addNote);
    document.getElementById('cpNote').addEventListener('keydown', function (e) { if (e.key === 'Enter') addNote(); });
    document.getElementById('cpFuTmpl').addEventListener('change', function () {
      var v = this.value;
      document.getElementById('cpFuMsg').value = FU_MSG[v] || '';
    });
    panel.querySelectorAll('.cp-fu-pre').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('cpFuDate').value = addDaysStr(Number(btn.getAttribute('data-days')));
      });
    });
    document.getElementById('cpFuAdd').addEventListener('click', scheduleFu);
  }
  function opts(arr, sel, withEmpty) {
    var o = withEmpty ? '<option value="">— 未割当 —</option>' : '';
    return o + arr.map(function (v) { return '<option' + (v === sel ? ' selected' : '') + '>' + HQ.esc(v) + '</option>'; }).join('');
  }
  function fillPanel(c) {
    document.getElementById('cpId').textContent = c.id + (c.genre ? '（' + c.genre + '）' : '');
    document.getElementById('cpName').value = c.name || '';
    document.getElementById('cpTel').value = c.tel || '';
    document.getElementById('cpEmail').value = c.email || '';
    document.getElementById('cpGenre').value = c.genre || '';
    document.getElementById('cpAssignee').innerHTML = opts(HQ.getStores().map(function (s) { return s.name; }), c.assignee, true);
    document.getElementById('cpStage').innerHTML = opts(STAGES, c.stage, false);
    document.getElementById('cpAmount').value = c.amount || '';
    document.getElementById('cpMemo').value = c.memo || '';
    renderTimeline(c);
    renderFollowups(c);
  }
  function renderTimeline(c) {
    var tl = document.getElementById('cpTimeline');
    var h = c.history || [];
    tl.innerHTML = h.length ? h.map(function (e) {
      return '<li><span class="cp-time">' + HQ.esc(e.t) + '</span><span class="cp-msg">' + HQ.esc(e.m) + '</span></li>';
    }).join('') : '<li class="cp-empty">まだ記録はありません。</li>';
  }
  function openPanel(id) {
    ensurePanel();
    var c = findCase(id); if (!c) return;
    panelId = id; fillPanel(c);
    panel.classList.add('open');
  }
  function closePanel() { if (panel) panel.classList.remove('open'); panelId = null; }
  function savePanel() {
    var c = findCase(panelId); if (!c) return;
    var newStage = document.getElementById('cpStage').value;
    if (newStage !== c.stage) addHistory(c, 'ステージ変更：' + c.stage + ' → ' + newStage);
    var newAsg = document.getElementById('cpAssignee').value;
    if (newAsg !== (c.assignee || '')) addHistory(c, '担当変更：' + (c.assignee || '未割当') + ' → ' + (newAsg || '未割当'));
    c.name = document.getElementById('cpName').value.trim();
    c.tel = document.getElementById('cpTel').value.trim();
    c.email = document.getElementById('cpEmail').value.trim();
    c.genre = document.getElementById('cpGenre').value.trim();
    c.assignee = newAsg; c.stage = newStage;
    c.amount = Number(document.getElementById('cpAmount').value) || 0;
    c.memo = document.getElementById('cpMemo').value.trim();
    save(c); render(); fillPanel(c);
    flash(document.getElementById('cpSave'), '保存しました ✓');
  }
  function addNote() {
    var inp = document.getElementById('cpNote');
    var txt = inp.value.trim(); if (!txt) return;
    var c = findCase(panelId); if (!c) return;
    addHistory(c, txt); save(c); HQ.note(c.id, txt);
    inp.value = ''; renderTimeline(c); render();
  }
  function flash(btn, msg) { var o = btn.textContent; btn.textContent = msg; btn.disabled = true; setTimeout(function () { btn.textContent = o; btn.disabled = false; }, 1200); }

  /* ---- 新規案件 ---- */
  var form = document.getElementById('addForm');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = document.getElementById('acName').value.trim();
      if (!name) return;
      var c = { id: 'CS-' + Date.now().toString().slice(-5), name: name, tel: document.getElementById('acTel').value.trim(),
        genre: document.getElementById('acGenre').value.trim(),
        assignee: (role === 'partner' && who) ? who : document.getElementById('acAssignee').value.trim(),
        stage: '新規受付', amount: 0, memo: '', history: [] };
      addHistory(c, '案件を作成');
      cases.unshift(c); save(c); render(); form.reset();
    });
  }

  HQ.loadCases(function (list) { cases = list; render(); });
})();
