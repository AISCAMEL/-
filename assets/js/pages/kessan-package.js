/* kessan-package.js ― 決算パッケージ（税理士提出用の書類一式を一括出力）
 *   ・PDF一括：表紙＋決算書(BS/PL)＋試算表＋消費税集計＋固定資産＋勘定科目内訳 を1枚にまとめて印刷/PDF
 *   ・CSV一式：仕訳帳・総勘定元帳・試算表・BS・PL・消費税集計・固定資産台帳・内訳明細を ZIP で出力
 */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;
  const CAT = A.accounts.CATEGORIES;
  const TAX = A.accounts.TAX_CATEGORIES;

  /* ---- CSVユーティリティ ---------------------------------------------- */
  const csvString = (rows) => {
    const esc = (v) => { const s = String(v == null ? '' : v); return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
    return '﻿' + rows.map((r) => r.map(esc).join(',')).join('\r\n'); // BOM付きUTF-8
  };
  const downloadCsv = (filename, rows) => {
    const blob = new Blob([csvString(rows)], { type: 'text/csv;charset=utf-8' });
    const a = el('a', { href: URL.createObjectURL(blob), download: filename }); document.body.appendChild(a); a.click(); a.remove();
  };
  const downloadBlob = (filename, blob) => { const a = el('a', { href: URL.createObjectURL(blob), download: filename }); document.body.appendChild(a); a.click(); a.remove(); };

  /* ---- 各CSVの行データを生成 ------------------------------------------ */
  const buildCsvSet = (ctx) => {
    const { journals, invoices, assets, st, tax, bd, fy, s } = ctx;
    const fyy = fy.start.slice(0, 4);
    const set = [];

    // 仕訳帳
    const jRows = [['No', '日付', '借方科目', '借方金額', '貸方科目', '貸方金額', '税区分', '摘要']];
    journals.filter((j) => U.inRange(j.date, fy.start, fy.end))
      .sort((a, b) => (a.date === b.date ? a.no - b.no : a.date.localeCompare(b.date)))
      .forEach((j) => {
        const d = j.lines.filter((l) => l.side === 'debit'), c = j.lines.filter((l) => l.side === 'credit');
        const n = Math.max(d.length, c.length);
        for (let i = 0; i < n; i++) jRows.push([i === 0 ? j.no : '', i === 0 ? j.date : '',
          d[i] ? S.accounts.name(d[i].account) : '', d[i] ? d[i].amount : '',
          c[i] ? S.accounts.name(c[i].account) : '', c[i] ? c[i].amount : '',
          (d[i] || c[i]) ? (TAX[(d[i] || c[i]).tax] || {}).label || '' : '', i === 0 ? (j.description || '') : '']);
      });
    set.push({ name: `01_仕訳帳_${fyy}.csv`, rows: jRows });

    // 総勘定元帳
    const gRows = [['コード', '勘定科目', '日付', '摘要', '借方', '貸方', '残高']];
    S.accounts.all().forEach((a) => {
      const lg = R.ledger(journals, a.code, fy.start, fy.end);
      if (!lg.rows.length && !lg.opening && !lg.closing) return;
      gRows.push([a.code, a.name, fy.start, '前期繰越', '', '', lg.opening]);
      lg.rows.forEach((r) => gRows.push([a.code, a.name, r.date, r.desc || '', r.debit || '', r.credit || '', r.balance]));
      gRows.push([a.code, a.name, fy.end, '次期繰越', '', '', lg.closing]);
    });
    set.push({ name: `02_総勘定元帳_${fyy}.csv`, rows: gRows });

    // 合計残高試算表
    const tb = R.trialBalance(journals, fy.start, fy.end);
    const tbRows = [['コード', '勘定科目', '区分', '借方合計', '貸方合計', '残高']];
    tb.rows.forEach((r) => tbRows.push([r.code, r.name, CAT[r.category].label, r.debit, r.credit, r.balance]));
    tbRows.push(['', '合計', '', tb.sum.debit, tb.sum.credit, '']);
    set.push({ name: `03_合計残高試算表_${fyy}.csv`, rows: tbRows });

    // 損益計算書
    const plRows = [['区分', '科目', '金額']];
    st.pl.revenue.items.forEach((i) => plRows.push(['収益', i.name, i.amount]));
    plRows.push(['', '収益合計', st.pl.revenue.total]);
    st.pl.expense.items.forEach((i) => plRows.push(['費用', i.name, i.amount]));
    plRows.push(['', '費用合計', st.pl.expense.total]);
    plRows.push(['', '当期純利益', st.pl.netIncome]);
    set.push({ name: `04_損益計算書_${fyy}.csv`, rows: plRows });

    // 貸借対照表
    const bsRows = [['区分', '科目', '金額']];
    st.bs.asset.items.forEach((i) => bsRows.push(['資産', i.name, i.amount]));
    bsRows.push(['', '資産合計', st.bs.asset.total]);
    st.bs.liability.items.forEach((i) => bsRows.push(['負債', i.name, i.amount]));
    st.bs.equity.items.forEach((i) => bsRows.push(['純資産', i.name, i.amount]));
    if (st.bs.netIncome) bsRows.push(['純資産', '当期純利益', st.bs.netIncome]);
    bsRows.push(['', '負債・純資産合計', st.bs.liabilityAndEquity]);
    set.push({ name: `05_貸借対照表_${fyy}.csv`, rows: bsRows });

    // 消費税集計
    const vatRows = [['項目', '金額']];
    vatRows.push(['課税売上（税抜）', tax.salesNet], ['仮受消費税', tax.salesTax],
      ['課税仕入（税抜）', tax.purchaseNet], ['仮払消費税', tax.purchaseTax], ['差引 納付税額', tax.payable]);
    set.push({ name: `06_消費税集計_${fyy}.csv`, rows: vatRows });

    // 固定資産台帳
    const faRows = [['資産名', '取得年月日', '取得価額', '期末帳簿価額']];
    bd.fixedAssets.forEach((a) => faRows.push([a.name, a.acquireDate, a.acquireCost, a.bookValue]));
    set.push({ name: `07_固定資産台帳_${fyy}.csv`, rows: faRows });

    // 勘定科目内訳明細書
    const ivRows = [['内訳区分', '相手先/科目', '金額', '備考']];
    bd.deposits.forEach((d) => ivRows.push(['預貯金', d.name, d.amount, '']));
    bd.receivables.forEach((r) => ivRows.push(['売掛金', r.partner, r.amount, '未回収の請求書より']));
    if (bd.receivableAccountBalance) ivRows.push(['売掛金', '（売掛金 勘定残高）', bd.receivableAccountBalance, '帳簿残高']);
    bd.payables.forEach((p) => ivRows.push(['買掛金・未払金等', p.name, p.amount, '']));
    bd.borrowings.forEach((b) => ivRows.push(['借入金', b.name, b.amount, '']));
    bd.fixedAssets.forEach((a) => ivRows.push(['固定資産', a.name, a.bookValue, `取得価額 ${a.acquireCost}`]));
    set.push({ name: `08_勘定科目内訳明細書_${fyy}.csv`, rows: ivRows });

    return set;
  };

  /* ---- PDF一括（印刷/PDF） -------------------------------------------- */
  const printAll = (ctx) => {
    const { st, tax, bd, fy, s } = ctx;
    const money = (n) => '¥' + U.yenSigned(n);
    const rowsOf = (items, nameKey, amtKey) => items.filter((i) => i[amtKey]).map((i) => `<tr><td>${U.esc(i[nameKey])}</td><td class="r">${money(i[amtKey])}</td></tr>`).join('') || '<tr><td colspan=2>—</td></tr>';
    const bsRight = [...st.bs.liability.items, ...st.bs.equity.items, { name: '当期純利益', amount: st.bs.netIncome }];
    const tb = R.trialBalance(ctx.journals, fy.start, fy.end);
    const tbBody = tb.rows.map((r) => `<tr><td>${r.code}</td><td>${U.esc(r.name)}</td><td class="r">¥${U.yen(r.debit)}</td><td class="r">¥${U.yen(r.credit)}</td><td class="r">${money(r.balance)}</td></tr>`).join('');
    const faBody = bd.fixedAssets.map((a) => `<tr><td>${U.esc(a.name)}</td><td>${U.fmtDate(a.acquireDate)}</td><td class="r">¥${U.yen(a.acquireCost)}</td><td class="r">¥${U.yen(a.bookValue)}</td></tr>`).join('') || '<tr><td colspan=4>—</td></tr>';
    const ivBlock = (title, body) => `<h3 class="rep-h">${title}</h3><table class="items">${body}</table>`;
    const listTbl = (head, arr, nameKey, amtKey) => `<thead><tr><th>${head}</th><th class="r">金額</th></tr></thead><tbody>${rowsOf(arr, nameKey, amtKey)}</tbody>`;

    const html = `
      <div class="doc report-doc">
        <div class="doc-title" style="letter-spacing:.2em">決 算 報 告 書（一式）</div>
        <div style="text-align:center;margin-bottom:4px;font-size:1.05rem">${U.esc(s.name)}</div>
        <div style="text-align:center" class="muted">${U.fmtDate(fy.start)} 〜 ${U.fmtDate(fy.end)}</div>
        <div class="muted small" style="text-align:center;margin:6px 0 2px">含まれる書類：損益計算書／貸借対照表／合計残高試算表／消費税集計／固定資産台帳／勘定科目内訳明細書</div>

        <h3 class="rep-h">損益計算書（P/L）</h3>
        <div class="two-col">
          <table class="items">${listTbl('費用', st.pl.expense.items, 'name', 'amount')}<tfoot><tr><td>費用合計</td><td class="r">¥${U.yen(st.pl.expense.total)}</td></tr></tfoot></table>
          <table class="items">${listTbl('収益', st.pl.revenue.items, 'name', 'amount')}<tfoot><tr><td>収益合計</td><td class="r">¥${U.yen(st.pl.revenue.total)}</td></tr></tfoot></table>
        </div>
        <div class="doc-grand">当期純利益　<b>${money(st.pl.netIncome)}</b></div>

        <h3 class="rep-h">貸借対照表（B/S）</h3>
        <div class="two-col">
          <table class="items">${listTbl('資産の部', st.bs.asset.items, 'name', 'amount')}<tfoot><tr><td>資産合計</td><td class="r">¥${U.yen(st.bs.asset.total)}</td></tr></tfoot></table>
          <table class="items"><thead><tr><th>負債・純資産の部</th><th class="r">金額</th></tr></thead><tbody>${rowsOf(bsRight, 'name', 'amount')}</tbody><tfoot><tr><td>負債・純資産合計</td><td class="r">¥${U.yen(st.bs.liabilityAndEquity)}</td></tr></tfoot></table>
        </div>

        <h3 class="rep-h">合計残高試算表</h3>
        <table class="items"><thead><tr><th>コード</th><th>勘定科目</th><th class="r">借方合計</th><th class="r">貸方合計</th><th class="r">残高</th></tr></thead>
          <tbody>${tbBody}</tbody>
          <tfoot><tr><td></td><td>合計</td><td class="r">¥${U.yen(tb.sum.debit)}</td><td class="r">¥${U.yen(tb.sum.credit)}</td><td class="r"></td></tr></tfoot></table>

        <h3 class="rep-h">消費税集計（インボイス対応）</h3>
        <table class="sum" style="min-width:100%">
          <tr><td>課税売上（税抜）</td><td class="r">¥${U.yen(tax.salesNet)}</td><td>仮受消費税</td><td class="r">¥${U.yen(tax.salesTax)}</td></tr>
          <tr><td>課税仕入（税抜）</td><td class="r">¥${U.yen(tax.purchaseNet)}</td><td>仮払消費税</td><td class="r">¥${U.yen(tax.purchaseTax)}</td></tr>
          <tr class="grand"><td>差引 納付税額</td><td class="r"></td><td></td><td class="r">${money(tax.payable)}</td></tr>
        </table>

        <h3 class="rep-h">固定資産台帳</h3>
        <table class="items"><thead><tr><th>資産名</th><th>取得年月日</th><th class="r">取得価額</th><th class="r">期末帳簿価額</th></tr></thead><tbody>${faBody}</tbody></table>

        <div class="doc-title" style="font-size:1.1rem;letter-spacing:.15em;margin-top:24px">勘定科目内訳明細書</div>
        ${ivBlock('預貯金等の内訳', listTbl('金融機関/科目', bd.deposits, 'name', 'amount'))}
        ${ivBlock('売掛金（未収入金）の内訳（取引先別）', `<thead><tr><th>取引先</th><th class="r">金額</th></tr></thead><tbody>${rowsOf(bd.receivables, 'partner', 'amount')}</tbody>`)}
        ${ivBlock('買掛金・未払金等の内訳', listTbl('科目', bd.payables, 'name', 'amount'))}
        ${ivBlock('借入金の内訳', listTbl('科目', bd.borrowings, 'name', 'amount'))}
        ${ivBlock('固定資産の内訳', `<thead><tr><th>資産名</th><th class="r">期末簿価</th></tr></thead><tbody>${rowsOf(bd.fixedAssets, 'name', 'bookValue')}</tbody>`)}

        <div class="muted small" style="margin-top:20px">※ 本書は社内確認用の概算・集計です。法人税の申告書作成および最終判断は税理士等にご確認ください。</div>
      </div>`;

    const overlay = el('div.print-overlay');
    overlay.appendChild(el('div.print-bar.no-print', {}, [
      el('button.btn.primary', { text: '🖨 印刷 / PDF保存', onclick: () => window.print() }),
      el('button.btn', { text: '閉じる', onclick: () => { overlay.remove(); document.body.classList.remove('printing'); } }),
    ]));
    overlay.appendChild(el('div.print-sheet', { html }));
    document.body.appendChild(overlay);
    document.body.classList.add('printing');
  };

  /* ---- ページ --------------------------------------------------------- */
  ui.register('package', async () => {
    const journals = await S.journals.loadAll();
    const invoices = await S.invoices.loadAll();
    const assets = await S.assets.loadAll();
    const s = S.settings.get();
    const fsMonth = s.fiscalStartMonth || 4;
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), fsMonth);
    const st = R.statements(journals, fy.start, fy.end);
    const tax = R.taxSummary(journals, fy.start, fy.end);
    const bd = R.breakdown(journals, invoices, assets, fy.start, fy.end, (iv) => S.invoices.calc(iv), fsMonth);
    const ctx = { journals, invoices, assets, st, tax, bd, fy, s };

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('決算パッケージ（税理士提出用）', [
      el('span.muted', { text: `${fy.start.slice(0, 4)}年度（${U.fmtDate(fy.start)}〜${U.fmtDate(fy.end)}）` }),
    ]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    const downloadZip = () => {
      const set = buildCsvSet(ctx);
      const files = set.map((f) => ({ name: f.name, data: csvString(f.rows) }));
      const readme = [
        '決算パッケージ（税理士提出用CSV一式）', '',
        `会社名: ${s.name || ''}`, `対象年度: ${U.fmtDate(fy.start)} 〜 ${U.fmtDate(fy.end)}`,
        `出力日: ${U.today()}`, '',
        '含まれるファイル:', ...set.map((f) => ' ・' + f.name),
        '', '※ 金額は税込経理ベースの集計です。法人税申告書の作成・最終判断は税理士にご確認ください。',
      ].join('\r\n');
      files.unshift({ name: '00_README.txt', data: '﻿' + readme });
      const blob = A.zip.create(files);
      downloadBlob(`決算パッケージ_${s.name || ''}_${fy.start.slice(0, 4)}.zip`, blob);
      ui.toast(`CSV ${set.length}種をZIPで出力しました`, 'ok');
    };

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '一括出力' }),
      el('p.muted.small', { text: 'ボタン一つで、税理士にそのまま渡せる書類をまとめて出力します。PDFは「見せる書類」、CSVは「会計・申告ソフトに取り込む明細データ」としてお使いください。' }),
      el('div.quick-row', {}, [
        el('button.btn.primary', { text: '🖨 PDF一括（決算書・試算表・消費税・固定資産・内訳）', onclick: () => printAll(ctx) }),
        el('button.btn', { text: '🗂 CSV一式（ZIP）をダウンロード', onclick: downloadZip }),
      ]),
      el('ul.muted.small', {}, [
        '仕訳帳 / 総勘定元帳 / 合計残高試算表',
        '損益計算書(PL) / 貸借対照表(BS)',
        '消費税集計 / 固定資産台帳 / 勘定科目内訳明細書',
      ].map((t) => el('li', { text: t }))),
    ]));

    // 勘定科目内訳明細書のプレビュー
    const sec = (title, head, rows) => {
      const card = el('div.card', {}, [el('h2', { text: title })]);
      card.appendChild(ui.table(head, rows, { empty: '該当なし' }));
      return card;
    };
    wrap.appendChild(sec('預貯金等の内訳', [
      { key: 'name', label: '金融機関/科目', render: (r) => r.name },
      { key: 'amount', label: '残高', align: 'right', render: (r) => '¥' + U.yenSigned(r.amount) },
    ], bd.deposits));
    wrap.appendChild(sec('売掛金（未収入金）の内訳（取引先別・未回収請求書）', [
      { key: 'partner', label: '取引先', render: (r) => r.partner },
      { key: 'amount', label: '金額', align: 'right', render: (r) => '¥' + U.yen(r.amount) },
    ], bd.receivables));
    wrap.appendChild(sec('買掛金・未払金等の内訳', [
      { key: 'name', label: '科目', render: (r) => r.name },
      { key: 'amount', label: '残高', align: 'right', render: (r) => '¥' + U.yenSigned(r.amount) },
    ], bd.payables));
    wrap.appendChild(sec('借入金の内訳', [
      { key: 'name', label: '科目', render: (r) => r.name },
      { key: 'amount', label: '残高', align: 'right', render: (r) => '¥' + U.yenSigned(r.amount) },
    ], bd.borrowings));
    wrap.appendChild(sec('固定資産の内訳', [
      { key: 'name', label: '資産名', render: (r) => r.name },
      { key: 'acquireCost', label: '取得価額', align: 'right', render: (r) => '¥' + U.yen(r.acquireCost) },
      { key: 'bookValue', label: '期末簿価', align: 'right', render: (r) => '¥' + U.yen(r.bookValue) },
    ], bd.fixedAssets));

    return wrap;
  });

  // 他ページ/テストから使えるよう公開
  A.kessanPackage = { buildCsvSet, csvString };
})();
