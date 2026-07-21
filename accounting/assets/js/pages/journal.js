/* journal.js ― 仕訳帳（複式簿記の入力・一覧） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;
  const TAX = A.accounts.TAX_CATEGORIES;
  const SRC_LABEL = { invoice: '請求', expense: '入出金', import: '取込', depreciation: '減価償却', disposal: '除却売却', lease: 'リース', compression: '圧縮', opening: '期首', closing: '決算', payroll: '給与', manual: '手入力' };

  // 勘定科目 <select>
  const accountSelect = (value) => {
    const sel = el('select.acc-sel');
    sel.appendChild(el('option', { value: '', text: '勘定科目…' }));
    S.accounts.all().forEach((a) => {
      const o = el('option', { value: a.code, text: `${a.name}` });
      if (a.code === value) o.selected = true;
      sel.appendChild(o);
    });
    return sel;
  };
  const taxSelect = (value) => {
    const sel = el('select.tax-sel');
    Object.keys(TAX).forEach((k) => {
      const o = el('option', { value: k, text: TAX[k].label });
      if (k === (value || 'out')) o.selected = true;
      sel.appendChild(o);
    });
    return sel;
  };

  // 1行（借方 or 貸方）の入力行を作る
  const lineRow = (side, data, onChange, onRemove) => {
    data = data || {};
    const acc = accountSelect(data.account);
    const tax = taxSelect(data.tax);
    const amt = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: data.amount ? U.yen(data.amount) : '' });
    const memo = el('input.memo-in', { type: 'text', value: data.memo || '', placeholder: '摘要(任意)' });
    // 勘定科目を選んだら既定税区分を自動セット
    acc.addEventListener('change', () => {
      const a = S.accounts.byCode(acc.value);
      if (a) tax.value = a.default_tax;
      onChange();
    });
    tax.addEventListener('change', onChange);
    amt.addEventListener('input', () => { amt.value = amt.value.replace(/[^\d,]/g, ''); onChange(); });
    amt.addEventListener('blur', () => { amt.value = amt.value ? U.yen(U.parseYen(amt.value)) : ''; });
    const read = () => ({ side, account: acc.value, tax: tax.value, amount: U.parseYen(amt.value), memo: memo.value });
    const row = el('div.jline', {}, [
      el('span.jline-side.' + side, { text: side === 'debit' ? '借方' : '貸方' }),
      acc, tax, amt,
      el('button.icon-btn.del', { text: '×', title: '行を削除', onclick: () => onRemove() }),
    ]);
    row._read = read;
    row.appendChild(memo);
    return row;
  };

  // 仕訳エディタ（新規/編集）
  const editor = async (existing) => {
    const j = existing ? JSON.parse(JSON.stringify(existing)) : { date: U.today(), description: '', lines: [] };
    if (!j.lines.length) j.lines = [{ side: 'debit' }, { side: 'credit' }];

    const dateI = el('input', { type: 'date', value: j.date });
    const descI = el('input', { type: 'text', value: j.description || '', placeholder: '摘要（例：売上入金）' });
    const fileI = el('input', { type: 'file', accept: 'image/*,application/pdf' });
    const depts = S.settings.get().departments || [];
    const deptI = el('select');
    deptI.appendChild(el('option', { value: '', text: '（部門なし）' }));
    depts.forEach((d) => { const o = el('option', { value: d.id, text: d.name }); if (d.id === j.dept) o.selected = true; deptI.appendChild(o); });
    const debitBox = el('div.jlines');
    const creditBox = el('div.jlines');
    const summary = el('div.jsummary');

    const collect = () => {
      const lines = [];
      debitBox.querySelectorAll('.jline').forEach((r) => lines.push(r._read()));
      creditBox.querySelectorAll('.jline').forEach((r) => lines.push(r._read()));
      return lines.filter((l) => l.account || l.amount);
    };
    const refresh = () => {
      const lines = collect();
      let d = 0, c = 0;
      lines.forEach((l) => { if (l.side === 'debit') d += l.amount; else c += l.amount; });
      summary.innerHTML = '';
      summary.appendChild(el('span', { html: `借方合計 <b>¥${U.yen(d)}</b>` }));
      summary.appendChild(el('span', { html: `貸方合計 <b>¥${U.yen(c)}</b>` }));
      const diff = d - c;
      summary.appendChild(el('span.' + (diff === 0 && d > 0 ? 'ok' : 'ng'),
        { html: diff === 0 ? '✓ 貸借一致' : `差額 ¥${U.yen(Math.abs(diff))}` }));
    };
    const addLine = (box, side, data) => {
      const r = lineRow(side, data, refresh, () => { r.remove(); refresh(); });
      box.appendChild(r);
    };
    j.lines.filter((l) => l.side === 'debit').forEach((l) => addLine(debitBox, 'debit', l));
    j.lines.filter((l) => l.side === 'credit').forEach((l) => addLine(creditBox, 'credit', l));
    if (!debitBox.children.length) addLine(debitBox, 'debit', {});
    if (!creditBox.children.length) addLine(creditBox, 'credit', {});
    refresh();

    const body = el('div.editor', {}, [
      el('div.form-row', {}, [
        el('label', {}, [el('span', { text: '日付' }), dateI]),
        el('label.grow', {}, [el('span', { text: '摘要' }), descI]),
        depts.length ? el('label', {}, [el('span', { text: '部門' }), deptI]) : null,
        el('label', {}, [el('span', { text: '証憑(任意)' }), fileI]),
      ]),
      el('div.dc-cols', {}, [
        el('div.dc-col', {}, [
          el('div.dc-title.debit', { text: '借方（増：資産・費用）' }), debitBox,
          el('button.btn.sm', { text: '＋ 借方行を追加', onclick: () => { addLine(debitBox, 'debit', {}); refresh(); } }),
        ]),
        el('div.dc-col', {}, [
          el('div.dc-title.credit', { text: '貸方（増：負債・純資産・収益）' }), creditBox,
          el('button.btn.sm', { text: '＋ 貸方行を追加', onclick: () => { addLine(creditBox, 'credit', {}); refresh(); } }),
        ]),
      ]),
      summary,
    ]);

    const save = async () => {
      const lines = collect();
      const totals = S.journals.totals({ lines });
      if (!lines.length || !lines.every((l) => l.account && l.amount > 0)) {
        return ui.toast('各行に勘定科目と金額を入力してください', 'err');
      }
      if (!totals.balanced) return ui.toast('借方と貸方の合計が一致していません', 'err');
      const rec = { ...j, date: dateI.value, description: descI.value, dept: deptI.value || '', lines };
      const saved = await S.journals.save(rec);
      if (fileI.files[0] && A.attachToJournal) { try { await A.attachToJournal(saved, fileI.files[0]); } catch (e) { ui.toast('証憑の保存に失敗: ' + e.message, 'err'); } }
      m.close();
      ui.toast('仕訳を保存しました', 'ok');
      ui.renderRoute();
    };

    const m = ui.modal(existing ? '仕訳の編集' : '仕訳の入力', body, {
      footer: [
        el('button.btn', { text: 'キャンセル', onclick: () => m.close() }),
        el('button.btn.primary', { text: '保存', onclick: save }),
      ],
    });
  };

  ui.register('journal', async (q) => {
    const journals = await S.journals.loadAll();
    if (q.new) setTimeout(() => editor(null), 0);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('仕訳帳', [
      el('button.btn.primary', { text: '＋ 仕訳を入力', onclick: () => editor(null) }),
    ]));

    const p = A.app.period();
    const list = journals.filter((j) => U.inRange(j.date, p.start, p.end))
      .sort((a, b) => (a.date === b.date ? b.no - a.no : b.date.localeCompare(a.date)));

    wrap.appendChild(el('div.card', {}, [
      ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); }),
      ui.table([
        { key: 'no', label: 'No', align: 'right', render: (r) => r.no },
        { key: 'date', label: '日付', render: (r) => U.fmtDate(r.date) },
        { key: 'debit', label: '借方科目', render: (r) => (r.lines.filter((l) => l.side === 'debit').map((l) => S.accounts.name(l.account)).join(' / ')) },
        { key: 'credit', label: '貸方科目', render: (r) => (r.lines.filter((l) => l.side === 'credit').map((l) => S.accounts.name(l.account)).join(' / ')) },
        { key: 'desc', label: '摘要', render: (r) => r.description || '—' },
        { key: 'amt', label: '金額', align: 'right', render: (r) => '¥' + U.yen(S.journals.totals(r).debit) },
        { key: 'src', label: '', render: (r) => r.source !== 'manual' ? el('span.badge', { text: SRC_LABEL[r.source] || r.source }) : '' },
        {
          key: 'act', label: '', align: 'right', render: (r) => {
            const box = el('div.row-actions');
            if (r.source === 'manual') {
              box.appendChild(el('button.icon-btn', { text: '✎', title: '編集', onclick: (e) => { e.stopPropagation(); editor(r); } }));
            }
            box.appendChild(el('button.icon-btn.del', {
              text: '🗑', title: '削除', onclick: async (e) => {
                e.stopPropagation();
                if (await ui.confirm('この仕訳を削除しますか？')) { await S.journals.remove(r.id); ui.toast('削除しました'); ui.renderRoute(); }
              },
            }));
            return box;
          },
        },
      ], list, { empty: 'この期間の仕訳はありません' }),
    ]));
    return wrap;
  });

  // 他ページから使えるよう公開
  A.journalEditor = editor;
})();
