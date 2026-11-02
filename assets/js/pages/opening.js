/* opening.js ― 期首残高の入力・前年度からの繰越 */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;
  const CAT = A.accounts.CATEGORIES;

  const bsAccounts = () => S.accounts.all().filter((a) => ['asset', 'liability', 'equity'].includes(a.category));

  ui.register('opening', async () => {
    const journals = await S.journals.loadAll();
    const s = S.settings.get();
    const fsMonth = s.fiscalStartMonth || 4;
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), fsMonth);
    const fyStart = fy.start;

    // 既存の期首残高（source='opening' かつ日付=期首）を読み込む
    const existing = journals.find((j) => j.source === 'opening' && j.date === fyStart);
    const openingMap = {}; // code -> 正規側の金額（借方資産は＋、貸方負債純資産は＋）
    if (existing) {
      existing.lines.forEach((l) => {
        const cat = S.accounts.category(l.account);
        const normalDebit = cat && CAT[cat].side === 'debit';
        const signed = (l.side === 'debit' ? 1 : -1) * (normalDebit ? 1 : -1) * l.amount;
        openingMap[l.account] = (openingMap[l.account] || 0) + signed;
      });
    }

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('期首残高・繰越', [
      el('span.muted', { text: `${fyStart.slice(0, 4)}年度（期首 ${U.fmtDate(fyStart)}）` }),
    ]));

    wrap.appendChild(el('div.card', {}, [
      el('p.muted.small', { text: '期首残高は、他システムからの移行時や、前年度末の残高を当期に引き継ぐ際に使います。「期間」を変えると対象年度が切り替わります。' }),
      ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); }),
    ]));

    // 入力テーブル
    const inputs = {};
    const balanceLine = el('div.jsummary');
    const refresh = () => {
      let d = 0, c = 0;
      bsAccounts().forEach((a) => {
        const v = U.parseYen(inputs[a.code].value);
        if (CAT[a.category].side === 'debit') d += v; else c += v;
      });
      balanceLine.innerHTML = '';
      balanceLine.appendChild(el('span', { html: `借方（資産）合計 <b>¥${U.yen(d)}</b>` }));
      balanceLine.appendChild(el('span', { html: `貸方（負債・純資産）合計 <b>¥${U.yen(c)}</b>` }));
      balanceLine.appendChild(el('span.' + (d === c ? 'ok' : 'ng'), { html: d === c ? '✓ 一致' : `差額 ¥${U.yen(Math.abs(d - c))}` }));
    };

    const mkRow = (a) => {
      const inp = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: openingMap[a.code] ? U.yen(openingMap[a.code]) : '' });
      inp.addEventListener('input', () => { inp.value = inp.value.replace(/[^\d,\-]/g, ''); refresh(); });
      inp.addEventListener('blur', () => { inp.value = inp.value ? U.yen(U.parseYen(inp.value)) : ''; });
      inputs[a.code] = inp;
      return el('tr', {}, [
        el('td', { text: a.code }),
        el('td', { text: a.name }),
        el('td', { text: CAT[a.category].label }),
        el('td', {}, [inp]),
      ]);
    };
    const groups = ['asset', 'liability', 'equity'];
    const bodyRows = [];
    groups.forEach((g) => bsAccounts().filter((a) => a.category === g).forEach((a) => bodyRows.push(mkRow(a))));

    const table = el('table.grid', {}, [
      el('thead', {}, [el('tr', {}, [el('th', { text: 'コード' }), el('th', { text: '科目' }), el('th', { text: '区分' }), el('th', { text: '期首残高' })])]),
      el('tbody', {}, bodyRows),
    ]);
    refresh();

    // 前年度から繰越
    const carryOver = async () => {
      const prevFy = U.fiscalRange(new Date(new Date(fyStart + 'T00:00:00').getTime() - 86400000).toISOString().slice(0, 10), fsMonth);
      const tb = R.trialBalance(journals, prevFy.start, prevFy.end);
      const st = R.statements(journals, prevFy.start, prevFy.end);
      if (!tb.rows.length) return ui.toast('前年度のデータがありません', 'err');
      // BS各科目の期末残高を反映
      bsAccounts().forEach((a) => { inputs[a.code].value = ''; });
      tb.rows.filter((r) => ['asset', 'liability', 'equity'].includes(r.category)).forEach((r) => {
        if (inputs[r.code]) inputs[r.code].value = r.balance ? U.yen(r.balance) : '';
      });
      // 前年度純利益を繰越利益剰余金に加算
      const rei = inputs['320'];
      if (rei) {
        const cur = U.parseYen(rei.value);
        rei.value = U.yen(cur + st.pl.netIncome);
      }
      refresh();
      ui.toast(`前年度（${prevFy.start.slice(0, 4)}年度）末の残高を反映しました。内容を確認して保存してください。`, 'ok');
    };

    const save = async () => {
      const lines = [];
      let d = 0, c = 0;
      bsAccounts().forEach((a) => {
        const v = U.parseYen(inputs[a.code].value);
        if (!v) return;
        const side = CAT[a.category].side; // 正の値は通常側に置く
        lines.push({ side: v >= 0 ? side : (side === 'debit' ? 'credit' : 'debit'), account: a.code, tax: 'out', amount: Math.abs(v) });
        if (side === 'debit') d += v; else c += v;
      });
      if (d !== c) return ui.toast('借方と貸方が一致していません', 'err');
      if (!lines.length) return ui.toast('期首残高が入力されていません', 'err');
      // 既存の当年度期首残高を置き換え
      await S.journals.removeWhere((j) => j.source === 'opening' && j.date === fyStart);
      await S.journals.save({ source: 'opening', date: fyStart, description: `期首残高（${fyStart.slice(0, 4)}年度）`, lines });
      ui.toast('期首残高を保存しました', 'ok');
      ui.renderRoute();
    };

    wrap.appendChild(el('div.card', {}, [
      el('div.card-head', {}, [
        el('h2', { text: '期首残高（貸借対照表科目）' }),
        el('button.btn', { text: '⟲ 前年度末から繰越', onclick: carryOver }),
      ]),
      table,
      balanceLine,
      el('div.quick-row', {}, [el('button.btn.primary', { text: '期首残高を保存', onclick: save })]),
    ]));
    return wrap;
  });
})();
