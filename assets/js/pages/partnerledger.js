/* partnerledger.js ― 取引先元帳（売掛金の請求・入金・残高） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  ui.register('partnerledger', async (q) => {
    const invoices = await S.invoices.loadAll();
    const journals = await S.journals.loadAll();
    const partners = await S.partners.loadAll();
    const p = A.app.period();

    // 取引先候補（請求書に出てくる名前＋マスタ）
    const names = new Set();
    invoices.filter((i) => i.type === 'invoice').forEach((i) => i.partnerName && names.add(i.partnerName));
    partners.forEach((pt) => names.add(pt.name));
    const list = [...names].sort((a, b) => a.localeCompare(b, 'ja'));
    const current = q.name || list[0] || '';

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('取引先元帳（売掛金）', []));

    // 取引先別の売掛残高サマリ
    const summary = list.map((nm) => {
      const L = R.partnerLedger(invoices, journals, nm, (iv) => S.invoices.calc(iv), null, null);
      return { name: nm, balance: L.closing };
    }).filter((r) => r.balance !== 0);
    if (summary.length) {
      const scard = el('div.card', {}, [el('h2', { text: '取引先別 売掛金残高' })]);
      scard.appendChild(ui.table([
        { key: 'name', label: '取引先', render: (r) => el('a', { href: '#/partnerledger?name=' + encodeURIComponent(r.name), text: r.name }) },
        { key: 'bal', label: '売掛金残高', align: 'right', render: (r) => '¥' + U.yenSigned(r.balance) },
      ], summary, {
        foot: el('tr.total-row', {}, [el('td', { text: '合計' }), el('td.right', { text: '¥' + U.yenSigned(summary.reduce((s, r) => s + r.balance, 0)) })]),
      }));
      wrap.appendChild(scard);
    }

    // 個別元帳
    const sel = el('select');
    if (!list.length) sel.appendChild(el('option', { text: '（取引先がありません）' }));
    list.forEach((nm) => { const o = el('option', { value: nm, text: nm }); if (nm === current) o.selected = true; sel.appendChild(o); });
    sel.addEventListener('change', () => ui.go('partnerledger?name=' + encodeURIComponent(sel.value)));

    const card = el('div.card', {}, [
      el('div.card-head', {}, [el('div.inline', {}, [el('span.muted', { text: '取引先' }), sel])]),
      ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); }),
    ]);
    if (current) {
      const L = R.partnerLedger(invoices, journals, current, (iv) => S.invoices.calc(iv), p.start, p.end);
      card.appendChild(ui.table([
        { key: 'date', label: '日付', render: (r) => U.fmtDate(r.date) },
        { key: 'desc', label: '摘要', render: (r) => r.desc },
        { key: 'debit', label: '請求（借方）', align: 'right', render: (r) => r.debit ? U.yen(r.debit) : '' },
        { key: 'credit', label: '入金（貸方）', align: 'right', render: (r) => r.credit ? U.yen(r.credit) : '' },
        { key: 'bal', label: '残高', align: 'right', render: (r) => U.yenSigned(r.balance) },
      ], L.rows, {
        empty: 'この期間の取引はありません',
        foot: el('tr.total-row', {}, [
          el('td', { text: '合計', colspan: 2 }),
          el('td.right', { text: '¥' + U.yen(L.totalDebit) }),
          el('td.right', { text: '¥' + U.yen(L.totalCredit) }),
          el('td.right', { text: '¥' + U.yenSigned(L.closing) }),
        ]),
      }));
    }
    wrap.appendChild(card);
    return wrap;
  });
})();
