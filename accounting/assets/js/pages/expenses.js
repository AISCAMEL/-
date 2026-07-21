/* expenses.js ― 経費・入出金（かんたん入力→仕訳を自動生成） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;
  const TAX = A.accounts.TAX_CATEGORIES;

  // 相手勘定（支払・入金手段）の候補
  const PAY_ACCOUNTS = ['100', '110', '210', '230', '120', '200'];

  const optionsFor = (codes, value) => {
    const sel = el('select');
    codes.forEach((c) => {
      const a = S.accounts.byCode(c); if (!a) return;
      const o = el('option', { value: c, text: a.name });
      if (c === value) o.selected = true;
      sel.appendChild(o);
    });
    return sel;
  };
  const accSelectByCategory = (cats, value) => {
    const sel = el('select');
    S.accounts.all().filter((a) => cats.includes(a.category)).forEach((a) => {
      const o = el('option', { value: a.code, text: a.name });
      if (a.code === value) o.selected = true;
      sel.appendChild(o);
    });
    return sel;
  };
  const taxSelect = (value) => {
    const sel = el('select');
    Object.keys(TAX).forEach((k) => {
      const o = el('option', { value: k, text: TAX[k].label });
      if (k === value) o.selected = true; sel.appendChild(o);
    });
    return sel;
  };

  const editor = (existing) => {
    // existing は source:'expense' の journal
    let dir = 'out'; // out=出金(費用), in=入金(収益)
    let mainCode = '560', payCode = '100', taxCode = 'purchase10', memo = '', date = U.today(), amount = 0, refId = null, id = null;
    if (existing) {
      id = existing.id; refId = existing.refId; date = existing.date; memo = existing.description;
      const dLine = existing.lines.find((l) => l.side === 'debit');
      const cLine = existing.lines.find((l) => l.side === 'credit');
      const dCat = S.accounts.category(dLine.account);
      if (dCat === 'expense') { dir = 'out'; mainCode = dLine.account; payCode = cLine.account; taxCode = dLine.tax; amount = dLine.amount; }
      else { dir = 'in'; mainCode = cLine.account; payCode = dLine.account; taxCode = cLine.tax; amount = cLine.amount; }
    }

    const dateI = el('input', { type: 'date', value: date });
    const dirSel = el('select', {}, [el('option', { value: 'out', text: '出金（費用の支払い）' }), el('option', { value: 'in', text: '入金（売上・その他収入）' })]);
    dirSel.value = dir;
    const mainWrap = el('span');
    const taxSel = taxSelect(taxCode);
    const paySel = optionsFor(PAY_ACCOUNTS, payCode);
    const amtI = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: amount ? U.yen(amount) : '', placeholder: '税込金額' });
    const memoI = el('input', { type: 'text', value: memo, placeholder: '摘要（例：ガソリン代）' });
    const preview = el('div.jsummary');

    const buildMain = () => {
      mainWrap.innerHTML = '';
      const sel = dirSel.value === 'out'
        ? accSelectByCategory(['expense'], mainCode)
        : accSelectByCategory(['revenue'], S.accounts.category(mainCode) === 'revenue' ? mainCode : '400');
      sel.addEventListener('change', () => { const a = S.accounts.byCode(sel.value); if (a) { taxSel.value = a.default_tax; } refresh(); });
      mainWrap._sel = sel;
      mainWrap.appendChild(sel);
    };
    const refresh = () => {
      const amt = U.parseYen(amtI.value);
      const t = TAX[taxSel.value];
      const inTax = t && t.kind !== 'none' ? U.taxIncludedPortion(amt, t.rate) : 0;
      preview.innerHTML = '';
      if (dirSel.value === 'out') {
        preview.appendChild(el('span', { html: `（借）${U.esc(S.accounts.name(mainWrap._sel.value))} ¥${U.yen(amt)}` }));
        preview.appendChild(el('span', { html: `（貸）${U.esc(S.accounts.name(paySel.value))} ¥${U.yen(amt)}` }));
      } else {
        preview.appendChild(el('span', { html: `（借）${U.esc(S.accounts.name(paySel.value))} ¥${U.yen(amt)}` }));
        preview.appendChild(el('span', { html: `（貸）${U.esc(S.accounts.name(mainWrap._sel.value))} ¥${U.yen(amt)}` }));
      }
      preview.appendChild(el('span.muted', { text: `うち消費税 ¥${U.yen(inTax)}` }));
    };
    dirSel.addEventListener('change', () => { buildMain(); refresh(); });
    taxSel.addEventListener('change', refresh);
    paySel.addEventListener('change', refresh);
    amtI.addEventListener('input', () => { amtI.value = amtI.value.replace(/[^\d,]/g, ''); refresh(); });
    amtI.addEventListener('blur', () => { amtI.value = amtI.value ? U.yen(U.parseYen(amtI.value)) : ''; });
    buildMain(); refresh();

    const body = el('div.editor', {}, [
      el('div.form-row', {}, [
        el('label', {}, [el('span', { text: '日付' }), dateI]),
        el('label', {}, [el('span', { text: '種別' }), dirSel]),
      ]),
      el('div.form-row', {}, [
        el('label.grow', {}, [el('span', { text: '科目' }), mainWrap]),
        el('label', {}, [el('span', { text: '税区分' }), taxSel]),
      ]),
      el('div.form-row', {}, [
        el('label', {}, [el('span', { text: '支払・入金手段' }), paySel]),
        el('label', {}, [el('span', { text: '金額(税込)' }), amtI]),
      ]),
      el('label', {}, [el('span', { text: '摘要' }), memoI]),
      el('div.preview-box', {}, [el('div.muted', { text: '生成される仕訳' }), preview]),
    ]);

    const save = async () => {
      const amt = U.parseYen(amtI.value);
      if (amt <= 0) return ui.toast('金額を入力してください', 'err');
      const mainC = mainWrap._sel.value, payC = paySel.value, t = taxSel.value;
      let lines;
      if (dirSel.value === 'out') {
        lines = [
          { side: 'debit', account: mainC, tax: t, amount: amt, memo: memoI.value },
          { side: 'credit', account: payC, tax: 'out', amount: amt },
        ];
      } else {
        lines = [
          { side: 'debit', account: payC, tax: 'out', amount: amt },
          { side: 'credit', account: mainC, tax: t, amount: amt, memo: memoI.value },
        ];
      }
      const rec = { id, refId: refId || id, source: 'expense', date: dateI.value, description: memoI.value, lines };
      if (existing) rec.no = existing.no;
      await S.journals.save(rec);
      // refId を自身のidに揃える（新規時）
      if (!existing) { rec.refId = rec.id; await S.journals.save(rec); }
      m.close();
      ui.toast('登録しました', 'ok');
      ui.renderRoute();
    };

    const m = ui.modal(existing ? '入出金の編集' : '入出金の登録', body, {
      footer: [
        el('button.btn', { text: 'キャンセル', onclick: () => m.close() }),
        el('button.btn.primary', { text: '保存', onclick: save }),
      ],
    });
  };

  ui.register('expenses', async (q) => {
    const journals = await S.journals.loadAll();
    if (q.new) setTimeout(() => editor(null), 0);
    const p = A.app.period();
    const list = journals.filter((j) => j.source === 'expense' && U.inRange(j.date, p.start, p.end))
      .sort((a, b) => (a.date === b.date ? b.no - a.no : b.date.localeCompare(a.date)));

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('経費・入出金', [
      el('button.btn.primary', { text: '＋ 入出金を登録', onclick: () => editor(null) }),
    ]));
    const card = el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]);
    card.appendChild(ui.table([
      { key: 'date', label: '日付', render: (r) => U.fmtDate(r.date) },
      {
        key: 'kind', label: '種別', render: (r) => {
          const d = r.lines.find((l) => l.side === 'debit');
          return S.accounts.category(d.account) === 'expense'
            ? el('span.badge.out', { text: '出金' }) : el('span.badge.in', { text: '入金' });
        },
      },
      {
        key: 'acc', label: '科目', render: (r) => {
          const d = r.lines.find((l) => l.side === 'debit');
          const isOut = S.accounts.category(d.account) === 'expense';
          const main = isOut ? d.account : r.lines.find((l) => l.side === 'credit').account;
          return S.accounts.name(main);
        },
      },
      { key: 'desc', label: '摘要', render: (r) => r.description || '—' },
      { key: 'amt', label: '金額', align: 'right', render: (r) => '¥' + U.yen(S.journals.totals(r).debit) },
      {
        key: 'act', label: '', align: 'right', render: (r) => el('div.row-actions', {}, [
          el('button.icon-btn', { text: '✎', onclick: () => editor(r) }),
          el('button.icon-btn.del', {
            text: '🗑', onclick: async () => {
              if (await ui.confirm('削除しますか？')) { await S.journals.remove(r.id); ui.toast('削除しました'); ui.renderRoute(); }
            },
          }),
        ]),
      },
    ], list, { empty: 'この期間の入出金はありません' }));
    wrap.appendChild(card);
    return wrap;
  });
})();
