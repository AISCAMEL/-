/* reports-page.js ― 試算表・貸借対照表(BS)・損益計算書(PL) */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;
  const CAT = A.accounts.CATEGORIES;

  /* ---- 試算表 ---------------------------------------------------------- */
  ui.register('trialbalance', async () => {
    const journals = await S.journals.loadAll();
    const p = A.app.period();
    const tb = R.trialBalance(journals, p.start, p.end);
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('合計残高試算表', [
      el('button.btn.sm', { text: '印刷', onclick: () => window.print() }),
    ]));
    const card = el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]);
    card.appendChild(ui.table([
      { key: 'code', label: 'コード', render: (r) => r.code },
      { key: 'name', label: '勘定科目', render: (r) => r.name },
      { key: 'cat', label: '区分', render: (r) => CAT[r.category].label },
      { key: 'debit', label: '借方合計', align: 'right', render: (r) => U.yen(r.debit) },
      { key: 'credit', label: '貸方合計', align: 'right', render: (r) => U.yen(r.credit) },
      { key: 'bal', label: '残高', align: 'right', render: (r) => U.yenSigned(r.balance) },
    ], tb.rows, {
      empty: 'この期間の仕訳はありません',
      foot: el('tr.total-row', {}, [
        el('td', { text: '合計', colspan: 3 }),
        el('td.right', { text: '¥' + U.yen(tb.sum.debit) }),
        el('td.right', { text: '¥' + U.yen(tb.sum.credit) }),
        el('td.right', { text: tb.balanced ? '✓ 一致' : '不一致' }),
      ]),
    }));
    wrap.appendChild(card);
    return wrap;
  });

  /* ---- BS / PL --------------------------------------------------------- */
  const sectionTable = (title, items, total, totalLabel) => {
    const rows = items.map((i) => el('tr', {}, [
      el('td', { text: i.name }),
      el('td.right', { text: '¥' + U.yenSigned(i.amount) }),
    ]));
    return el('table.grid.report-table', {}, [
      el('thead', {}, [el('tr', {}, [el('th', { text: title }), el('th.right', { text: '金額' })])]),
      el('tbody', {}, rows.length ? rows : [el('tr', {}, [el('td', { colspan: 2, class: 'empty', text: '—' })])]),
      el('tfoot', {}, [el('tr.total-row', {}, [
        el('td', { text: totalLabel || ('　' + title + '合計') }),
        el('td.right', { text: '¥' + U.yenSigned(total) }),
      ])]),
    ]);
  };

  ui.register('statements', async () => {
    const journals = await S.journals.loadAll();
    const p = A.app.period();
    const st = R.statements(journals, p.start, p.end);
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('決算書（BS / PL）', [
      el('button.btn.sm', { text: '印刷', onclick: () => window.print() }),
    ]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    // 損益計算書
    const pl = st.pl;
    wrap.appendChild(el('div.card.report-card', {}, [
      el('h2', { text: '損益計算書（P/L）' }),
      el('div.two-col', {}, [
        sectionTable('費用', pl.expense.items, pl.expense.total),
        sectionTable('収益', pl.revenue.items, pl.revenue.total),
      ]),
      el('div.result-line.' + (pl.netIncome >= 0 ? 'good' : 'bad'),
        { html: `当期純利益　<b>¥${U.yenSigned(pl.netIncome)}</b>` }),
    ]));

    // 貸借対照表
    const bs = st.bs;
    const rightItems = [...bs.liability.items, ...bs.equity.items,
      { name: '当期純利益', amount: bs.netIncome }];
    wrap.appendChild(el('div.card.report-card', {}, [
      el('h2', { text: '貸借対照表（B/S）' }),
      el('div.two-col', {}, [
        sectionTable('資産の部', bs.asset.items, bs.asset.total, '資産合計'),
        sectionTable('負債・純資産の部', rightItems, bs.liabilityAndEquity, '負債・純資産合計'),
      ]),
      el('div.result-line.' + (bs.balanced ? 'good' : 'bad'),
        { html: bs.balanced ? '✓ 貸借一致' : '⚠ 貸借不一致（仕訳をご確認ください）' }),
    ]));
    return wrap;
  });
})();
