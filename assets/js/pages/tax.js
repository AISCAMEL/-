/* tax.js ― 消費税集計（インボイス対応・税率別／納付税額の試算） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  const rateTable = (title, byRate, isSales) => {
    const rows = Object.keys(byRate).sort().reverse().map((r) => {
      const b = byRate[r];
      return el('tr', {}, [
        el('td', { text: r + '%' }),
        el('td.right', { text: '¥' + U.yen(b.gross - b.tax) }),
        el('td.right', { text: '¥' + U.yen(b.tax) }),
        el('td.right', { text: '¥' + U.yen(b.gross) }),
      ]);
    });
    return el('table.grid.report-table', {}, [
      el('thead', {}, [el('tr', {}, [el('th', { text: title }), el('th.right', { text: '税抜' }), el('th.right', { text: '消費税' }), el('th.right', { text: '税込' })])]),
      el('tbody', {}, rows.length ? rows : [el('tr', {}, [el('td', { colspan: 4, class: 'empty', text: '対象取引なし' })])]),
    ]);
  };

  ui.register('tax', async () => {
    const journals = await S.journals.loadAll();
    const p = A.app.period();
    const t = R.taxSummary(journals, p.start, p.end);
    const wrap = el('div');
    wrap.appendChild(ui.pageHead('消費税集計（インボイス対応）', [
      el('button.btn.sm', { text: '印刷', onclick: () => window.print() }),
    ]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    wrap.appendChild(el('div.card.report-card', {}, [
      el('h2', { text: '税率別 集計' }),
      el('div.two-col', {}, [
        rateTable('課税売上', t.salesByRate, true),
        rateTable('課税仕入', t.purchaseByRate, false),
      ]),
    ]));

    // 計算方式の選択（原則／簡易課税／2割特例）
    const s = S.settings.get();
    const methodSel = el('select', {}, [
      el('option', { value: 'general', text: '原則課税' }),
      el('option', { value: 'simplified', text: '簡易課税' }),
      el('option', { value: 'special20', text: '2割特例' }),
    ]);
    methodSel.value = s.taxFilingMethod || 'general';
    const bizSel = el('select');
    Object.keys(R.DEEMED_RATES).forEach((k) => { const d = R.DEEMED_RATES[k]; const o = el('option', { value: k, text: `${d.label} ${d.rate}%` }); if (Number(k) === (s.simplifiedBizType || 5)) o.selected = true; bizSel.appendChild(o); });
    const bizWrap = el('label', {}, [el('span', { text: '事業区分（みなし仕入率）' }), bizSel]);
    const payableBox = el('div');

    const drawPayable = () => {
      const method = methodSel.value;
      bizWrap.style.display = method === 'simplified' ? '' : 'none';
      const bizType = Number(bizSel.value);
      const payable = R.taxPayable(t, method, bizType);
      const rows = [el('tr', {}, [el('td', { text: '仮受消費税（売上に係る消費税）' }), el('td.right', { text: '¥' + U.yen(t.salesTax) })])];
      if (method === 'general') rows.push(el('tr', {}, [el('td', { text: '仮払消費税（仕入税額控除）' }), el('td.right', { text: '△¥' + U.yen(t.purchaseTax) })]));
      if (method === 'simplified') { const dr = R.DEEMED_RATES[bizType].rate; rows.push(el('tr', {}, [el('td', { text: `みなし仕入税額（${dr}%）` }), el('td.right', { text: '△¥' + U.yen(Math.floor(t.salesTax * dr / 100)) })])); }
      if (method === 'special20') rows.push(el('tr', {}, [el('td', { text: 'みなし仕入税額（80%＝2割特例）' }), el('td.right', { text: '△¥' + U.yen(t.salesTax - payable) })]));
      rows.push(el('tr.total-row', {}, [el('td', { text: '差引 納付税額' + (payable < 0 ? '（還付）' : '') }), el('td.right', { text: '¥' + U.yenSigned(payable) })]));
      payableBox.innerHTML = '';
      payableBox.appendChild(el('table.grid.report-table', {}, [el('tbody', {}, rows)]));
      // 方式間の比較
      const g = R.taxPayable(t, 'general', bizType), sp = R.taxPayable(t, 'special20', bizType), si = R.taxPayable(t, 'simplified', bizType);
      payableBox.appendChild(el('div.compare', {}, [
        el('div.compare-item' + (method === 'general' ? '.sel' : ''), { html: `原則課税<br><b>¥${U.yenSigned(g)}</b>` }),
        el('div.compare-item' + (method === 'simplified' ? '.sel' : ''), { html: `簡易課税<br><b>¥${U.yenSigned(si)}</b>` }),
        el('div.compare-item' + (method === 'special20' ? '.sel' : ''), { html: `2割特例<br><b>¥${U.yenSigned(sp)}</b>` }),
      ]));
    };
    methodSel.addEventListener('change', async () => { await S.settings.save({ taxFilingMethod: methodSel.value }); drawPayable(); });
    bizSel.addEventListener('change', async () => { await S.settings.save({ simplifiedBizType: Number(bizSel.value) }); drawPayable(); });
    drawPayable();

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '消費税 納付額の試算' }),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '計算方式' }), methodSel]), bizWrap]),
      payableBox,
      el('p.muted.small', { text: '※ 税込経理方式での概算です。実際の申告額は端数処理・課税区分・各種特例により異なる場合があります。2割特例・簡易課税には適用要件（基準期間の課税売上高、届出等）があります。' }),
    ]));
    return wrap;
  });
})();
