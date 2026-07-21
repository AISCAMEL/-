/* =========================================================================
 * app.js  ―  ブートストラップ（初期化・ナビ・期間状態）※最後に読み込む
 * ========================================================================= */
window.A = window.A || {};

A.app = (function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;

  // 期間状態（localStorage に保持）。初期値は当期（会計年度）。
  let _period = null;
  const PKEY = 'kaikei_period';
  const period = () => {
    if (_period) return _period;
    try { _period = JSON.parse(localStorage.getItem(PKEY)); } catch (e) { _period = null; }
    if (!_period) {
      const s = S.settings.get();
      _period = U.fiscalRange(U.today(), s.fiscalStartMonth || 4);
    }
    return _period;
  };
  const setPeriod = (p) => { _period = p; try { localStorage.setItem(PKEY, JSON.stringify(p)); } catch (e) {} };

  // ナビゲーション定義
  const NAV = [
    { path: 'dashboard', label: 'ダッシュボード', icon: '🏠' },
    { group: '取引' },
    { path: 'journal', label: '仕訳帳', icon: '📒' },
    { path: 'expenses', label: '経費・入出金', icon: '💴' },
    { path: 'invoices', label: '請求書', icon: '📄' },
    { path: 'estimates', label: '見積書', icon: '📝' },
    { path: 'partners', label: '取引先', icon: '🏢' },
    { group: 'レポート' },
    { path: 'ledger', label: '総勘定元帳', icon: '📚' },
    { path: 'trialbalance', label: '試算表', icon: '⚖️' },
    { path: 'statements', label: '決算書(BS/PL)', icon: '📊' },
    { path: 'tax', label: '消費税集計', icon: '🧾' },
    { group: '' },
    { path: 'settings', label: '設定', icon: '⚙️' },
  ];

  const buildNav = () => {
    const nav = el('nav.side-nav');
    NAV.forEach((n) => {
      if (n.group !== undefined) { nav.appendChild(el('div.nav-group', { text: n.group })); return; }
      const a = el('a.nav-item', { href: '#/' + n.path, 'data-nav': n.path }, [
        el('span.nav-ico', { text: n.icon }), el('span', { text: n.label }),
      ]);
      nav.appendChild(a);
    });
    return nav;
  };

  const init = async () => {
    await A.db.seedIfEmpty();
    await S.settings.load();
    await S.accounts.loadAll();

    const s = S.settings.get();
    const app = document.getElementById('app');
    app.innerHTML = '';

    const sidebar = el('aside.sidebar', {}, [
      el('div.brand', {}, [
        el('div.brand-mark', { text: '会計' }),
        el('div.brand-text', {}, [el('div.brand-name', { text: s.name || '会計ソフト' }), el('div.brand-sub', { text: 'クラウド会計 (自社版)' })]),
      ]),
      buildNav(),
      el('div.side-foot', {}, [el('span.muted.small', { text: 'データはブラウザ内に保存' })]),
    ]);

    const outlet = el('main.content');
    ui.setOutlet(outlet);

    // モバイル用メニュートグル
    const topbar = el('header.topbar.no-print', {}, [
      el('button.icon-btn.menu-btn', { text: '☰', onclick: () => document.body.classList.toggle('nav-open') }),
      el('div.topbar-title', { text: 'クラウド会計' }),
    ]);

    app.appendChild(el('div.layout', {}, [sidebar, el('div.main-col', {}, [topbar, outlet])]));

    // ナビクリックでモバイルメニューを閉じる
    sidebar.addEventListener('click', (e) => { if (e.target.closest('.nav-item')) document.body.classList.remove('nav-open'); });

    ui.start();
  };

  return { init, period, setPeriod };
})();

document.addEventListener('DOMContentLoaded', () => {
  A.app.init().catch((e) => {
    console.error(e);
    document.getElementById('app').innerHTML =
      '<div style="padding:2rem;color:#b00">初期化に失敗しました: ' + (e && e.message) + '</div>';
  });
});
