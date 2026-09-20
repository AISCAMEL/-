/* cashforecast.js ― 資金繰り予定表（現預金＋入金予定・支払予定の見込み残高） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;
  const CAT = A.accounts.CATEGORIES;

  const plans = () => S.settings.get().cashPlans || [];
  const savePlans = (list) => S.settings.save({ cashPlans: list });

  const cashAsOf = (journals, date) => {
    let b = 0;
    journals.forEach((j) => {
      if (date && j.date > date) return;
      (j.lines || []).forEach((l) => {
        if (l.account !== '100' && l.account !== '110') return;
        b += (l.side === 'debit' ? 1 : -1) * l.amount; // 現預金は借方増加
      });
    });
    return b;
  };

  ui.register('cashforecast', async () => {
    const journals = await S.journals.loadAll();
    const invoices = await S.invoices.loadAll();
    const today = U.today();
    const startCash = cashAsOf(journals, today);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('資金繰り予定表', [
      el('button.btn.primary', { text: '＋ 予定を追加', onclick: () => addPlan() }),
    ]));

    async function addPlan() {
      const date = el('input', { type: 'date', value: today });
      const label = el('input', { type: 'text', placeholder: '例：家賃支払い / 借入返済' });
      const dir = el('select', {}, [el('option', { value: 'out', text: '出金（支払）' }), el('option', { value: 'in', text: '入金' })]);
      const amt = el('input.amt-in', { type: 'text', inputmode: 'numeric', placeholder: '金額' });
      amt.addEventListener('blur', () => { amt.value = amt.value ? U.yen(U.parseYen(amt.value)) : ''; });
      const body = el('div.editor', {}, [
        el('div.form-row', {}, [el('label', {}, [el('span', { text: '予定日' }), date]), el('label', {}, [el('span', { text: '区分' }), dir]), el('label', {}, [el('span', { text: '金額' }), amt])]),
        el('label', {}, [el('span', { text: '内容' }), label]),
      ]);
      const m = ui.modal('入出金予定の追加', body, {
        footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', {
          text: '追加', onclick: async () => {
            const v = U.parseYen(amt.value); if (v <= 0) return ui.toast('金額を入力してください', 'err');
            const next = [...plans(), { id: U.uid('cp'), date: date.value, label: label.value || '(予定)', amount: dir.value === 'out' ? -v : v }];
            await savePlans(next); m.close(); ui.toast('追加しました', 'ok'); ui.renderRoute();
          },
        })],
      });
    }

    // 予定の集約（未入金請求書＝自動の入金予定 ＋ 手入力の予定）
    const items = [];
    invoices.filter((iv) => iv.type === 'invoice' && iv.posted && !iv.paid).forEach((iv) => {
      items.push({ date: iv.dueDate || iv.date, label: `入金予定：${iv.partnerName || ''}`, amount: S.invoices.calc(iv).total, auto: true });
    });
    plans().forEach((p) => items.push({ id: p.id, date: p.date, label: p.label, amount: p.amount }));
    const future = items.filter((i) => (i.date || '') >= today).sort((a, b) => (a.date || '').localeCompare(b.date || ''));

    let bal = startCash;
    const rows = future.map((i) => { bal += i.amount; return { ...i, balance: bal }; });

    wrap.appendChild(el('div.card', {}, [
      el('div.big-num', { text: '現在の現預金 ¥' + U.yen(startCash) }),
      el('p.muted.small', { text: '未入金の請求書（入金予定）と、手入力した入出金予定から、今後の現預金残高の見込みを表示します。' }),
    ]));

    const card = el('div.card');
    card.appendChild(ui.table([
      { key: 'date', label: '予定日', render: (r) => U.fmtDate(r.date) },
      { key: 'label', label: '内容', render: (r) => r.label + (r.auto ? '' : '') },
      { key: 'in', label: '入金', align: 'right', render: (r) => r.amount > 0 ? U.yen(r.amount) : '' },
      { key: 'out', label: '出金', align: 'right', render: (r) => r.amount < 0 ? U.yen(-r.amount) : '' },
      { key: 'bal', label: '残高見込', align: 'right', render: (r) => el('span' + (r.balance < 0 ? '.neg' : ''), { text: '¥' + U.yenSigned(r.balance) }) },
      {
        key: 'act', label: '', align: 'right', render: (r) => r.auto ? el('span.badge', { text: '請求' }) : el('button.icon-btn.del', {
          text: '🗑', onclick: async () => { await savePlans(plans().filter((x) => x.id !== r.id)); ui.toast('削除しました'); ui.renderRoute(); },
        }),
      },
    ], rows, { empty: '今後の予定はありません' }));
    if (rows.some((r) => r.balance < 0)) card.appendChild(el('p.result-line.bad', { text: '⚠ 残高見込がマイナスになる時点があります。資金手当てをご検討ください。' }));
    wrap.appendChild(card);
    return wrap;
  });
})();
