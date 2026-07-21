/* tax.js ― 消費税集計（インボイス対応・税率別／納付税額の試算） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  const rateTable = (title, byRate, isSales) => {
    const rows = Object.keys(byRate).sort().reverse().map((r) => {
      const b = byRate[r];
      return el('tr', {}, [
        el('td', { text: r + '%' }),
        el('td.right', { text: '¥' + U.yen(b.gross - b.tax) }),
        el('td.right', { text: '¥' + U.yen(b.tax) }),
        el('td.right', { text: '¥' + U.yen(b.gross) }),
      ]);
    });
    return el('table.grid.report-table', {}, [
      el('thead', {}, [el('tr', {}, [el('th', { text: title }), el('th.right', { text: '税抜' }), el('th.right', { text: '消費税' }), el('th.right', { text: '税込' })])]),
      el('tbody', {}, rows.length ? rows : [el('tr', {}, [el('td', { colspan: 4, class: 'empty', text: '対象取引なし' })])]),
    ]);
  };

  ui.register('tax', async () => {
    const journals = await S.journals.loadAll();
    const p = A.app.period();
    const t = R.taxSummary(journals, p.start, p.end);
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('消費税集計（インボイス対応）', [
      el('button.btn.sm', { text: '印刷', onclick: () => window.print() }),
    ]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    wrap.appendChild(el('div.card.report-card', {}, [
      el('h2', { text: '税率別 集計' }),
      el('div.two-col', {}, [
        rateTable('課税売上', t.salesByRate, true),
        rateTable('課税仕入', t.purchaseByRate, false),
      ]),
    ]));

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '消費税 納付額の試算' }),
      el('table.grid.report-table', {}, [
        el('tbody', {}, [
          el('tr', {}, [el('td', { text: '仮受消費税（売上に係る消費税）' }), el('td.right', { text: '¥' + U.yen(t.salesTax) })]),
          el('tr', {}, [el('td', { text: '仮払消費税（仕入税額控除）' }), el('td.right', { text: '△¥' + U.yen(t.purchaseTax) })]),
          el('tr.total-row', {}, [el('td', { text: '差引 納付税額' + (t.payable < 0 ? '（還付）' : '') }), el('td.right', { text: '¥' + U.yenSigned(t.payable) })]),
        ]),
      ]),
      el('p.muted.small', { text: '※ 税込経理方式・原則課税での概算です。実際の申告額は端数処理・簡易課税・特例により異なる場合があります。' }),
    ]));
    return wrap;
  });
})();
