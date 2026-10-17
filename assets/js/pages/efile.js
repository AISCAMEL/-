/* efile.js ― 電子申告向けデータ出力（消費税申告書サマリ・法人税基礎データ） */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  const downloadCsv = (filename, rows) => {
    const esc = (v) => { const s = String(v == null ? '' : v); return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
    const blob = new Blob(['﻿' + rows.map((r) => r.map(esc).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = el('a', { href: URL.createObjectURL(blob), download: filename });
    document.body.appendChild(a); a.click(); a.remove();
  };
  const downloadText = (filename, text, mime) => {
    const blob = new Blob([text], { type: (mime || 'text/plain') + ';charset=utf-8' });
    const a = el('a', { href: URL.createObjectURL(blob), download: filename });
    document.body.appendChild(a); a.click(); a.remove();
  };
  const xmlEsc = (s) => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const line = (label, value, big) => el('div.income-line' + (big ? '.big' : ''), {}, [el('span', { text: label }), el('span', { text: '¥' + U.yenSigned(value) })]);

  ui.register('efile', async () => {
    const journals = await S.journals.loadAll();
    const s = S.settings.get();
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), s.fiscalStartMonth || 4);
    const t = R.taxSummary(journals, fy.start, fy.end);
    const method = s.taxFilingMethod || 'general';
    const bizType = s.simplifiedBizType || 5;
    const ret = R.taxReturnCalc(t, method, bizType);
    const st = R.statements(journals, fy.start, fy.end);
    const methodLabel = { general: '原則課税', simplified: '簡易課税', special20: '2割特例' }[method];

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('電子申告データ出力', [el('span.muted', { text: `${fy.start.slice(0, 4)}年度` })]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    // 消費税申告書サマリ
    const vatCsv = () => downloadCsv(`消費税申告書サマリ_${fy.start.slice(0, 4)}.csv`, [
      ['項目', '金額'],
      ['計算方式', methodLabel],
      ['課税標準額（千円未満切捨）', ret.taxableBase],
      ['消費税額（国税7.8%等）', ret.nationalTax],
      ['控除対象仕入税額', ret.deduction],
      ['差引税額（国税・百円未満切捨）', ret.nationalPayable],
      ['地方消費税', ret.localTax],
      ['納付税額 合計', ret.totalPayable],
    ]);
    wrap.appendChild(el('div.card', {}, [
      el('div.card-head', {}, [el('h2', { text: `消費税申告書サマリ（${methodLabel}）` }), el('button.btn.sm', { text: '⬇ CSV', onclick: vatCsv })]),
      line('課税標準額（千円未満切捨）', ret.taxableBase),
      line('消費税額（国税分）', ret.nationalTax),
      line('控除対象仕入税額', -ret.deduction),
      line('差引税額（国税・百円未満切捨）', ret.nationalPayable),
      line('地方消費税（国税×22/78）', ret.localTax),
      line('納付税額 合計', ret.totalPayable, true),
      el('p.muted.small', { text: '※ 割戻し計算による概算です。e-Tax/eLTAX の申告書に転記する際の目安としてご利用ください。' }),
    ]));

    // 法人税 基礎データ
    const rate = s.corporateTaxRate || 15;
    const roughTax = Math.floor(Math.max(0, st.pl.netIncome) * rate / 100);
    const citCsv = () => downloadCsv(`法人税基礎データ_${fy.start.slice(0, 4)}.csv`, [
      ['項目', '金額'],
      ['売上（収益）合計', st.pl.revenue.total],
      ['費用合計', st.pl.expense.total],
      ['当期純利益', st.pl.netIncome],
      ['概算税率(%)', rate],
      ['法人税等の概算', roughTax],
    ]);
    wrap.appendChild(el('div.card', {}, [
      el('div.card-head', {}, [el('h2', { text: '法人税 基礎データ' }), el('button.btn.sm', { text: '⬇ CSV', onclick: citCsv })]),
      line('当期純利益', st.pl.netIncome),
      line(`法人税等の概算（${rate}%）`, roughTax, true),
      el('p.muted.small', { html: '加算・減算を反映した所得金額は<b>申告サポート</b>で計算できます。' }),
      el('div.quick-row', {}, [el('button.btn.sm', { text: '申告サポートへ', onclick: () => ui.go('filing') })]),
    ]));

    // e-Tax取込用データ（財務諸表・勘定科目内訳）
    const CAT = A.accounts.CATEGORIES;
    const financialCsv = () => {
      const rows = [['区分', '科目コード', '科目名', '当期金額']];
      const push = (secLabel, items) => items.filter((i) => i.amount).forEach((i) => rows.push([secLabel, i.code || '', i.name, i.amount]));
      push('資産', st.bs.asset.items);
      push('負債', st.bs.liability.items);
      push('純資産', [...st.bs.equity.items, { name: '当期純利益', amount: st.bs.netIncome }]);
      push('収益', st.pl.revenue.items);
      push('費用', st.pl.expense.items);
      downloadCsv(`財務諸表_${fy.start.slice(0, 4)}.csv`, rows);
    };
    const arCsv = async () => {
      const invoices = await S.invoices.loadAll();
      const map = {};
      invoices.filter((iv) => iv.type === 'invoice' && iv.posted && !iv.paid).forEach((iv) => {
        map[iv.partnerName || '（不明）'] = (map[iv.partnerName || '（不明）'] || 0) + S.invoices.calc(iv).total;
      });
      const rows = [['取引先', '売掛金残高']];
      Object.keys(map).forEach((k) => rows.push([k, map[k]]));
      if (rows.length === 1) return ui.toast('未回収の売掛金がありません');
      downloadCsv(`売掛金内訳_${fy.start.slice(0, 4)}.csv`, rows);
    };

    // e-Tax連携用XML（財務諸表・消費税・法人税を構造化）
    const buildXml = () => {
      const secXml = (name, items) => `    <${name}>\n` + items.filter((i) => i.amount).map((i) =>
        `      <科目 コード="${xmlEsc(i.code || '')}" 名称="${xmlEsc(i.name)}" 金額="${Math.round(i.amount)}"/>`).join('\n') + `\n    </${name}>`;
      const bsRight = [...st.bs.liability.items, ...st.bs.equity.items, { name: '当期純利益', amount: st.bs.netIncome }];
      return `<?xml version="1.0" encoding="UTF-8"?>
<会計データ 規格="aizu-kaikei/1.0" 会社名="${xmlEsc(s.name)}" 登録番号="${xmlEsc(s.invoiceRegNo || '')}" 会計期間開始="${fy.start}" 会計期間終了="${fy.end}">
  <財務諸表>
    <貸借対照表>
${secXml('資産の部', st.bs.asset.items)}
${secXml('負債純資産の部', bsRight)}
    </貸借対照表>
    <損益計算書>
${secXml('収益', st.pl.revenue.items)}
${secXml('費用', st.pl.expense.items)}
      <当期純利益 金額="${Math.round(st.pl.netIncome)}"/>
    </損益計算書>
  </財務諸表>
  <消費税申告 計算方式="${xmlEsc(methodLabel)}">
    <課税標準額>${ret.taxableBase}</課税標準額>
    <消費税額>${ret.nationalTax}</消費税額>
    <控除対象仕入税額>${ret.deduction}</控除対象仕入税額>
    <差引税額>${ret.nationalPayable}</差引税額>
    <地方消費税>${ret.localTax}</地方消費税>
    <納付税額合計>${ret.totalPayable}</納付税額合計>
  </消費税申告>
  <法人税基礎>
    <当期純利益>${Math.round(st.pl.netIncome)}</当期純利益>
    <概算税率>${s.corporateTaxRate || 15}</概算税率>
  </法人税基礎>
</会計データ>`;
    };

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: 'e-Tax取込用データ（財務諸表・内訳）' }),
      el('p.muted.small', { text: '財務諸表（決算書）と勘定科目内訳の基礎データをCSV／XMLで出力します。e-Tax の財務諸表・勘定科目内訳明細書の作成に取り込む際の元データとしてご利用ください。' }),
      el('div.quick-row', {}, [
        el('button.btn', { text: '⬇ 財務諸表 CSV', onclick: financialCsv }),
        el('button.btn', { text: '⬇ 売掛金内訳 CSV', onclick: arCsv }),
        el('button.btn', { text: '⬇ 会計データ XML', onclick: () => downloadText(`会計データ_${fy.start.slice(0, 4)}.xml`, buildXml(), 'application/xml') }),
      ]),
    ]));

    wrap.appendChild(el('div.card', {}, [
      el('p.muted.small', { text: 'e-Tax（国税）・eLTAX（地方税）の電子申告では、これらの金額を各申告書の該当欄へ入力、または会計ソフト連携CSVとしてご利用ください。最終的な申告内容は税理士等にご確認ください。' }),
    ]));
    return wrap;
  });
})();
