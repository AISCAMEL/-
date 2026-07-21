/* =========================================================================
 * ui.js  ―  ハッシュルーター・共通UI部品（トースト・モーダル・期間フィルタ）
 * ========================================================================= */
window.A = window.A || {};

A.ui = (function () {
  'use strict';
  const U = A.util;
  const el = U.el;

  /* ---- ルーター（#/journal 形式） ------------------------------------- */
  const routes = {};
  let _outlet = null;
  const register = (path, render) => { routes[path] = render; };
  const currentPath = () => (location.hash.replace(/^#\/?/, '').split('?')[0]) || 'dashboard';
  const queryParams = () => {
    const q = location.hash.split('?')[1] || '';
    const o = {};
    new URLSearchParams(q).forEach((v, k) => { o[k] = v; });
    return o;
  };
  const go = (path) => { location.hash = '#/' + path; };
  const setOutlet = (node) => { _outlet = node; };

  const renderRoute = async () => {
    const path = currentPath();
    const render = routes[path] || routes['dashboard'];
    if (!_outlet) return;
    _outlet.innerHTML = '';
    // ナビのアクティブ表示更新
    document.querySelectorAll('[data-nav]').forEach((a) => {
      a.classList.toggle('active', a.getAttribute('data-nav') === path);
    });
    const spinner = el('div.loading', { text: '読み込み中…' });
    _outlet.appendChild(spinner);
    try {
      const view = await render(queryParams());
      _outlet.innerHTML = '';
      if (view) _outlet.appendChild(view);
    } catch (e) {
      console.error(e);
      _outlet.innerHTML = '';
      _outlet.appendChild(el('div.card', { html: '<b>エラー:</b> ' + U.esc(e.message) }));
    }
    window.scrollTo(0, 0);
  };
  const start = () => {
    window.addEventListener('hashchange', renderRoute);
    renderRoute();
  };

  /* ---- トースト -------------------------------------------------------- */
  let _toastBox = null;
  const toast = (msg, kind) => {
    if (!_toastBox) {
      _toastBox = el('div.toast-box');
      document.body.appendChild(_toastBox);
    }
    const t = el('div.toast' + (kind ? '.' + kind : ''), { text: msg });
    _toastBox.appendChild(t);
    setTimeout(() => t.classList.add('show'), 10);
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 2600);
  };

  /* ---- モーダル -------------------------------------------------------- */
  const modal = (title, bodyNode, opts) => {
    opts = opts || {};
    const overlay = el('div.modal-overlay');
    const box = el('div.modal', {}, [
      el('div.modal-head', {}, [
        el('h3', { text: title }),
        el('button.icon-btn', { text: '×', title: '閉じる', onclick: () => close() }),
      ]),
      el('div.modal-body', {}, [bodyNode]),
    ]);
    if (opts.footer) box.appendChild(el('div.modal-foot', {}, opts.footer));
    overlay.appendChild(box);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    document.body.appendChild(overlay);
    document.body.classList.add('modal-open');
    function close() {
      overlay.remove();
      document.body.classList.remove('modal-open');
      if (opts.onClose) opts.onClose();
    }
    return { close, box };
  };

  const confirm = (msg) => new Promise((resolve) => {
    const m = modal('確認', el('p', { text: msg }), {
      footer: [
        el('button.btn', { text: 'キャンセル', onclick: () => { m.close(); resolve(false); } }),
        el('button.btn.danger', { text: 'OK', onclick: () => { m.close(); resolve(true); } }),
      ],
    });
  });

  /* ---- 共通部品 -------------------------------------------------------- */
  // ページ見出し（タイトル＋右側アクション）
  const pageHead = (title, actions) =>
    el('div.page-head', {}, [
      el('h1', { text: title }),
      el('div.page-actions', {}, actions || []),
    ]);

  // テーブル生成: cols=[{key,label,align,render}], rows=[obj]
  const table = (cols, rows, opts) => {
    opts = opts || {};
    const thead = el('tr', {}, cols.map((c) =>
      el('th' + (c.align ? '.' + c.align : ''), { text: c.label })));
    const body = rows.map((r, i) => el('tr', opts.onRow ? { onclick: () => opts.onRow(r, i) } : {},
      cols.map((c) => {
        const v = c.render ? c.render(r, i) : r[c.key];
        const td = el('td' + (c.align ? '.' + c.align : ''));
        if (v instanceof Node) td.appendChild(v);
        else td.innerHTML = v == null ? '' : (c.html ? v : U.esc(v));
        return td;
      })));
    const t = el('table.grid' + (opts.className ? '.' + opts.className : ''), {}, [
      el('thead', {}, [thead]),
      el('tbody', {}, body.length ? body : [el('tr', {}, [
        el('td', { colspan: cols.length, class: 'empty', text: opts.empty || 'データがありません' }),
      ])]),
    ]);
    if (opts.foot) {
      t.appendChild(el('tfoot', {}, [opts.foot]));
    }
    return t;
  };

  // 期間フィルタUI（会計年度に連動）。onChange({start,end}) を呼ぶ。
  const periodBar = (state, onChange) => {
    const startI = el('input', { type: 'date', value: state.start || '' });
    const endI = el('input', { type: 'date', value: state.end || '' });
    const apply = () => onChange({ start: startI.value || null, end: endI.value || null });
    startI.addEventListener('change', apply);
    endI.addEventListener('change', apply);
    const thisFY = () => {
      const s = A.store.settings.get();
      const fy = U.fiscalRange(U.today(), s.fiscalStartMonth || 4);
      startI.value = fy.start; endI.value = fy.end; apply();
    };
    return el('div.period-bar', {}, [
      el('span.muted', { text: '期間' }),
      startI, el('span', { text: '〜' }), endI,
      el('button.btn.sm', { text: '今期', onclick: thisFY }),
      el('button.btn.sm', { text: '全期間', onclick: () => { startI.value = ''; endI.value = ''; apply(); } }),
    ]);
  };

  const money = (n, cls) => {
    const span = el('span.num' + (cls ? '.' + cls : ''));
    span.textContent = U.yenSigned(n);
    if (Number(n) < 0) span.classList.add('neg');
    return span;
  };

  return {
    register, go, start, setOutlet, renderRoute, currentPath, queryParams,
    toast, modal, confirm, pageHead, table, periodBar, money,
  };
})();
