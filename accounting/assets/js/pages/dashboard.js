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
    const assets = await S.assets.loadAll();
    const d = R.dashboard(journals, p.start, p.end);
    const st = R.statements(journals, p.start, p.end);
    const s = S.settings.get();

    const unpaid = invoices.filter((i) => i.type === 'invoice' && !i.paid);
    const unpaidTotal = unpaid.reduce((sum, i) => sum + S.invoices.calc(i).total, 0);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('ダッシュボード', [
      el('span.muted', { text: s.name + '　' + U.fmtDate(p.start || '') + '〜' + U.fmtDate(p.end || '') }),
    ]));

    // やることリスト（アラート）
    const fy = U.fiscalRange(p.start || U.today(), s.fiscalStartMonth || 4);
    const today = U.today();
    const alerts = [];
    if (!st.bs.balanced) alerts.push({ level: 'bad', text: '貸借対照表が一致していません。仕訳をご確認ください。', to: 'statements' });
    const depPending = assets.filter((a) => !a.disposed && R.depForFiscalYear(a, s.fiscalStartMonth || 4, fy.start) &&
      !journals.some((j) => j.source === 'depreciation' && j.refId === a.id && U.inRange(j.date, fy.start, fy.end)));
    if (depPending.length) alerts.push({ level: 'warn', text: `当期の減価償却が未計上の固定資産が ${depPending.length} 件あります。`, to: 'assets' });
    const overdue = unpaid.filter((i) => i.dueDate && i.dueDate < today);
    if (overdue.length) alerts.push({ level: 'bad', text: `支払期限を過ぎた未入金の請求書が ${overdue.length} 件あります。`, to: 'invoices' });
    else if (unpaid.length) alerts.push({ level: 'warn', text: `未入金の請求書が ${unpaid.length} 件（¥${U.yen(unpaidTotal)}）あります。`, to: 'invoices' });
    const draftInv = invoices.filter((i) => i.type === 'invoice' && !i.posted);
    if (draftInv.length) alerts.push({ level: 'info', text: `売上未計上の請求書が ${draftInv.length} 件あります。`, to: 'invoices' });

    if (alerts.length) {
      wrap.appendChild(el('div.card.todo-card', {}, [
        el('h3', { text: '📌 やることリスト' }),
        el('div.todo-list', {}, alerts.map((a) => el('div.todo-item.' + a.level, {
          onclick: () => ui.go(a.to),
        }, [el('span.todo-dot'), el('span', { text: a.text }), el('span.todo-go', { text: '→' })]))),
      ]));
    }

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
