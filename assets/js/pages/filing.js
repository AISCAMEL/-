/* filing.js ― 確定申告サポート（所得金額の調整＝別表四の考え方・法人税概算・CSV出力） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;
  const CAT = A.accounts.CATEGORIES;

  // CSV文字列を作ってダウンロード（Excelでも文字化けしないようBOM付きUTF-8）
  const downloadCsv = (filename, rows) => {
    const esc = (v) => {
      const s = String(v == null ? '' : v);
      return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    };
    const csv = rows.map((r) => r.map(esc).join(',')).join('\r\n');
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
    const a = el('a', { href: URL.createObjectURL(blob), download: filename });
    document.body.appendChild(a); a.click(); a.remove();
  };

  // 加算・減算項目の編集ブロック
  const adjBlock = (title, hint, onChange) => {
    const rows = [];
    const box = el('div');
    const add = (name, amount) => {
      const nameI = el('input', { type: 'text', value: name || '', placeholder: hint });
      const amtI = el('input.amt-in', { type: 'text', inputmode: 'numeric', value: amount ? U.yen(amount) : '' });
      amtI.addEventListener('input', () => { amtI.value = amtI.value.replace(/[^\d,]/g, ''); onChange(); });
      amtI.addEventListener('blur', () => { amtI.value = amtI.value ? U.yen(U.parseYen(amtI.value)) : ''; });
      const row = el('div.adj-row', {}, [nameI, amtI, el('button.icon-btn.del', { text: '×', onclick: () => { row.remove(); rows.splice(rows.indexOf(entry), 1); onChange(); } })]);
      const entry = { row, read: () => ({ name: nameI.value, amount: U.parseYen(amtI.value) }) };
      rows.push(entry); box.appendChild(row);
    };
    const total = () => rows.reduce((s, e) => s + e.read().amount, 0);
    const card = el('div.card', {}, [
      el('div.card-head', {}, [el('h3', { text: title }), el('button.btn.sm', { text: '＋ 項目を追加', onclick: () => { add(); onChange(); } })]),
      box,
    ]);
    return { card, total, add };
  };

  ui.register('filing', async () => {
    const journals = await S.journals.loadAll();
    const s = S.settings.get();
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), s.fiscalStartMonth || 4);
    const st = R.statements(journals, fy.start, fy.end);
    const netIncome = st.pl.netIncome;

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('確定申告サポート', [
      el('span.muted', { text: `${fy.start.slice(0, 4)}年度` }),
    ]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    const resultBox = el('div');
    const rateI = el('input', { type: 'number', min: '0', step: '0.1', value: s.corporateTaxRate || 15 });
    const recompute = () => {
      const add = addBlock.total(), sub = subBlock.total();
      const income = netIncome + add - sub;
      const rate = Number(rateI.value) || 0;
      const tax = Math.floor(Math.max(0, income) * rate / 100);
      resultBox.innerHTML = '';
      resultBox.appendChild(el('div.income-result', {}, [
        el('div', {}, [
          el('div.income-line', {}, [el('span', { text: '当期純利益' }), el('span', { text: '¥' + U.yenSigned(netIncome) })]),
          el('div.income-line', {}, [el('span', { text: '加算（損金不算入など）' }), el('span', { text: '＋¥' + U.yen(add) })]),
          el('div.income-line', {}, [el('span', { text: '減算（益金不算入など）' }), el('span', { text: '△¥' + U.yen(sub) })]),
          el('div.income-line.big', {}, [el('span', { text: '所得金額' }), el('span', { text: '¥' + U.yenSigned(income) })]),
        ]),
        el('div', {}, [
          el('div.income-line', {}, [el('span', { text: '概算税率' }), el('span', {}, [rateI, el('span', { text: ' %' })])]),
          el('div.income-line.big', {}, [el('span', { text: '法人税等の概算' }), el('span', { text: '¥' + U.yen(tax) })]),
        ]),
      ]));
    };
    rateI.addEventListener('input', () => { S.settings.save({ corporateTaxRate: Number(rateI.value) || 0 }); recompute(); });

    const addBlock = adjBlock('加算項目', '例：法人税等・交際費損金不算入・減価償却超過', recompute);
    const subBlock = adjBlock('減算項目', '例：受取配当等の益金不算入', recompute);
    // よくある加算を初期表示（空欄・任意）
    addBlock.add('法人税・住民税・事業税', 0);
    recompute();

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '所得金額の計算（別表四の考え方）' }),
      el('p.muted.small', { text: '会計上の当期純利益に、税務上の加算・減算を加減して「所得金額」を求めます。金額は目安で、実際の申告区分・税率は税理士等にご確認ください。' }),
    ]));
    wrap.appendChild(el('div.two-col', {}, [addBlock.card, subBlock.card]));
    wrap.appendChild(el('div.card', {}, [el('h2', { text: '所得金額・税額の概算' }), resultBox]));

    // CSV出力
    const exportTB = () => {
      const tb = R.trialBalance(journals, fy.start, fy.end);
      const rows = [['コード', '勘定科目', '区分', '借方合計', '貸方合計', '残高']];
      tb.rows.forEach((r) => rows.push([r.code, r.name, CAT[r.category].label, r.debit, r.credit, r.balance]));
      rows.push(['', '合計', '', tb.sum.debit, tb.sum.credit, '']);
      downloadCsv(`勘定科目内訳_${fy.start.slice(0, 4)}.csv`, rows);
    };
    const exportJournals = () => {
      const list = journals.filter((j) => U.inRange(j.date, fy.start, fy.end))
        .sort((a, b) => (a.date === b.date ? a.no - b.no : a.date.localeCompare(b.date)));
      const rows = [['No', '日付', '借方科目', '借方金額', '貸方科目', '貸方金額', '税区分', '摘要']];
      list.forEach((j) => {
        const d = j.lines.filter((l) => l.side === 'debit'), c = j.lines.filter((l) => l.side === 'credit');
        const n = Math.max(d.length, c.length);
        for (let i = 0; i < n; i++) {
          rows.push([i === 0 ? j.no : '', i === 0 ? j.date : '',
            d[i] ? S.accounts.name(d[i].account) : '', d[i] ? d[i].amount : '',
            c[i] ? S.accounts.name(c[i].account) : '', c[i] ? c[i].amount : '',
            (d[i] || c[i]) ? (A.accounts.TAX_CATEGORIES[(d[i] || c[i]).tax] || {}).label || '' : '',
            i === 0 ? (j.description || '') : '']);
        }
      });
      downloadCsv(`仕訳帳_${fy.start.slice(0, 4)}.csv`, rows);
    };

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: 'データ出力（CSV）' }),
      el('p.muted.small', { text: '税理士への共有や表計算ソフトでの確認に使えます（ExcelでもそのままUTF-8で開けます）。' }),
      el('div.quick-row', {}, [
        el('button.btn', { text: '⬇ 勘定科目内訳（試算表）CSV', onclick: exportTB }),
        el('button.btn', { text: '⬇ 仕訳帳 CSV', onclick: exportJournals }),
        el('button.btn', { text: '🖨 決算書を印刷（決算ページ）', onclick: () => ui.go('closing') }),
      ]),
    ]));
    return wrap;
  });
})();
