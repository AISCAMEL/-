/* closing.js ― 決算（損益振替）・確定申告向け決算報告書 */
window.A = window.A || {};
(function () {
  'use strict';
  const U = A.util, el = U.el, ui = A.ui, S = A.store, R = A.reports;

  // 損益振替仕訳の明細を作る（収益・費用を繰越利益剰余金へ振替）
  const buildClosingLines = (st) => {
    const lines = [];
    st.pl.revenue.items.forEach((it) => {
      if (!it.amount) return;
      lines.push({ side: it.amount >= 0 ? 'debit' : 'credit', account: it.code, tax: 'out', amount: Math.abs(it.amount) });
    });
    st.pl.expense.items.forEach((it) => {
      if (!it.amount) return;
      lines.push({ side: it.amount >= 0 ? 'credit' : 'debit', account: it.code, tax: 'out', amount: Math.abs(it.amount) });
    });
    const net = st.pl.netIncome; // 繰越利益剰余金へ
    if (net !== 0) lines.push({ side: net >= 0 ? 'credit' : 'debit', account: '320', tax: 'out', amount: Math.abs(net) });
    return lines;
  };

  const check = (ok, label, detail) => el('div.check-row', {}, [
    el('span.check-ico.' + (ok ? 'ok' : 'ng'), { text: ok ? '✓' : '！' }),
    el('span', { html: `${label}${detail ? ` <span class="muted small">${detail}</span>` : ''}` }),
  ]);

  ui.register('closing', async () => {
    const journals = await S.journals.loadAll();
    const assets = await S.assets.loadAll();
    const s = S.settings.get();
    const fsMonth = s.fiscalStartMonth || 4;
    const p = A.app.period();
    const fy = U.fiscalRange(p.start || U.today(), fsMonth);
    const st = R.statements(journals, fy.start, fy.end);
    const tax = R.taxSummary(journals, fy.start, fy.end);

    const alreadyClosed = journals.some((j) => j.source === 'closing' && U.inRange(j.date, fy.start, fy.end));
    const depPending = assets.filter((a) => !a.disposed).filter((a) => {
      const row = R.depForFiscalYear(a, fsMonth, fy.start);
      if (!row) return false;
      return !journals.some((j) => j.source === 'depreciation' && j.refId === a.id && U.inRange(j.date, fy.start, fy.end));
    });

    const wrap = el('div');
    wrap.appendChild(ui.pageHead('決算・確定申告サポート', [
      el('span.muted', { text: `${fy.start.slice(0, 4)}年度（${U.fmtDate(fy.start)}〜${U.fmtDate(fy.end)}）` }),
    ]));
    wrap.appendChild(el('div.card', {}, [ui.periodBar(p, (np) => { A.app.setPeriod(np); ui.renderRoute(); })]));

    // チェックリスト
    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '年度締めチェックリスト' }),
      check(st.bs.balanced, '貸借対照表が一致している', st.bs.balanced ? '' : '仕訳をご確認ください'),
      check(depPending.length === 0, '固定資産の当期減価償却を計上済み', depPending.length ? `未計上 ${depPending.length}件（固定資産ページで計上）` : ''),
      check(alreadyClosed, '損益振替（決算整理）を計上済み', alreadyClosed ? '' : '下のボタンで実行できます'),
    ]));

    // 当期業績サマリ＋損益振替
    const doClose = async () => {
      if (alreadyClosed) return ui.toast('この年度は既に損益振替済みです', 'err');
      const lines = buildClosingLines(st);
      if (!lines.length) return ui.toast('振替対象の損益がありません', 'err');
      const t = S.journals.totals({ lines });
      if (!t.balanced) return ui.toast('振替仕訳の貸借が一致しません', 'err');
      if (!await ui.confirm(`当期純利益 ¥${U.yenSigned(st.pl.netIncome)} を繰越利益剰余金へ振り替えます。よろしいですか？`)) return;
      await S.journals.save({ source: 'closing', date: fy.end, description: `損益振替（${fy.start.slice(0, 4)}年度決算）`, lines });
      ui.toast('損益振替を計上しました', 'ok');
      ui.renderRoute();
    };

    wrap.appendChild(el('div.card', {}, [
      el('h2', { text: '当期業績と損益振替' }),
      el('div.stat-grid', {}, [
        (() => el('div.stat.good', {}, [el('div.stat-label', { text: '売上（収益）' }), el('div.stat-value', { text: '¥' + U.yen(st.pl.revenue.total) })]))(),
        (() => el('div.stat.warn', {}, [el('div.stat-label', { text: '費用' }), el('div.stat-value', { text: '¥' + U.yen(st.pl.expense.total) })]))(),
        (() => el('div.stat.' + (st.pl.netIncome >= 0 ? 'good' : 'bad'), {}, [el('div.stat-label', { text: '当期純利益' }), el('div.stat-value', { text: '¥' + U.yenSigned(st.pl.netIncome) })]))(),
        (() => el('div.stat', {}, [el('div.stat-label', { text: '消費税 納付試算' }), el('div.stat-value', { text: '¥' + U.yenSigned(tax.payable) })]))(),
      ]),
      el('p.muted.small', { text: '損益振替は、収益・費用を締めて当期純利益を繰越利益剰余金へ振り替える決算整理仕訳です。翌年度の期首残高は「期首残高・繰越」から作成できます。' }),
      el('div.quick-row', {}, [
        el('button.btn.primary', { text: alreadyClosed ? '損益振替は計上済み' : '損益振替を計上する', disabled: alreadyClosed, onclick: doClose }),
        el('button.btn', { text: '🖨 決算報告書を表示（印刷/PDF）', onclick: () => reportView(s, st, tax, assets, fy) }),
      ]),
    ]));
    return wrap;
  });

  /* ---- 決算報告書（印刷/PDF） ----------------------------------------- */
  const reportView = (s, st, tax, assets, fy) => {
    const rows = (items) => items.filter((i) => i.amount).map((i) => `<tr><td>${U.esc(i.name)}</td><td class="r">¥${U.yenSigned(i.amount)}</td></tr>`).join('') || '<tr><td colspan=2>—</td></tr>';
    const bsRight = [...st.bs.liability.items, ...st.bs.equity.items, { name: '当期純利益', amount: st.bs.netIncome }];
    const assetRows = assets.map((a) => `<tr><td>${U.esc(a.name)}</td><td class="r">¥${U.yen(a.acquireCost)}</td></tr>`).join('') || '<tr><td colspan=2>—</td></tr>';
    const html = `
      <div class="doc report-doc">
        <div class="doc-title" style="letter-spacing:.2em">決 算 報 告 書</div>
        <div style="text-align:center;margin-bottom:6px">${U.esc(s.name)}</div>
        <div style="text-align:center" class="muted">${U.fmtDate(fy.start)} 〜 ${U.fmtDate(fy.end)}</div>
        <h3 class="rep-h">損益計算書（P/L）</h3>
        <div class="two-col">
          <table class="items"><thead><tr><th>費用</th><th class="r">金額</th></tr></thead><tbody>${rows(st.pl.expense.items)}</tbody>
            <tfoot><tr><td>費用合計</td><td class="r">¥${U.yen(st.pl.expense.total)}</td></tr></tfoot></table>
          <table class="items"><thead><tr><th>収益</th><th class="r">金額</th></tr></thead><tbody>${rows(st.pl.revenue.items)}</tbody>
            <tfoot><tr><td>収益合計</td><td class="r">¥${U.yen(st.pl.revenue.total)}</td></tr></tfoot></table>
        </div>
        <div class="doc-grand">当期純利益　<b>¥${U.yenSigned(st.pl.netIncome)}</b></div>
        <h3 class="rep-h">貸借対照表（B/S）</h3>
        <div class="two-col">
          <table class="items"><thead><tr><th>資産の部</th><th class="r">金額</th></tr></thead><tbody>${rows(st.bs.asset.items)}</tbody>
            <tfoot><tr><td>資産合計</td><td class="r">¥${U.yen(st.bs.asset.total)}</td></tr></tfoot></table>
          <table class="items"><thead><tr><th>負債・純資産の部</th><th class="r">金額</th></tr></thead><tbody>${rows(bsRight)}</tbody>
            <tfoot><tr><td>負債・純資産合計</td><td class="r">¥${U.yen(st.bs.liabilityAndEquity)}</td></tr></tfoot></table>
        </div>
        <h3 class="rep-h">消費税集計</h3>
        <table class="sum" style="min-width:100%">
          <tr><td>課税売上（税抜）</td><td class="r">¥${U.yen(tax.salesNet)}</td><td>仮受消費税</td><td class="r">¥${U.yen(tax.salesTax)}</td></tr>
          <tr><td>課税仕入（税抜）</td><td class="r">¥${U.yen(tax.purchaseNet)}</td><td>仮払消費税</td><td class="r">¥${U.yen(tax.purchaseTax)}</td></tr>
          <tr class="grand"><td>差引 納付税額</td><td class="r"></td><td></td><td class="r">¥${U.yenSigned(tax.payable)}</td></tr>
        </table>
        ${assets.length ? `<h3 class="rep-h">固定資産一覧</h3><table class="items"><thead><tr><th>資産名</th><th class="r">取得価額</th></tr></thead><tbody>${assetRows}</tbody></table>` : ''}
        <div class="muted small" style="margin-top:20px">※ 本書は社内確認用の概算です。確定申告の最終判断は税理士等にご確認ください。</div>
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
})();
