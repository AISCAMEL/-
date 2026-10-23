/* monthly.js ― 月次推移（売上・費用・利益のグラフと表） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  ui.register('monthly', async () => {
    const journals = await S.journals.loadAll();
    const s = S.settings.get();
    const fsMonth = s.fiscalStartMonth || 4;
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), fsMonth);
    const tr = R.monthlyTrend(journals, fy.start, fsMonth);

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('月次推移', [el('span.muted', { text: `${fy.start.slice(0, 4)}年度` })]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    // グラフ（売上・費用）
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '売上・費用の月次推移' }),
      ui.barChart(tr.months, {
        labelKey: 'ym', labelFmt: (ym) => ym.slice(5) + '月', height: 240,
        keys: [{ key: 'revenue', label: '売上', color: '#2563eb' }, { key: 'expense', label: '費用', color: '#f59e0b' }],
      }),
    ]));

    // 売上総利益（粗利）の推移
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '売上総利益（粗利）の月次推移' }),
      ui.barChart(tr.months, {
        labelKey: 'ym', labelFmt: (ym) => ym.slice(5) + '月', height: 180,
        keys: [{ key: 'gross', label: '売上総利益', color: '#16a34a' }],
      }),
      el('p.muted.small', { text: '売上総利益＝売上高 − 売上原価（仕入高・外注費）。' }),
    ]));

    // 利益の推移
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '利益の月次推移' }),
      ui.barChart(tr.months, {
        labelKey: 'ym', labelFmt: (ym) => ym.slice(5) + '月', height: 180,
        keys: [{ key: 'net', label: '当期純利益', color: '#0d9488' }],
      }),
    ]));

    // 現預金残高の推移（資金繰り）
    const ct = R.cashTrend(journals, fy.start, fsMonth);
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '現預金残高の推移' }),
      ui.barChart(ct.months, {
        labelKey: 'ym', labelFmt: (ym) => ym.slice(5) + '月', height: 180,
        keys: [{ key: 'cash', label: '現預金残高（月末）', color: '#0ea5e9' }],
      }),
      el('p.muted.small', { text: '各月末時点の現金・普通預金・当座預金の合計残高です。' }),
    ]));

    // 表（行クリックでその月の仕訳へドリルダウン）
    const monthRange = (ym) => {
      const [y, m] = ym.split('-').map(Number);
      const end = new Date(y, m, 0);
      const pad = (x) => String(x).padStart(2, '0');
      return { start: `${ym}-01`, end: `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}` };
    };
    const card = el('div.card', {}, [el('h2', { text: '月次表（クリックでその月の仕訳へ）' })]);
    card.appendChild(ui.table([
      { key: 'ym', label: '月', render: (r) => r.ym },
      { key: 'revenue', label: '売上', align: 'right', render: (r) => '¥' + U.yenSigned(r.revenue) },
      { key: 'expense', label: '費用', align: 'right', render: (r) => '¥' + U.yenSigned(r.expense) },
      { key: 'net', label: '利益', align: 'right', render: (r) => el('span' + (r.net < 0 ? '.neg' : ''), { text: '¥' + U.yenSigned(r.net) }) },
    ], tr.months, {
      onRow: (r) => { A.app.setPeriod(monthRange(r.ym)); ui.go('journal'); },
      foot: el('tr.total-row', {}, [
        el('td', { text: '年間合計' }),
        el('td.right', { text: '¥' + U.yenSigned(tr.total.revenue) }),
        el('td.right', { text: '¥' + U.yenSigned(tr.total.expense) }),
        el('td.right', { text: '¥' + U.yenSigned(tr.total.net) }),
      ]),
    }));
    wrap.appendChild(card);
    return wrap;
  });
})();
