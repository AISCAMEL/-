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

    // 予定の集約（未入金請求書＝自動の入金予定 ＋ 定期請求の将来見込み ＋ 手入力の予定）
    const pad = (x) => String(x).padStart(2, '0');
    const addDays = (ds, n) => { const d = new Date(ds + 'T00:00:00'); d.setDate(d.getDate() + n); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; };
    const items = [];
    invoices.filter((iv) => iv.type === 'invoice' && iv.posted && !iv.paid).forEach((iv) => {
      items.push({ date: iv.dueDate || iv.date, label: `入金予定：${iv.partnerName || ''}`, amount: S.invoices.calc(iv).total, auto: true });
    });
    // 定期請求テンプレートから、今後4か月の発行予定を入金見込みとして投影（既発行月は除く）
    (S.settings.get().recurringInvoices || []).forEach((t) => {
      const amt = S.invoices.calc({ items: t.items || [] }).total; if (!amt) return;
      const day = Math.min(28, Math.max(1, Number(t.day) || 1));
      const base = new Date(today + 'T00:00:00');
      for (let k = 0; k < 4; k++) {
        const d = new Date(base.getFullYear(), base.getMonth() + k, day);
        const ds = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(day)}`;
        if (ds < today) continue;
        if (t.lastGenerated && ds.slice(0, 7) <= t.lastGenerated) continue; // 既発行分は請求書側に計上済み
        items.push({ date: ds, label: `定期請求予定：${t.partnerName || ''}`, amount: amt, auto: true });
      }
    });
    plans().forEach((p) => items.push({ id: p.id, date: p.date, label: p.label, amount: p.amount }));
    const future = items.filter((i) => (i.date || '') >= today).sort((a, b) => (a.date || '').localeCompare(b.date || ''));

    let bal = startCash;
    const rows = future.map((i) => { bal += i.amount; return { ...i, balance: bal }; });

    // 週次の残高見通し（12週）
    const weeks = [];
    for (let i = 0; i < 12; i++) {
      const end = addDays(today, (i + 1) * 7);
      const b = startCash + future.filter((f) => f.date <= end).reduce((s, f) => s + f.amount, 0);
      weeks.push({ label: end.slice(5).replace('-', '/'), value: b, end });
    }
    const minW = weeks.reduce((m, w) => (w.value < m.value ? w : m), { value: startCash, end: today });
    const minBal = Math.min(startCash, minW.value);

    wrap.appendChild(el('div.card', {}, [
      el('div.big-num', { text: '現在の現預金 ¥' + U.yen(startCash) }),
      el('p.muted.small', { text: '未入金の請求書・定期請求の見込み・手入力の予定から、今後12週間の現預金残高を見通します。' }),
    ]));

    // 残高見通しグラフ
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '残高見通し（今後12週）' }),
      ui.lineChart(weeks, { height: 210, color: '#0ea5e9', labelFmt: (l) => l }),
      el('p.result-line.' + (minBal < 0 ? 'bad' : 'muted'), { text: minBal < 0
        ? `⚠ 最低残高見込は ¥${U.yenSigned(minBal)}（${U.fmtDate(minW.end)}頃）。資金手当てをご検討ください。`
        : `最低残高見込：¥${U.yen(minBal)}（${U.fmtDate(minW.end)}頃）` }),
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
