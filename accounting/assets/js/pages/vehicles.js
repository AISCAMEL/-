/* vehicles.js ― 車両別損益（案件別原価管理・オークション代行の中核機能）
 * 1台ごとに 仕入＋諸経費→売却 を紐づけ、粗利・粗利率・在庫日数・在庫評価額を集計。
 * 普通の会計ソフトにはない、代行業のための「車両P/L」。
 */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  const STATUS = { stock: '在庫', reserved: '商談中', sold: '売却済', canceled: 'キャンセル' };
  const statusBadge = (v, sold) => {
    const k = v.status || (sold ? 'sold' : 'stock');
    const cls = k === 'sold' ? 'in' : (k === 'canceled' ? 'out' : '');
    return el('span.badge.' + cls, { text: STATUS[k] || '在庫' });
  };

  /* ---- 車両の登録・編集 ---------------------------------------------- */
  const editor = (existing) => {
    const v = existing || { name: '', maker: '', carNo: '', auctionSite: '', purchaseDate: U.today(), saleDate: '', status: 'stock', note: '' };
    const name = el('input', { type: 'text', value: v.name || '', placeholder: '車名（例：アルファード 2.5S）' });
    const maker = el('input', { type: 'text', value: v.maker || '', placeholder: 'メーカー/型式（任意）' });
    const carNo = el('input', { type: 'text', value: v.carNo || '', placeholder: '管理番号・車台番号（任意）' });
    const site = el('input', { type: 'text', value: v.auctionSite || '', placeholder: '仕入先・会場（例：USS東京）' });
    const pDate = el('input', { type: 'date', value: v.purchaseDate || U.today() });
    const sDate = el('input', { type: 'date', value: v.saleDate || '' });
    const status = el('select'); Object.keys(STATUS).forEach((k) => { const o = el('option', { value: k, text: STATUS[k] }); if (k === (v.status || 'stock')) o.selected = true; status.appendChild(o); });
    const note = el('textarea', { rows: 2 }); note.value = v.note || '';
    const body = el('div.editor', {}, [
      el('div.form-row', {}, [el('label.grow', {}, [el('span', { text: '車名' }), name]), el('label', {}, [el('span', { text: '状態' }), status])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: 'メーカー/型式' }), maker]), el('label', {}, [el('span', { text: '管理番号' }), carNo])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '仕入先・会場' }), site])]),
      el('div.form-row', {}, [el('label', {}, [el('span', { text: '仕入日' }), pDate]), el('label', {}, [el('span', { text: '売却日（売れたら）' }), sDate])]),
      el('label', {}, [el('span', { text: 'メモ' }), note]),
      el('p.muted.small', { text: '登録後、仕訳・経費・請求書の入力画面で「車両」を選ぶと、この車両に原価・売上が紐づきます。' }),
    ]);
    const m = ui.modal(existing ? '車両の編集' : '車両の登録', body, {
      footer: [el('button.btn', { text: 'キャンセル', onclick: () => m.close() }), el('button.btn.primary', {
        text: '保存', onclick: async () => {
          if (!name.value.trim()) return ui.toast('車名を入力してください', 'err');
          await S.vehicles.save({ ...v, name: name.value.trim(), maker: maker.value, carNo: carNo.value, auctionSite: site.value, purchaseDate: pDate.value, saleDate: sDate.value, status: status.value, note: note.value });
          m.close(); ui.toast('保存しました', 'ok'); ui.renderRoute();
        },
      })],
    });
  };

  /* ---- 1台の明細（紐づく仕訳） --------------------------------------- */
  const detail = (row, journals) => {
    const v = row.v;
    const tagged = journals.filter((j) => j.vehicleId === v.id).sort((a, b) => a.date.localeCompare(b.date));
    const lineInfo = (j) => {
      let costs = [], sales = [];
      (j.lines || []).forEach((l) => {
        const cat = S.accounts.category(l.account);
        if (cat === 'expense') costs.push(`${S.accounts.name(l.account)} ¥${U.yen(l.amount)}`);
        if (cat === 'revenue') sales.push(`${S.accounts.name(l.account)} ¥${U.yen(l.amount)}`);
      });
      return [costs.join('・'), sales.join('・')];
    };
    const insights = [];
    if (!row.sold && row.holdingDays != null && row.holdingDays > 45) insights.push({ c: 'warn', t: `長期在庫（${row.holdingDays}日）— 早期の販売・値下げ検討を` });
    if (row.sold && row.profit < 0) insights.push({ c: 'bad', t: `赤字販売（${U.yenSigned(row.profit)}）` });
    else if (row.sold && row.marginPct != null && row.marginPct < 10) insights.push({ c: 'warn', t: `低粗利（粗利率 ${row.marginPct}%）` });
    if (row.sold && row.profit >= 0 && (row.marginPct == null || row.marginPct >= 15)) insights.push({ c: 'ok', t: `良好な粗利（${U.yenSigned(row.profit)} / ${row.marginPct != null ? row.marginPct + '%' : '—'}）` });

    const body = el('div', {}, [
      el('div.stat-grid', {}, [
        el('div.stat', {}, [el('div.stat-label', { text: '仕入原価' }), el('div.stat-value', { text: '¥' + U.yen(row.purchase) })]),
        el('div.stat', {}, [el('div.stat-label', { text: '諸経費' }), el('div.stat-value', { text: '¥' + U.yen(row.expense) })]),
        el('div.stat.good', {}, [el('div.stat-label', { text: '売上' }), el('div.stat-value', { text: '¥' + U.yen(row.sales) })]),
        el('div.stat.' + (row.profit >= 0 ? 'good' : 'bad'), {}, [el('div.stat-label', { text: '粗利' }), el('div.stat-value', { text: '¥' + U.yenSigned(row.profit) })]),
      ]),
      insights.length ? el('div.todo-list', {}, insights.map((i) => el('div.todo-item.' + i.c, {}, [el('span.todo-dot'), el('span', { text: i.t })]))) : null,
    ]);
    body.appendChild(ui.table([
      { key: 'date', label: '日付', render: (r) => U.fmtDate(r.date) },
      { key: 'desc', label: '摘要', render: (r) => r.description || '—' },
      { key: 'cost', label: '原価', align: 'right', render: (r) => lineInfo(r)[0] || '—' },
      { key: 'sale', label: '売上', align: 'right', render: (r) => lineInfo(r)[1] || '—' },
    ], tagged, { empty: 'この車両に紐づく仕訳がまだありません（仕訳・経費・請求書で車両を選択してください）' }));
    const m = ui.modal(`${v.name}　明細`, body, { footer: [el('button.btn', { text: '閉じる', onclick: () => m.close() }), el('button.btn.primary', { text: '編集', onclick: () => { m.close(); editor(v); } })] });
  };

  ui.register('vehicles', async () => {
    const vehicles = await S.vehicles.loadAll();
    const journals = await S.journals.loadAll();
    const pl = R.vehiclePL(journals, vehicles);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('車両別損益（案件別原価管理）', [
      el('button.btn.primary', { text: '＋ 車両を登録', onclick: () => editor(null) }),
    ]));

    // KPI
    const avgMargin = (() => { const sold = pl.rows.filter((r) => r.sold && r.marginPct != null); return sold.length ? Math.round(sold.reduce((s, r) => s + r.marginPct, 0) / sold.length * 10) / 10 : null; })();
    wrap.appendChild(el('div.stat-grid', {}, [
      el('div.stat', {}, [el('div.stat-label', { text: '在庫台数' }), el('div.stat-value', { text: pl.inStock + ' 台' })]),
      el('div.stat.warn', {}, [el('div.stat-label', { text: '在庫評価額（積み上げ原価）' }), el('div.stat-value', { text: '¥' + U.yen(pl.inventoryValue) })]),
      el('div.stat.good', {}, [el('div.stat-label', { text: '販売済み 粗利' }), el('div.stat-value', { text: '¥' + U.yenSigned(pl.soldProfit) })]),
      el('div.stat', {}, [el('div.stat-label', { text: '平均粗利率（販売済み）' }), el('div.stat-value', { text: avgMargin != null ? avgMargin + '%' : '—' })]),
    ]));

    if (!vehicles.length) {
      wrap.appendChild(el('div.card', {}, [
        el('h2', { text: 'まだ車両がありません' }),
        el('p.muted', { text: 'オークションで仕入れた1台ごとに車両を登録し、仕入・陸送・整備・手数料などの経費と、売却の売上を紐づけると、車両ごとの粗利が自動で見えます。' }),
        el('button.btn.primary', { text: '＋ 最初の車両を登録', onclick: () => editor(null) }),
      ]));
      return wrap;
    }

    const card = el('div.card', {}, [el('h2', { text: '車両一覧（クリックで明細）' })]);
    card.appendChild(ui.table([
      { key: 'name', label: '車両', render: (r) => r.v.name + (r.v.auctionSite ? `（${r.v.auctionSite}）` : '') },
      { key: 'status', label: '状態', render: (r) => statusBadge(r.v, r.sold) },
      { key: 'purchase', label: '仕入', align: 'right', render: (r) => '¥' + U.yen(r.purchase) },
      { key: 'expense', label: '諸経費', align: 'right', render: (r) => '¥' + U.yen(r.expense) },
      { key: 'sales', label: '売上', align: 'right', render: (r) => '¥' + U.yen(r.sales) },
      { key: 'profit', label: '粗利', align: 'right', render: (r) => el('span' + (r.profit < 0 ? '.neg' : ''), { text: '¥' + U.yenSigned(r.profit) }) },
      { key: 'margin', label: '粗利率', align: 'right', render: (r) => r.marginPct != null ? r.marginPct + '%' : '—' },
      { key: 'days', label: '在庫日数', align: 'right', render: (r) => r.holdingDays != null ? (r.sold ? r.holdingDays + '日(回転)' : r.holdingDays + '日') : '—' },
    ], pl.rows, {
      onRow: (r) => detail(r, journals),
      foot: el('tr.total-row', {}, [
        el('td', { text: '合計' }), el('td'),
        el('td.right', { text: '¥' + U.yen(pl.totals.purchase) }),
        el('td.right', { text: '¥' + U.yen(pl.totals.expense) }),
        el('td.right', { text: '¥' + U.yen(pl.totals.sales) }),
        el('td.right', { text: '¥' + U.yenSigned(pl.totals.profit) }),
        el('td'), el('td'),
      ]),
    }));
    wrap.appendChild(card);
    return wrap;
  });
})();
