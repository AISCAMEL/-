/* ============================================================
   BUYMO 本部システム 共通モジュール（HQ）
   - 案件データ（buymo_cases）と加盟店（buymo_stores）を localStorage で共有
   - ENDPOINT 設定時は GAS と読み書き（doGet action=cases / doPost type:"case"）
   - 各HQ画面（ダッシュボード/ボード/リード/加盟店/レポート）が利用
   ============================================================ */
window.HQ = (function () {
  'use strict';
  var ENDPOINT = ''; // 例: https://script.google.com/macros/s/XXXX/exec（空ならデモ）
  var STAGES = ['新規受付', '査定中', '商談中', '契約', '入金待ち', '完了'];
  var WON = ['契約', '入金待ち', '完了'];
  var CKEY = 'buymo_cases', SKEY = 'buymo_stores';

  function seedCases() {
    return [
      { id: 'CS-7001', name: '佐藤 様', tel: '090-1111-2222', email: 'sato@example.com', genre: '廃車', assignee: 'いわき店', stage: '新規受付', amount: 0, memo: '不動車・引取希望' },
      { id: 'CS-7002', name: '田中 様', tel: '080-2222-3333', email: 'tanaka@example.com', genre: '事故車', assignee: 'いわき店', stage: '査定中', amount: 120000, memo: '前方損傷' },
      { id: 'CS-7003', name: '鈴木 様', tel: '070-3333-4444', email: 'suzuki@example.com', genre: 'SUV', assignee: '郡山店', stage: '商談中', amount: 1820000, memo: '他社相見積中' },
      { id: 'CS-7004', name: '山田 様', tel: '090-4444-5555', email: 'yamada@example.com', genre: '軽', assignee: 'いわき店', stage: '契約', amount: 740000, memo: '' },
      { id: 'CS-7005', name: '高橋 様', tel: '080-5555-6666', email: 'takahashi@example.com', genre: 'EV', assignee: '郡山店', stage: '入金待ち', amount: 1180000, memo: '書類待ち' },
      { id: 'CS-7006', name: '伊藤 様', tel: '070-6666-7777', email: 'ito@example.com', genre: 'セダン', assignee: 'いわき店', stage: '完了', amount: 1500000, memo: '' }
    ];
  }
  function seedStores() {
    return [
      { name: 'いわき店', area: '福島県いわき市', tel: '0246-00-0000', status: '稼働中' },
      { name: '郡山店', area: '福島県郡山市', tel: '024-000-0000', status: '稼働中' },
      { name: '仙台店', area: '宮城県仙台市', tel: '022-000-0000', status: '準備中' }
    ];
  }
  function readLS(k, seed) { try { var a = JSON.parse(localStorage.getItem(k)); return (a && a.length) ? a : seed(); } catch (e) { return seed(); } }

  function loadCases(cb) {
    if (ENDPOINT) {
      window.__hqcases = function (d) { cb(d && d.length ? d : []); };
      var s = document.createElement('script');
      s.src = ENDPOINT + '?action=cases&callback=__hqcases';
      s.onerror = function () { cb(readLS(CKEY, seedCases)); };
      document.body.appendChild(s);
    } else { cb(readLS(CKEY, seedCases)); }
  }
  function getCasesLS() { return readLS(CKEY, seedCases); }
  function saveCases(arr) { try { localStorage.setItem(CKEY, JSON.stringify(arr)); } catch (e) {} }
  function upsertCase(c) {
    var arr = getCasesLS();
    var i = -1, k; for (k = 0; k < arr.length; k++) if (arr[k].id === c.id) { i = k; break; }
    if (i >= 0) { var m; for (m in c) arr[i][m] = c[m]; } else { arr.unshift(c); }
    saveCases(arr); postCase(c);
  }
  function postCase(c) {
    if (!ENDPOINT) return;
    fetch(ENDPOINT, { method: 'POST', mode: 'no-cors', headers: { 'Content-Type': 'text/plain;charset=utf-8' },
      body: JSON.stringify({ type: 'case', id: c.id, name: c.name, phone: c.tel, email: c.email, genre: c.genre, assignee: c.assignee, stage: c.stage, amount: c.amount, memo: c.memo }) }).catch(function () {});
  }

  function note(id, text) {
    if (!ENDPOINT) return;
    fetch(ENDPOINT, { method: 'POST', mode: 'no-cors', headers: { 'Content-Type': 'text/plain;charset=utf-8' },
      body: JSON.stringify({ type: 'note', id: id, text: text }) }).catch(function () {});
  }

  function getStores() { return readLS(SKEY, seedStores); }
  function saveStores(arr) { try { localStorage.setItem(SKEY, JSON.stringify(arr)); } catch (e) {} }

  function yen(n) { return '¥' + (Number(n) || 0).toLocaleString('en-US'); }
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function stageIdx(s) { var i = STAGES.indexOf(s); return i < 0 ? 0 : i; }

  function nav(active) {
    var el = document.getElementById('hqNav');
    if (!el) return;
    var items = [
      ['dashboard', 'ダッシュボード', 'hq-dashboard.html'],
      ['board', '案件ボード', 'hq.html?role=hq'],
      ['leads', 'リード', 'hq-leads.html'],
      ['stores', '加盟店', 'hq-stores.html'],
      ['report', '営業レポート', 'report.html']
    ];
    el.innerHTML = items.map(function (it) {
      return '<a href="' + it[2] + '"' + (it[0] === active ? ' aria-current="page"' : '') + '>' + it[1] + '</a>';
    }).join('');
  }

  return {
    ENDPOINT: ENDPOINT, STAGES: STAGES, WON: WON,
    loadCases: loadCases, getCasesLS: getCasesLS, saveCases: saveCases, upsertCase: upsertCase,
    getStores: getStores, saveStores: saveStores, note: note,
    yen: yen, esc: esc, stageIdx: stageIdx, nav: nav
  };
})();
