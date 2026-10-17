/* ledger.js ― 総勘定元帳（勘定科目ごとの明細と残高） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  ui.register('ledger', async (q) => {
    const journals = await S.journals.loadAll();
    const p = A.app.period();
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('総勘定元帳', []));

    // 使われている科目のみ選択肢に出す（先頭は最初の科目）
    const used = new Set();
    journals.forEach((j) => (j.lines || []).forEach((l) => used.add(l.account)));
    const codes = S.accounts.all().filter((a) => used.has(a.code));
    const current = q.code || (codes[0] && codes[0].code) || '';

    const sel = el('select');
    if (!codes.length) sel.appendChild(el('option', { text: '（仕訳がありません）' }));
    codes.forEach((a) => {
      const o = el('option', { value: a.code, text: `${a.code} ${a.name}` });
      if (a.code === current) o.selected = true;
      sel.appendChild(o);
    });
    sel.addEventListener('change', () => ui.go('ledger?code=' + sel.value));

    const card = el('div.card', {}, [
      el('div.card-head', {}, [
        el('div.inline', {}, [el('span.muted', { text: '勘定科目' }), sel]),
      ]),
      ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); }),
    ]);

    if (current) {
      const L = R.ledger(journals, current, p.start, p.end);
      card.appendChild(ui.table([
        { key: 'date', label: '日付', render: (r) => U.fmtDate(r.date) },
        { key: 'desc', label: '摘要', render: (r) => r.description || r.memo || '—' },
        { key: 'debit', label: '借方', align: 'right', render: (r) => r.debit ? U.yen(r.debit) : '' },
        { key: 'credit', label: '貸方', align: 'right', render: (r) => r.credit ? U.yen(r.credit) : '' },
        { key: 'balance', label: '残高', align: 'right', render: (r) => U.yenSigned(r.balance) },
      ], L.rows, {
        empty: 'この期間の明細はありません',
        foot: el('tr.total-row', {}, [
          el('td', { text: '期首残高 ¥' + U.yenSigned(L.opening), colspan: 3 }),
          el('td.right', { text: '期末残高' }),
          el('td.right', { text: '¥' + U.yenSigned(L.closing) }),
        ]),
      }));
      card.insertBefore(
        el('div.ledger-summary', { html: `期首残高 <b>¥${U.yenSigned(L.opening)}</b>　→　期末残高 <b>¥${U.yenSigned(L.closing)}</b>` }),
        card.querySelector('table'));
    }
    wrap.appendChild(card);
    return wrap;
  });
})();
