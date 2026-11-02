/* inventory.js ― 棚卸し（商品マスタ・期末棚卸・売上原価・棚卸仕訳） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  const yenInput = (v) => {
    const i = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: v ? U.yen(v) : '' });
    i.addEventListener('blur', () => { i.value = i.value ? U.yen(U.parseYen(i.value)) : ''; });
    return i;
  };

  const productModal = (existing) => {
    const p = existing || { name: '', unit: '個', unitPrice: 0, note: '' };
    const name = el('input', { type: 'text', value: p.name, placeholder: '商品名' });
    const unit = el('input', { type: 'text', value: p.unit || '個', placeholder: '単位' });
    const price = yenInput(p.unitPrice);
    const note = el('input', { type: 'text', value: p.note || '', placeholder: '備考' });
    const body = el('div.editor', {}, [
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '商品名' }), name]), el('label', {}, [el('span', { text: '単位' }), unit]), el('label', {}, [el('span', { text: '評価単価' }), price])]),
      el('label', {}, [el('span', { text: '備考' }), note]),
    ]);
    const m = ui.modal(existing ? '商品の編集' : '商品の登録', body, {
      footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', {
        text: '保存', onclick: async () => {
          if (!name.value.trim()) return ui.toast('商品名を入力してください', 'err');
          await S.products.save({ ...p, name: name.value.trim(), unit: unit.value, unitPrice: U.parseYen(price.value), note: note.value });
          m.close(); ui.toast('保存しました', 'ok'); ui.renderRoute();
        },
      })],
    });
  };

  ui.register('inventory', async () => {
    const journals = await S.journals.loadAll();
    const products = await S.products.loadAll();
    const s = S.settings.get();
    const fsMonth = s.fiscalStartMonth || 4;
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), fsMonth);
    const inv = (s.inventory && s.inventory[fy.start]) || { closing: 0, counts: {} };
    // 前期末＝当期首棚卸高
    const prevFy = U.fiscalRange(new Date(new Date(fy.start + 'T00:00:00').getTime() - 86400000).toISOString().slice(0, 10), fsMonth);
    const prevClosing = (s.inventory && s.inventory[prevFy.start] && s.inventory[prevFy.start].closing) || 0;
    // 当期仕入高（仕入高500の当期残高）
    const tb = R.trialBalance(journals, fy.start, fy.end);
    const purchase = (tb.rows.find((r) => r.code === '500') || { balance: 0 }).balance;

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('棚卸し', [
      el('span.muted', { text: `${fy.start.slice(0, 4)}年度` }),
      el('button.btn', { text: '＋ 商品を登録', onclick: () => productModal(null) }),
    ]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    // 期末棚卸（商品ごとの数量→金額）
    const counts = { ...(inv.counts || {}) };
    const closingBox = el('div');
    const closingManual = yenInput(inv.closing || 0);
    const computeClosing = () => {
      if (!products.length) return U.parseYen(closingManual.value);
      return products.reduce((sum, pr) => sum + (Number(counts[pr.id]) || 0) * (pr.unitPrice || 0), 0);
    };
    const summary = el('div.jsummary');
    const refreshSummary = () => {
      const closing = computeClosing();
      const cogs = prevClosing + purchase - closing; // 売上原価
      summary.innerHTML = '';
      summary.appendChild(el('span', { html: `期首棚卸高 <b>¥${U.yen(prevClosing)}</b>` }));
      summary.appendChild(el('span', { html: `＋当期仕入高 <b>¥${U.yen(purchase)}</b>` }));
      summary.appendChild(el('span', { html: `−期末棚卸高 <b>¥${U.yen(closing)}</b>` }));
      summary.appendChild(el('span.ok', { html: `＝売上原価 <b>¥${U.yenSigned(cogs)}</b>` }));
    };

    if (products.length) {
      const rows = products.map((pr) => {
        const qty = el('input.qty-in', { type: 'number', min: '0', value: counts[pr.id] != null ? counts[pr.id] : '' });
        const amt = el('span');
        const upd = () => { counts[pr.id] = Number(qty.value) || 0; amt.textContent = '¥' + U.yen((Number(qty.value) || 0) * (pr.unitPrice || 0)); refreshSummary(); };
        qty.addEventListener('input', upd);
        setTimeout(upd, 0);
        return el('tr', {}, [
          el('td', { text: pr.name }),
          el('td', { text: pr.unit || '' }),
          el('td.right', { text: '¥' + U.yen(pr.unitPrice) }),
          el('td', {}, [qty]),
          el('td.right', {}, [amt]),
          el('td', {}, [el('div.row-actions', {}, [
            el('button.icon-btn', { text: '✎', onclick: () => productModal(pr) }),
            el('button.icon-btn.del', { text: '🗑', onclick: async () => { if (await ui.confirm('この商品を削除しますか？')) { await S.products.remove(pr.id); ui.toast('削除しました'); ui.renderRoute(); } } }),
          ])]),
        ]);
      });
      closingBox.appendChild(el('table.grid', {}, [
        el('thead', {}, [el('tr', {}, ['商品', '単位', '評価単価', '数量', '金額', ''].map((h) => el('th', { text: h })))]),
        el('tbody', {}, rows),
      ]));
    } else {
      closingBox.appendChild(el('div.form-row', {}, [el('label', {}, [el('span', { text: '期末棚卸高（直接入力）' }), closingManual])]));
      closingManual.addEventListener('input', refreshSummary);
      closingBox.appendChild(el('p.muted.small', { text: '「＋商品を登録」で商品を登録すると、数量入力から自動計算できます。' }));
    }
    refreshSummary();

    const post = async () => {
      const closing = computeClosing();
      const lines = [];
      if (prevClosing > 0) { lines.push({ side: 'debit', account: '500', tax: 'out', amount: prevClosing }); lines.push({ side: 'credit', account: '150', tax: 'out', amount: prevClosing }); }
      if (closing > 0) { lines.push({ side: 'debit', account: '150', tax: 'out', amount: closing }); lines.push({ side: 'credit', account: '500', tax: 'out', amount: closing }); }
      if (!lines.length) return ui.toast('期首・期末とも0のため計上する仕訳がありません', 'err');
      await S.journals.removeWhere((j) => j.source === 'inventory' && U.inRange(j.date, fy.start, fy.end));
      await S.journals.save({ source: 'inventory', date: fy.end, description: `棚卸（期末棚卸高 ¥${U.yen(closing)}）`, lines });
      const all = { ...(S.settings.get().inventory || {}) };
      all[fy.start] = { closing, counts };
      await S.settings.save({ inventory: all });
      ui.toast('棚卸仕訳を計上しました', 'ok'); ui.renderRoute();
    };

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '期末棚卸' }),
      closingBox,
      summary,
      el('p.muted.small', { text: '三分法：借）仕入高／貸）繰越商品（期首）と、借）繰越商品／貸）仕入高（期末）を計上し、仕入高を売上原価に調整します。棚卸資産(150)は期末棚卸高になります。' }),
      el('div.quick-row', {}, [el('button.btn.primary', { text: '棚卸仕訳を計上', onclick: post })]),
    ]));
    return wrap;
  });
})();
