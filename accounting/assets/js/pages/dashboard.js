/* dashboard.js ― ダッシュボード（当期サマリ・最近の仕訳・未処理請求書） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  const stat = (label, value, cls) =>
    el('div.stat.' + (cls || ''), {}, [
      el('div.stat-label', { text: label }),
      el('div.stat-value', { text: '¥' + U.yenSigned(value) }),
    ]);

  ui.register('dashboard', async () => {
    const p = A.app.period();
    const journals = await S.journals.loadAll();
    const invoices = await S.invoices.loadAll();
    const d = R.dashboard(journals, p.start, p.end);
    const s = S.settings.get();

    const unpaid = invoices.filter((i) => i.type === 'invoice' && !i.paid);
    const unpaidTotal = unpaid.reduce((sum, i) => sum + S.invoices.calc(i).total, 0);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('ダッシュボード', [
      el('span.muted', { text: s.name + '　' + U.fmtDate(p.start || '') + '〜' + U.fmtDate(p.end || '') }),
    ]));

    wrap.appendChild(el('div.stat-grid', {}, [
      stat('当期売上（収益）', d.revenue, 'good'),
      stat('当期費用', d.expense, 'warn'),
      stat('当期純利益', d.netIncome, d.netIncome >= 0 ? 'good' : 'bad'),
      stat('現預金残高', d.cash, ''),
    ]));

    wrap.appendChild(el('div.two-col', {}, [
      // 最近の仕訳
      (() => {
        const recent = [...journals].sort((a, b) =>
          (a.date === b.date ? b.no - a.no : b.date.localeCompare(a.date))).slice(0, 8);
        const card = el('div.card', {}, [
          el('div.card-head', {}, [el('h3', { text: '最近の仕訳' }),
            el('button.btn.sm', { text: '一覧へ', onclick: () => ui.go('journal') })]),
        ]);
        card.appendChild(ui.table([
          { key: 'date', label: '日付', render: (r) => U.fmtDate(r.date) },
          { key: 'desc', label: '摘要', render: (r) => r.description || '—' },
          { key: 'amt', label: '金額', align: 'right', render: (r) => '¥' + U.yen(S.journals.totals(r).debit) },
        ], recent, { empty: 'まだ仕訳がありません' }));
        return card;
      })(),
      // 未入金請求書
      (() => {
        const card = el('div.card', {}, [
          el('div.card-head', {}, [el('h3', { text: '未入金の請求書' }),
            el('button.btn.sm', { text: '請求書へ', onclick: () => ui.go('invoices') })]),
        ]);
        card.appendChild(el('div.big-num', { text: '¥' + U.yen(unpaidTotal), title: '未入金合計' }));
        card.appendChild(ui.table([
          { key: 'no', label: '番号', render: (r) => S.invoices.displayNo(r) },
          { key: 'pn', label: '取引先', render: (r) => r.partnerName || '—' },
          { key: 'amt', label: '金額', align: 'right', render: (r) => '¥' + U.yen(S.invoices.calc(r).total) },
        ], unpaid.slice(0, 6), { empty: '未入金はありません' }));
        return card;
      })(),
    ]));

    // クイックアクション
    wrap.appendChild(el('div.quick-row', {}, [
      el('button.btn.primary', { text: '＋ 仕訳を入力', onclick: () => ui.go('journal?new=1') }),
      el('button.btn', { text: '＋ 経費を登録', onclick: () => ui.go('expenses?new=1') }),
      el('button.btn', { text: '＋ 請求書を作成', onclick: () => ui.go('invoices?new=invoice') }),
    ]));
    return wrap;
  });
})();
