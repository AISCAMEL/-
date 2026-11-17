/* cashflow.js ― キャッシュフロー計算書（簡易）・年度比較 */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  const line = (label, value, opt) => el('div.income-line' + (opt || ''), {}, [el('span', { text: label }), el('span', { text: '¥' + U.yenSigned(value) })]);

  ui.register('cashflow', async () => {
    const journals = await S.journals.loadAll();
    const s = S.settings.get();
    const fsMonth = s.fiscalStartMonth || 4;
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), fsMonth);
    const cf = R.cashflow(journals, fy.start, fy.end);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('キャッシュフロー計算書（簡易）', [el('span.muted', { text: `${fy.start.slice(0, 4)}年度` })]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '営業活動によるキャッシュフロー' }),
      line('当期純利益', cf.net),
      line('＋ 減価償却費', cf.dep),
      line('− 売上債権の増加', -cf.dAR),
      line('− 棚卸資産の増加', -cf.dInv),
      line('＋ 仕入債務の増加', cf.dAP),
      line('営業CF 小計', cf.operating, '.big'),
    ]));
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '投資・財務活動によるキャッシュフロー' }),
      line('投資活動によるCF', cf.investing),
      line('財務活動によるCF', cf.financing),
      cf.adjust ? line('その他・調整', cf.adjust) : null,
    ]));
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '現預金の増減' }),
      line('期首 現預金', cf.cashOpen),
      line('現預金の増減', cf.cashChange, '.big'),
      line('期末 現預金', cf.cashClose, '.big'),
      el('p.muted.small', { text: '※ 間接法による概算です。運転資本の増減や非資金項目の一部を簡略化しています。正確なキャッシュフロー計算書は専門家にご確認ください。' }),
    ]));
    return wrap;
  });

  /* ---- 年度比較（前期対比） -------------------------------------------- */
  ui.register('compare', async () => {
    const journals = await S.journals.loadAll();
    const s = S.settings.get();
    const fsMonth = s.fiscalStartMonth || 4;
    const p = A.app.period();
    const cur = U.fiscalRange(p.start || U.today(), fsMonth);
    const prevRef = new Date(new Date(cur.start + 'T00:00:00').getTime() - 86400000).toISOString().slice(0, 10);
    const prev = U.fiscalRange(prevRef, fsMonth);
    const stC = R.statements(journals, cur.start, cur.end);
    const stP = R.statements(journals, prev.start, prev.end);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('年度比較（前期対比）', [el('span.muted', { text: `${prev.start.slice(0, 4)}年度 → ${cur.start.slice(0, 4)}年度` })]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    const rows = [
      { label: '売上（収益）', c: stC.pl.revenue.total, p: stP.pl.revenue.total },
      { label: '費用', c: stC.pl.expense.total, p: stP.pl.expense.total },
      { label: '当期純利益', c: stC.pl.netIncome, p: stP.pl.netIncome },
      { label: '資産合計', c: stC.bs.asset.total, p: stP.bs.asset.total },
      { label: '負債合計', c: stC.bs.liability.total, p: stP.bs.liability.total },
    ];
    const card = el('div.card');
    card.appendChild(ui.table([
      { key: 'label', label: '項目', render: (r) => r.label },
      { key: 'p', label: '前期', align: 'right', render: (r) => '¥' + U.yenSigned(r.p) },
      { key: 'c', label: '当期', align: 'right', render: (r) => '¥' + U.yenSigned(r.c) },
      { key: 'diff', label: '増減', align: 'right', render: (r) => el('span' + ((r.c - r.p) < 0 ? '.neg' : ''), { text: '¥' + U.yenSigned(r.c - r.p) }) },
      { key: 'rate', label: '増減率', align: 'right', render: (r) => r.p ? (Math.round((r.c - r.p) / Math.abs(r.p) * 1000) / 10) + '%' : '—' },
    ], rows));
    wrap.appendChild(card);

    // 前年同月比（月別）グラフ
    const trC = R.monthlyTrend(journals, cur.start, fsMonth);
    const trP = R.monthlyTrend(journals, prev.start, fsMonth);
    const mrows = trC.months.map((m, i) => ({ ym: m.ym, curRev: m.revenue, prevRev: (trP.months[i] || {}).revenue || 0, curNet: m.net, prevNet: (trP.months[i] || {}).net || 0 }));
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '売上の前年同月比' }),
      ui.barChart(mrows, {
        labelKey: 'ym', labelFmt: (ym) => ym.slice(5) + '月', height: 220,
        keys: [{ key: 'prevRev', label: '前期', color: '#cbd5e1' }, { key: 'curRev', label: '当期', color: '#2563eb' }],
      }),
    ]));
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '利益の前年同月比' }),
      ui.barChart(mrows, {
        labelKey: 'ym', labelFmt: (ym) => ym.slice(5) + '月', height: 200,
        keys: [{ key: 'prevNet', label: '前期', color: '#cbd5e1' }, { key: 'curNet', label: '当期', color: '#0d9488' }],
      }),
    ]));
    return wrap;
  });
})();
