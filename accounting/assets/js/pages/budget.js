/* budget.js ― 予算・予実管理（科目別の年間予算と実績対比） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  const plAccounts = () => S.accounts.all().filter((a) => ['revenue', 'expense'].includes(a.category));

  ui.register('budget', async () => {
    const journals = await S.journals.loadAll();
    const s = S.settings.get();
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), s.fiscalStartMonth || 4);
    const fyKey = fy.start;
    const budgets = (s.budgets && s.budgets[fyKey]) || {};

    // 実績（PL科目の当期残高）
    const st = R.statements(journals, fy.start, fy.end);
    const actual = {};
    [...st.pl.revenue.items, ...st.pl.expense.items].forEach((i) => { actual[i.code] = i.amount; });

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('予算・予実管理', [el('span.muted', { text: `${fyKey.slice(0, 4)}年度` })]));
    wrap.appendChild(el('div.card', {}, [
      el('p.muted.small', { text: '科目ごとに年間予算を入力すると、当期の実績と対比できます。「期間」で対象年度が切り替わります。' }),
      ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); }),
    ]));

    const inputs = {};
    const summary = el('div.jsummary');
    const catTable = (cat, title) => {
      const accs = plAccounts().filter((a) => a.category === cat);
      const rows = accs.map((a) => {
        const inp = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: budgets[a.code] ? U.yen(budgets[a.code]) : '' });
        inp.addEventListener('blur', () => { inp.value = inp.value ? U.yen(U.parseYen(inp.value)) : ''; recompute(); });
        inp.addEventListener('input', recompute);
        inputs[a.code] = inp;
        return { a, inp };
      });
      const bodyRows = rows.map(({ a, inp }) => {
        const act = actual[a.code] || 0;
        const rateCell = el('td.right');
        const diffCell = el('td.right');
        const upd = () => {
          const bud = U.parseYen(inp.value);
          diffCell.textContent = bud ? U.yenSigned(act - bud) : '—';
          rateCell.textContent = bud ? Math.round(act / bud * 100) + '%' : '—';
        };
        inp.addEventListener('input', upd); inp.addEventListener('blur', upd);
        const tr = el('tr', {}, [el('td', { text: a.name }), el('td', {}, [inp]), el('td.right', { text: '¥' + U.yen(act) }), diffCell, rateCell]);
        upd();
        return tr;
      });
      return el('table.grid', {}, [
        el('thead', {}, [el('tr', {}, [el('th', { text: title }), el('th', { text: '年間予算' }), el('th.right', { text: '実績' }), el('th.right', { text: '差異' }), el('th.right', { text: '達成率' })])]),
        el('tbody', {}, bodyRows),
      ]);
    };

    const recompute = () => {
      let budRev = 0, budExp = 0;
      plAccounts().forEach((a) => { const v = U.parseYen(inputs[a.code].value); if (a.category === 'revenue') budRev += v; else budExp += v; });
      const actRev = st.pl.revenue.total, actExp = st.pl.expense.total;
      summary.innerHTML = '';
      summary.appendChild(el('span', { html: `予算利益 <b>¥${U.yenSigned(budRev - budExp)}</b>` }));
      summary.appendChild(el('span', { html: `実績利益 <b>¥${U.yenSigned(actRev - actExp)}</b>` }));
      summary.appendChild(el('span.' + (actRev - actExp >= budRev - budExp ? 'ok' : 'ng'), { html: `差異 ¥${U.yenSigned((actRev - actExp) - (budRev - budExp))}` }));
    };

    wrap.appendChild(el('div.card', {}, [el('h2', { text: '収益予算' }), catTable('revenue', '収益科目')]));
    wrap.appendChild(el('div.card', {}, [el('h2', { text: '費用予算' }), catTable('expense', '費用科目')]));
    recompute();

    const save = async () => {
      const map = {};
      plAccounts().forEach((a) => { const v = U.parseYen(inputs[a.code].value); if (v) map[a.code] = v; });
      const all = { ...(S.settings.get().budgets || {}) };
      all[fyKey] = map;
      await S.settings.save({ budgets: all });
      ui.toast('予算を保存しました', 'ok');
    };
    wrap.appendChild(el('div.card', {}, [summary, el('div.quick-row', {}, [el('button.btn.primary', { text: '予算を保存', onclick: save })])]));
    return wrap;
  });
})();
