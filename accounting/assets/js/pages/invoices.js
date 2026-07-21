/* invoices.js ― 請求書・見積書（作成／印刷(PDF)／仕訳連携／入金記録） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store;

  const RATE_OPTS = [
    { v: 10, label: '10%' },
    { v: 8, label: '8%(軽減)' },
    { v: 0, label: '非課税/対象外' },
  ];

  /* ---- 明細行 ---------------------------------------------------------- */
  const itemRow = (it, onChange, onRemove) => {
    it = it || { name: '', qty: 1, unitPrice: 0, taxRate: 10 };
    const name = el('input', { type: 'text', value: it.name || '', placeholder: '品目・摘要' });
    const qty = el('input.qty-in', { type: 'number', step: '1', value: it.qty != null ? it.qty : 1 });
    const price = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: it.unitPrice ? U.yen(it.unitPrice) : '' });
    const rate = el('select.rate-sel');
    RATE_OPTS.forEach((r) => { const o = el('option', { value: r.v, text: r.label }); if (r.v === Number(it.taxRate)) o.selected = true; rate.appendChild(o); });
    const amount = el('span.line-amt');
    const read = () => ({ name: name.value, qty: Number(qty.value) || 0, unitPrice: U.parseYen(price.value), taxRate: Number(rate.value) });
    const upd = () => { const r = read(); amount.textContent = '¥' + U.yen(r.qty * r.unitPrice); onChange(); };
    [name, qty, rate].forEach((n) => n.addEventListener('input', upd));
    price.addEventListener('input', () => { price.value = price.value.replace(/[^\d,]/g, ''); upd(); });
    price.addEventListener('blur', () => { price.value = price.value ? U.yen(U.parseYen(price.value)) : ''; });
    upd();
    const row = el('div.item-row', {}, [
      name, qty, price, rate, amount,
      el('button.icon-btn.del', { text: '×', onclick: () => onRemove() }),
    ]);
    row._read = read;
    return row;
  };

  /* ---- エディタ -------------------------------------------------------- */
  const editor = async (existing, type) => {
    const partners = await S.partners.loadAll();
    const inv = existing ? JSON.parse(JSON.stringify(existing))
      : { type: type || 'invoice', date: U.today(), dueDate: '', partnerId: '', partnerName: '', items: [], note: '' };
    if (!inv.items.length) inv.items = [{ name: '', qty: 1, unitPrice: 0, taxRate: 10 }];

    const dateI = el('input', { type: 'date', value: inv.date });
    const dueI = el('input', { type: 'date', value: inv.dueDate || '' });
    const pSel = el('select');
    pSel.appendChild(el('option', { value: '', text: '（直接入力）' }));
    partners.forEach((p) => { const o = el('option', { value: p.id, text: p.name }); if (p.id === inv.partnerId) o.selected = true; pSel.appendChild(o); });
    const pName = el('input', { type: 'text', value: inv.partnerName || '', placeholder: '取引先名' });
    pSel.addEventListener('change', () => { const p = partners.find((x) => x.id === pSel.value); if (p) pName.value = p.name; });

    const itemsBox = el('div.items-box');
    const totalsBox = el('div.inv-totals');
    const collect = () => { const arr = []; itemsBox.querySelectorAll('.item-row').forEach((r) => arr.push(r._read())); return arr.filter((i) => i.name || i.unitPrice); };
    const refresh = () => {
      const c = S.invoices.calc({ items: collect() });
      totalsBox.innerHTML = '';
      totalsBox.appendChild(el('div', { html: `小計（税抜） <b>¥${U.yen(c.net)}</b>` }));
      Object.keys(c.buckets).sort().reverse().forEach((r) => {
        if (Number(r) === 0) return;
        totalsBox.appendChild(el('div.muted', { html: `${r}%対象 ¥${U.yen(c.buckets[r].net)}　消費税 ¥${U.yen(c.buckets[r].tax)}` }));
      });
      totalsBox.appendChild(el('div', { html: `消費税 <b>¥${U.yen(c.tax)}</b>` }));
      totalsBox.appendChild(el('div.grand', { html: `合計 <b>¥${U.yen(c.total)}</b>` }));
    };
    const addItem = (it) => { const r = itemRow(it, refresh, () => { r.remove(); refresh(); }); itemsBox.appendChild(r); };
    inv.items.forEach(addItem); refresh();

    const noteI = el('textarea', { rows: 2, placeholder: '備考（振込先・支払条件など）' }); noteI.value = inv.note || '';

    const body = el('div.editor', {}, [
      el('div.form-row', {}, [
        el('label', {}, [el('span', { text: inv.type === 'estimate' ? '見積日' : '請求日' }), dateI]),
        el('label', {}, [el('span', { text: inv.type === 'estimate' ? '有効期限' : '支払期限' }), dueI]),
      ]),
      el('div.form-row', {}, [
        el('label', {}, [el('span', { text: '取引先を選択' }), pSel]),
        el('label.grow', {}, [el('span', { text: '取引先名' }), pName]),
      ]),
      el('div.items-head', {}, [el('span', { text: '品目' }), el('span', { text: '数量' }), el('span', { text: '単価' }), el('span', { text: '税率' }), el('span', { text: '金額' }), el('span')]),
      itemsBox,
      el('button.btn.sm', { text: '＋ 明細を追加', onclick: () => addItem() }),
      totalsBox,
      el('label', {}, [el('span', { text: '備考' }), noteI]),
    ]);

    const save = async (afterPrint) => {
      const items = collect();
      if (!pName.value) return ui.toast('取引先名を入力してください', 'err');
      if (!items.length) return ui.toast('明細を1行以上入力してください', 'err');
      const rec = { ...inv, date: dateI.value, dueDate: dueI.value, partnerId: pSel.value, partnerName: pName.value, items, note: noteI.value };
      const saved = await S.invoices.save(rec);
      m.close();
      ui.toast('保存しました', 'ok');
      if (afterPrint) printView(saved);
      ui.renderRoute();
    };

    const m = ui.modal((existing ? '編集' : '新規') + '：' + (inv.type === 'estimate' ? '見積書' : '請求書'), body, {
      footer: [
        el('button.btn', { text: 'キャンセル', onclick: () => m.close() }),
        el('button.btn', { text: '保存して印刷', onclick: () => save(true) }),
        el('button.btn.primary', { text: '保存', onclick: () => save(false) }),
      ],
    });
  };

  /* ---- 印刷/PDFプレビュー ---------------------------------------------- */
  const printView = (inv) => {
    const s = S.settings.get();
    const c = S.invoices.calc(inv);
    const title = inv.type === 'estimate' ? '見 積 書' : '請 求 書';
    const rateRows = Object.keys(c.buckets).sort().reverse()
      .filter((r) => Number(r) > 0)
      .map((r) => `<tr><td>${r}%対象</td><td class="r">¥${U.yen(c.buckets[r].net)}</td><td class="r">¥${U.yen(c.buckets[r].tax)}</td></tr>`).join('');

    const itemRows = inv.items.map((it) => `
      <tr>
        <td>${U.esc(it.name)}</td>
        <td class="r">${it.qty}</td>
        <td class="r">¥${U.yen(it.unitPrice)}</td>
        <td class="c">${it.taxRate ? it.taxRate + '%' : '—'}</td>
        <td class="r">¥${U.yen(it.qty * it.unitPrice)}</td>
      </tr>`).join('');

    const html = `
      <div class="doc">
        <div class="doc-title">${title}</div>
        <div class="doc-top">
          <div class="doc-to">
            <div class="to-name">${U.esc(inv.partnerName)} 御中</div>
            <table class="meta">
              <tr><td>${inv.type === 'estimate' ? '見積番号' : '請求番号'}</td><td>${S.invoices.displayNo(inv)}</td></tr>
              <tr><td>${inv.type === 'estimate' ? '見積日' : '請求日'}</td><td>${U.fmtDate(inv.date)}</td></tr>
              ${inv.dueDate ? `<tr><td>${inv.type === 'estimate' ? '有効期限' : '支払期限'}</td><td>${U.fmtDate(inv.dueDate)}</td></tr>` : ''}
            </table>
          </div>
          <div class="doc-from">
            <div class="from-name">${U.esc(s.name)}</div>
            ${s.address ? `<div>${U.esc(s.address)}</div>` : ''}
            ${s.tel ? `<div>TEL: ${U.esc(s.tel)}</div>` : ''}
            ${s.email ? `<div>${U.esc(s.email)}</div>` : ''}
            <div class="reg">登録番号：${U.esc(s.invoiceRegNo || '未登録')}</div>
          </div>
        </div>
        <div class="doc-grand">${inv.type === 'estimate' ? 'お見積金額' : 'ご請求金額'}　<b>¥${U.yen(c.total)}</b>（税込）</div>
        <table class="items">
          <thead><tr><th>品目</th><th class="r">数量</th><th class="r">単価</th><th class="c">税率</th><th class="r">金額</th></tr></thead>
          <tbody>${itemRows}</tbody>
        </table>
        <div class="doc-bottom">
          <div class="tax-break">
            <table><thead><tr><th>区分</th><th class="r">対象額(税抜)</th><th class="r">消費税</th></tr></thead>
            <tbody>${rateRows || '<tr><td colspan=3>—</td></tr>'}</tbody></table>
          </div>
          <table class="sum">
            <tr><td>小計（税抜）</td><td class="r">¥${U.yen(c.net)}</td></tr>
            <tr><td>消費税</td><td class="r">¥${U.yen(c.tax)}</td></tr>
            <tr class="grand"><td>合計</td><td class="r">¥${U.yen(c.total)}</td></tr>
          </table>
        </div>
        ${inv.note ? `<div class="doc-note"><b>備考</b><br>${U.esc(inv.note).replace(/\n/g, '<br>')}</div>` : ''}
        ${s.bank ? `<div class="doc-note"><b>お振込先</b><br>${U.esc(s.bank).replace(/\n/g, '<br>')}</div>` : ''}
      </div>`;

    const overlay = el('div.print-overlay');
    const sheet = el('div.print-sheet', { html });
    const bar = el('div.print-bar.no-print', {}, [
      el('button.btn.primary', { text: '🖨 印刷 / PDF保存', onclick: () => window.print() }),
      el('button.btn', { text: '閉じる', onclick: () => { overlay.remove(); document.body.classList.remove('printing'); } }),
    ]);
    overlay.appendChild(bar); overlay.appendChild(sheet);
    document.body.appendChild(overlay);
    document.body.classList.add('printing');
  };

  /* ---- 仕訳へ計上／入金記録 ------------------------------------------- */
  const postToJournal = async (inv) => {
    const c = S.invoices.calc(inv);
    // 借方：売掛金(税込合計)　貸方：売上高(税率別・税込)
    const lines = [{ side: 'debit', account: '120', tax: 'out', amount: c.total, memo: inv.partnerName }];
    Object.keys(c.buckets).forEach((r) => {
      const b = c.buckets[r]; const gross = b.net + b.tax;
      const tax = Number(r) === 8 ? 'sales8' : (Number(r) === 10 ? 'sales10' : 'out');
      lines.push({ side: 'credit', account: '400', tax, amount: gross });
    });
    await S.journals.removeBySource(inv.id);
    await S.journals.save({ refId: inv.id, source: 'invoice', date: inv.date, description: `売上：${inv.partnerName}（${S.invoices.displayNo(inv)}）`, lines });
    await S.invoices.save({ ...inv, posted: true });
    ui.toast('売上を仕訳に計上しました', 'ok');
    ui.renderRoute();
  };
  const recordPayment = async (inv) => {
    const c = S.invoices.calc(inv);
    await S.journals.save({ refId: inv.id + '_pay', source: 'invoice', date: U.today(), description: `入金：${inv.partnerName}（${S.invoices.displayNo(inv)}）`, lines: [
      { side: 'debit', account: '110', tax: 'out', amount: c.total },
      { side: 'credit', account: '120', tax: 'out', amount: c.total },
    ] });
    await S.invoices.save({ ...inv, paid: true });
    ui.toast('入金を記録しました', 'ok');
    ui.renderRoute();
  };

  /* ---- 一覧 ------------------------------------------------------------ */
  const listPage = (kind) => async (q) => {
    if (q.new) setTimeout(() => editor(null, q.new === 'estimate' ? 'estimate' : 'invoice'), 0);
    const all = await S.invoices.loadAll();
    const list = all.filter((i) => i.type === kind);
    const isEst = kind === 'estimate';
    const wrap = el('div');
    wrap.appendChild(ui.pageHead(isEst ? '見積書' : '請求書', [
      el('button.btn.primary', { text: isEst ? '＋ 見積書を作成' : '＋ 請求書を作成', onclick: () => editor(null, kind) }),
    ]));
    const card = el('div.card');
    card.appendChild(ui.table([
      { key: 'no', label: '番号', render: (r) => S.invoices.displayNo(r) },
      { key: 'date', label: '日付', render: (r) => U.fmtDate(r.date) },
      { key: 'pn', label: '取引先', render: (r) => r.partnerName || '—' },
      { key: 'amt', label: '金額(税込)', align: 'right', render: (r) => '¥' + U.yen(S.invoices.calc(r).total) },
      {
        key: 'status', label: '状態', render: (r) => {
          if (isEst) return r.posted ? el('span.badge.in', { text: '請求済' }) : el('span.badge', { text: '見積' });
          if (r.paid) return el('span.badge.in', { text: '入金済' });
          if (r.posted) return el('span.badge.out', { text: '計上済' });
          return el('span.badge', { text: '未計上' });
        },
      },
      {
        key: 'act', label: '', align: 'right', render: (r) => {
          const box = el('div.row-actions');
          box.appendChild(el('button.icon-btn', { text: '🖨', title: '印刷/PDF', onclick: () => printView(r) }));
          box.appendChild(el('button.icon-btn', { text: '✎', title: '編集', onclick: () => editor(r, kind) }));
          if (!isEst && !r.posted) box.appendChild(el('button.btn.sm', { text: '売上計上', onclick: () => postToJournal(r) }));
          if (!isEst && r.posted && !r.paid) box.appendChild(el('button.btn.sm', { text: '入金記録', onclick: () => recordPayment(r) }));
          if (isEst && !r.posted) box.appendChild(el('button.btn.sm', {
            text: '請求書化', onclick: async () => {
              const copy = { ...r, id: null, no: null, type: 'invoice', date: U.today(), posted: false, paid: false };
              const saved = await S.invoices.save(copy);
              await S.invoices.save({ ...r, posted: true });
              ui.toast('請求書を作成しました', 'ok'); printView(saved); ui.renderRoute();
            },
          }));
          box.appendChild(el('button.icon-btn.del', {
            text: '🗑', onclick: async () => { if (await ui.confirm('削除しますか？（関連仕訳も削除）')) { await S.invoices.remove(r.id); ui.toast('削除しました'); ui.renderRoute(); } },
          }));
          return box;
        },
      },
    ], list, { empty: isEst ? '見積書はありません' : '請求書はありません' }));
    wrap.appendChild(card);
    return wrap;
  };

  ui.register('invoices', listPage('invoice'));
  ui.register('estimates', listPage('estimate'));
})();
