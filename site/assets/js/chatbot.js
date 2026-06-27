/* ============================================================
   BUYMO チャットボット（ユーザー用 / 加盟店用 切替）
   - 初期モード: window.BUYMO_BOT_MODE = 'user' | 'partner'（既定 user）
   - ヘッダーのトグルでいつでも切替可能
   - ルールベース（キーワード一致）。将来 Claude API 等に差し替え可（respond を置換）。
   ============================================================ */
(function () {
  'use strict';
  var MODE = (window.BUYMO_BOT_MODE === 'partner') ? 'partner' : 'user';
  var BOT_ENDPOINT = ''; // GAS /exec を設定すると AI 応答（OpenRouter）。空ならルールベース。

  var KB = {
    user: {
      title: '買取サポート',
      greet: 'こんにちは！車買取についてお答えします。気になることをどうぞ。',
      chips: ['査定は無料？', '事故車もOK？', '入金はいつ？', '必要な書類は？'],
      rules: [
        { k: ['査定', '料', '無料', '費用', '出張'], a: '査定・出張・手続きはすべて無料です。お客様のご負担はありません。' },
        { k: ['事故', '不動', '廃車', '動かない', '水没'], a: '事故車・不動車・廃車・水没車もOK。レッカー引取も無料で対応します。' },
        { k: ['入金', '振込', 'いつ', '支払'], a: 'ご契約・書類確認後、最短即日〜数営業日でご指定口座へお振込みします。' },
        { k: ['書類', '車検証', '必要', '印鑑'], a: '車検証・自賠責保険証・本人確認書類・印鑑などが必要です。詳しくは担当がご案内します。' },
        { k: ['キャンセル', '断'], a: '査定後のキャンセルは無料です。お気軽にご相談ください。' },
        { k: ['エリア', '地域', 'どこ', '対応'], a: '全国47都道府県に対応。お近くのスタッフが無料で出張査定に伺います。' },
        { k: ['ローン', '残債'], a: 'ローンが残っている車も買取可能です。残債の精算もサポートします。' }
      ],
      fallback: 'お力になれずすみません。無料査定フォーム（buymo-contact.html）かお電話（0120-123-456）で詳しくご案内します。'
    },
    partner: {
      title: '加盟店サポート',
      greet: 'BUYMO加盟店向けサポートです。運営・査定・システムの疑問にお答えします。',
      chips: ['出品の流れは？', 'システムの使い方', '相場の調べ方', '集客は？'],
      rules: [
        { k: ['出品', 'オークション', '流れ', '搬入'], a: '出品は「申込→査定→出品票作成→会場搬入→落札」。詳細はアカデミーの〈出品マニュアル〉コースをご覧ください。' },
        { k: ['報酬', '手数料', '収益', 'ロイヤリティ'], a: '報酬・ロイヤリティはプランによります。詳細は本部の個別説明をご確認ください。' },
        { k: ['相場', '査定', 'いくら', '金額'], a: '相場は査定シミュレーター/オークション実績を参照。判断に迷う場合は本部へエスカレーションを。' },
        { k: ['システム', '看板', 'リード', '使い方', 'ボード'], a: '案件は看板ボードで管理します。アカデミーの〈システム操作〉コースに手順があります。' },
        { k: ['集客', '広告', '送客', 'リード獲得'], a: 'BUYMOブランドのLP・地域SEO・WEB広告から見込み客を送客します。' },
        { k: ['研修', '学習', '勉強', 'マニュアル'], a: 'アカデミー（partner-academy.html）で動画＋テキスト研修を受講できます。' },
        { k: ['トラブル', 'クレーム', '対応'], a: 'クレーム時は対応履歴に記録のうえ、重大案件は本部へ連絡してください。' }
      ],
      fallback: '本部サポートにお繋ぎします。アカデミーやコミュニティもご活用ください。'
    }
  };

  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

  var root = el('div', 'cbot');
  root.innerHTML =
    '<button class="cbot-launch" aria-label="チャットを開く">💬</button>' +
    '<div class="cbot-panel" hidden>' +
      '<div class="cbot-head"><span class="cbot-title"></span>' +
        '<div class="cbot-mode"><button data-mode="user">ユーザー</button><button data-mode="partner">加盟店</button></div>' +
        '<button class="cbot-x" aria-label="閉じる">×</button></div>' +
      '<div class="cbot-log" id="cbotLog"></div>' +
      '<div class="cbot-chips" id="cbotChips"></div>' +
      '<form class="cbot-input"><input id="cbotIn" placeholder="メッセージを入力…" autocomplete="off"><button>送信</button></form>' +
    '</div>';
  document.body.appendChild(root);
  document.body.classList.add('has-bot');

  var panel = root.querySelector('.cbot-panel');
  var log = root.querySelector('#cbotLog');
  var chips = root.querySelector('#cbotChips');

  function add(role, text) {
    var m = el('div', 'cbot-msg ' + role, esc(text));
    log.appendChild(m); log.scrollTop = log.scrollHeight;
  }
  function respond(text) {
    var kb = KB[MODE]; var t = text.toLowerCase();
    for (var i = 0; i < kb.rules.length; i++) {
      var r = kb.rules[i];
      for (var j = 0; j < r.k.length; j++) { if (text.indexOf(r.k[j]) >= 0 || t.indexOf(r.k[j]) >= 0) return r.a; }
    }
    return kb.fallback;
  }
  function renderChips() {
    chips.innerHTML = '';
    KB[MODE].chips.forEach(function (c) {
      var b = el('button', 'cbot-chip', esc(c));
      b.addEventListener('click', function () { send(c); });
      chips.appendChild(b);
    });
  }
  function setMode(m) {
    MODE = m;
    root.querySelector('.cbot-title').textContent = '🐮 ' + KB[m].title;
    root.querySelectorAll('.cbot-mode button').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-mode') === m); });
    log.innerHTML = ''; add('bot', KB[m].greet); renderChips();
  }
  function aiAnswer(text, cb) {
    if (!BOT_ENDPOINT) { cb(null); return; }
    var name = '__bot' + Date.now();
    var done = false;
    window[name] = function (d) { done = true; cb(d && d.answer ? d.answer : null); try { delete window[name]; } catch (e) {} };
    var s = document.createElement('script');
    s.src = BOT_ENDPOINT + '?action=bot&mode=' + MODE + '&q=' + encodeURIComponent(text) + '&callback=' + name;
    s.onerror = function () { if (!done) cb(null); };
    document.body.appendChild(s);
    setTimeout(function () { if (!done) { done = true; cb(null); } }, 12000); // タイムアウト→ルールへ
  }
  function send(text) {
    text = (text || '').trim(); if (!text) return;
    add('user', text);
    if (BOT_ENDPOINT) {
      var typing = el('div', 'cbot-msg bot', '…'); log.appendChild(typing); log.scrollTop = log.scrollHeight;
      aiAnswer(text, function (a) { typing.remove(); add('bot', a || respond(text)); });
    } else {
      setTimeout(function () { add('bot', respond(text)); }, 250);
    }
  }

  root.querySelector('.cbot-launch').addEventListener('click', function () { panel.hidden = false; this.style.display = 'none'; if (!log.children.length) setMode(MODE); });
  root.querySelector('.cbot-x').addEventListener('click', function () { panel.hidden = true; root.querySelector('.cbot-launch').style.display = ''; });
  root.querySelectorAll('.cbot-mode button').forEach(function (b) { b.addEventListener('click', function () { setMode(b.getAttribute('data-mode')); }); });
  root.querySelector('.cbot-input').addEventListener('submit', function (e) { e.preventDefault(); var i = document.getElementById('cbotIn'); send(i.value); i.value = ''; });

  setMode(MODE);
})();
