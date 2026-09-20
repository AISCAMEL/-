/* =========================================================================
 * reports.js  ―  帳票計算（総勘定元帳・試算表・BS・PL・消費税集計）
 * すべて仕訳(journals)から集計する。副作用なしの純粋計算。
 * ========================================================================= */
window.A = window.A || {};

A.reports = (function () {
  'use strict';
  const S = A.store;
  const U = A.util;
  const CAT = A.accounts.CATEGORIES;
  const TAX = A.accounts.TAX_CATEGORIES;

  // 期間で仕訳明細を平坦化。返り値: [{date,no,description,account,side,amount,tax,jid}]
  const flatLines = (journals, start, end) => {
    const rows = [];
    journals.forEach((j) => {
      if (!U.inRange(j.date, start, end)) return;
      (j.lines || []).forEach((l) => {
        rows.push({
          date: j.date, no: j.no, description: j.description || '',
          account: l.account, side: l.side, amount: Number(l.amount) || 0,
          tax: l.tax || 'out', jid: j.id, memo: l.memo || '',
        });
      });
    });
    return rows;
  };

  /* ---- 総勘定元帳 ------------------------------------------------------
   * 指定勘定の明細＋残高推移。開始残高(opening)は期間開始前の累計。
   * ------------------------------------------------------------------- */
  const ledger = (journals, code, start, end) => {
    const acc = S.accounts.byCode(code);
    const debitNormal = acc && CAT[acc.category].side === 'debit';
    const sign = (side) => (side === 'debit' ? 1 : -1) * (debitNormal ? 1 : -1);

    // 期首残高＝開始日より前の累計。開始日未指定（全期間）なら 0。
    let opening = 0;
    if (start) {
      flatLines(journals, null, prevDay(start))
        .filter((r) => r.account === code)
        .forEach((r) => { opening += sign(r.side) * r.amount; });
    }

    let bal = opening;
    const rows = flatLines(journals, start, end)
      .filter((r) => r.account === code)
      .sort((a, b) => (a.date === b.date ? a.no - b.no : a.date.localeCompare(b.date)))
      .map((r) => {
        bal += sign(r.side) * r.amount;
        return {
          ...r,
          debit: r.side === 'debit' ? r.amount : 0,
          credit: r.side === 'credit' ? r.amount : 0,
          balance: bal,
        };
      });
    return { opening, rows, closing: bal, debitNormal };
  };
  const prevDay = (d) => {
    const dt = new Date(d + 'T00:00:00'); dt.setDate(dt.getDate() - 1);
    const p = (x) => String(x).padStart(2, '0');
    return `${dt.getFullYear()}-${p(dt.getMonth() + 1)}-${p(dt.getDate())}`;
  };

  /* ---- 試算表 ----------------------------------------------------------
   * 勘定ごとの借方合計・貸方合計・残高。
   * ------------------------------------------------------------------- */
  const trialBalance = (journals, start, end) => {
    const map = {}; // code -> {debit, credit}
    flatLines(journals, start, end).forEach((r) => {
      if (!map[r.account]) map[r.account] = { debit: 0, credit: 0 };
      map[r.account][r.side] += r.amount;
    });
    const rows = S.accounts.all()
      .filter((a) => map[a.code])
      .map((a) => {
        const m = map[a.code];
        const debitNormal = CAT[a.category].side === 'debit';
        const balance = debitNormal ? (m.debit - m.credit) : (m.credit - m.debit);
        return {
          code: a.code, name: a.name, category: a.category,
          debit: m.debit, credit: m.credit, balance, debitNormal,
        };
      });
    const sum = rows.reduce((s, r) => {
      s.debit += r.debit; s.credit += r.credit; return s;
    }, { debit: 0, credit: 0 });
    return { rows, sum, balanced: sum.debit === sum.credit };
  };

  /* ---- 損益計算書(PL) ・ 貸借対照表(BS) --------------------------------
   * PL: 収益 − 費用 = 当期純利益。
   * BS: 資産 = 負債 + 純資産 + 当期純利益。
   * ------------------------------------------------------------------- */
  const statements = (journals, start, end) => {
    // 損益振替（決算整理）仕訳は、PL表示からは除外して当期の実績を示し、
    // BS（繰越利益剰余金）には反映させる。これにより二重計上を避ける。
    const closingExists = journals.some((j) => j.source === 'closing' && U.inRange(j.date, start, end));
    const plTB = trialBalance(journals.filter((j) => j.source !== 'closing'), start, end);
    const bsTB = trialBalance(journals, start, end);
    const groupFrom = (tb, cat) => {
      const items = tb.rows.filter((r) => r.category === cat)
        .map((r) => ({ code: r.code, name: r.name, amount: r.balance }))
        .filter((r) => r.amount !== 0);
      const total = items.reduce((s, r) => s + r.amount, 0);
      return { items, total };
    };
    const revenue = groupFrom(plTB, 'revenue');
    const expense = groupFrom(plTB, 'expense');
    const netIncome = revenue.total - expense.total; // 当期純利益（実績）

    const asset = groupFrom(bsTB, 'asset');
    const liability = groupFrom(bsTB, 'liability');
    const equity = groupFrom(bsTB, 'equity');
    // 損益振替済みなら利益は繰越利益剰余金に含まれるため、当期純利益行は0にする
    const bsNetIncome = closingExists ? 0 : netIncome;
    const equityWithProfit = equity.total + bsNetIncome;

    return {
      pl: { revenue, expense, netIncome },
      bs: {
        asset, liability, equity, netIncome: bsNetIncome,
        liabilityAndEquity: liability.total + equityWithProfit,
        balanced: asset.total === (liability.total + equityWithProfit),
      },
    };
  };

  /* ---- 消費税集計 ------------------------------------------------------
   * 税込経理前提。仕訳明細の税区分から、課税売上・課税仕入を税率別に集計し、
   * 仮受消費税(売上に含まれる税) − 仮払消費税(仕入に含まれる税) = 差引納付税額。
   * ------------------------------------------------------------------- */
  const taxSummary = (journals, start, end) => {
    const rows = flatLines(journals, start, end);
    const salesByRate = {};    // rate -> {gross, tax}
    const purchaseByRate = {};
    rows.forEach((r) => {
      const t = TAX[r.tax];
      if (!t || t.kind === 'none') return;
      const bucket = t.kind === 'sales' ? salesByRate : purchaseByRate;
      if (!bucket[t.rate]) bucket[t.rate] = { gross: 0, tax: 0 };
      bucket[t.rate].gross += r.amount;
      bucket[t.rate].tax += U.taxIncludedPortion(r.amount, t.rate);
    });
    const sumTax = (b) => Object.values(b).reduce((s, x) => s + x.tax, 0);
    const sumGross = (b) => Object.values(b).reduce((s, x) => s + x.gross, 0);
    const salesTax = sumTax(salesByRate);       // 仮受消費税
    const purchaseTax = sumTax(purchaseByRate); // 仮払消費税（仕入税額控除）
    const payable = salesTax - purchaseTax;      // 差引納付（△は還付）
    return {
      salesByRate, purchaseByRate,
      salesGross: sumGross(salesByRate), salesNet: sumGross(salesByRate) - salesTax,
      purchaseGross: sumGross(purchaseByRate), purchaseNet: sumGross(purchaseByRate) - purchaseTax,
      salesTax, purchaseTax, payable,
    };
  };

  /* ---- 消費税：簡易課税・2割特例の納付額 -------------------------------
   * 簡易課税のみなし仕入率（事業区分別）。
   * ------------------------------------------------------------------- */
  const DEEMED_RATES = {
    1: { label: '第1種（卸売業）', rate: 90 },
    2: { label: '第2種（小売業）', rate: 80 },
    3: { label: '第3種（製造業等）', rate: 70 },
    4: { label: '第4種（その他）', rate: 60 },
    5: { label: '第5種（サービス業等）', rate: 50 },
    6: { label: '第6種（不動産業）', rate: 40 },
  };
  // summary = taxSummary() の結果, opts = {method, bizType}
  const taxPayable = (summary, method, bizType) => {
    const salesTax = summary.salesTax;
    if (method === 'special20') return Math.floor(salesTax * 0.2); // 2割特例：売上税額の2割
    if (method === 'simplified') {
      const rate = (DEEMED_RATES[bizType] || DEEMED_RATES[5]).rate;
      const deduct = Math.floor(salesTax * rate / 100); // みなし仕入税額
      return salesTax - deduct;
    }
    return summary.salesTax - summary.purchaseTax; // 原則課税
  };

  /* ---- 部門別損益 ------------------------------------------------------
   * 仕訳の dept ごとに、収益・費用・利益を集計する。
   * departments = [{id,name}]。dept 未設定は「未配賦」に集計。
   * ------------------------------------------------------------------- */
  const deptSummary = (journals, start, end, departments) => {
    const buckets = {}; // deptId -> {revenue, expense}
    const ensure = (id) => (buckets[id] = buckets[id] || { revenue: 0, expense: 0 });
    journals.forEach((j) => {
      if (j.source === 'closing') return; // 決算振替は除外
      if (!U.inRange(j.date, start, end)) return;
      const id = j.dept || '_none';
      const b = ensure(id);
      (j.lines || []).forEach((l) => {
        const cat = S.accounts.category(l.account);
        if (cat === 'revenue') b.revenue += (l.side === 'credit' ? 1 : -1) * l.amount;
        else if (cat === 'expense') b.expense += (l.side === 'debit' ? 1 : -1) * l.amount;
      });
    });
    const rows = (departments || []).map((d) => {
      const b = buckets[d.id] || { revenue: 0, expense: 0 };
      return { id: d.id, name: d.name, revenue: b.revenue, expense: b.expense, profit: b.revenue - b.expense };
    });
    const none = buckets['_none'];
    if (none && (none.revenue || none.expense)) rows.push({ id: '_none', name: '未配賦', revenue: none.revenue, expense: none.expense, profit: none.revenue - none.expense });
    const total = rows.reduce((s, r) => { s.revenue += r.revenue; s.expense += r.expense; s.profit += r.profit; return s; }, { revenue: 0, expense: 0, profit: 0 });
    return { rows, total };
  };

  /* ---- 消費税申告書の各欄（割戻し計算・概算） --------------------------
   * 国税率：10%→7.8%、8%(軽減)→6.24%。地方消費税＝国税×22/78。
   * summary = taxSummary() の結果。method/bizType で控除税額を切替。
   * ------------------------------------------------------------------- */
  const NATIONAL = { 10: 7.8, 8: 6.24 };
  const floorTo = (n, unit) => (n < 0 ? -Math.floor(-n / unit) * unit : Math.floor(n / unit) * unit);
  const taxReturnCalc = (summary, method, bizType) => {
    // 税抜課税標準額（税率別）
    const base = {};
    let baseSum = 0, natTax = 0;
    Object.keys(summary.salesByRate).forEach((r) => {
      const gross = summary.salesByRate[r].gross;
      const net = Math.floor(gross * 100 / (100 + Number(r)));
      base[r] = net; baseSum += net;
    });
    const baseRounded = floorTo(baseSum, 1000); // 課税標準額（千円未満切捨）
    Object.keys(base).forEach((r) => { natTax += Math.floor(base[r] * (NATIONAL[r] || 0) / 100); });
    // 控除対象仕入税額（国税）
    let deduction = 0;
    if (method === 'special20') deduction = Math.floor(natTax * 0.8);
    else if (method === 'simplified') { const rate = (DEEMED_RATES[bizType] || DEEMED_RATES[5]).rate; deduction = Math.floor(natTax * rate / 100); }
    else Object.keys(summary.purchaseByRate).forEach((r) => { deduction += Math.floor(summary.purchaseByRate[r].gross * (NATIONAL[r] || 0) / (100 + Number(r))); });
    const natPayable = floorTo(natTax - deduction, 100); // 差引税額（百円未満切捨）
    const localTax = floorTo(natPayable * 22 / 78, 100);  // 地方消費税
    return {
      taxableBase: baseRounded, nationalTax: natTax, deduction,
      nationalPayable: natPayable, localTax, totalPayable: natPayable + localTax,
      byRate: base,
    };
  };

  /* ---- ダッシュボード用サマリ ----------------------------------------- */
  const dashboard = (journals, start, end) => {
    const st = statements(journals, start, end);
    const cash = st.bs.asset.items
      .filter((i) => ['100', '110'].includes(i.code))
      .reduce((s, i) => s + i.amount, 0);
    return {
      revenue: st.pl.revenue.total,
      expense: st.pl.expense.total,
      netIncome: st.pl.netIncome,
      cash,
    };
  };

  /* ---- 減価償却（定額法・月割） ---------------------------------------
   * 直接法。年度ごとの償却額と帳簿価額の推移を返す。
   * 償却率 = (取得価額 − 残存価額) ÷ 耐用年数。供用初年度は月割。
   * ------------------------------------------------------------------- */
  const pad = (x) => String(x).padStart(2, '0');
  // method: 'straight'(定額) / 'declining'(200%定率) / 'lump3'(一括償却資産3年) / 'immediate'(少額即時)
  const depSchedule = (asset, fsMonth) => {
    const cost = Number(asset.acquireCost) || 0;
    const residual = Number(asset.residual) || 0;
    const life = Math.max(1, Number(asset.usefulLife) || 1);
    const method = asset.method || 'straight';
    const startDate = asset.startDate || asset.acquireDate;
    if (!startDate || cost <= 0) return [];
    const d = new Date(startDate + 'T00:00:00');
    let fyYear = d.getFullYear() - ((d.getMonth() + 1) < fsMonth ? 1 : 0);
    const fyMeta = (y, i) => {
      const start = `${y}-${pad(fsMonth)}-01`;
      const endD = new Date(y + 1, fsMonth - 1, 0);
      const end = `${endD.getFullYear()}-${pad(endD.getMonth() + 1)}-${pad(endD.getDate())}`;
      let months = 12;
      if (i === 0) {
        months = (endD.getFullYear() * 12 + endD.getMonth()) - (d.getFullYear() * 12 + d.getMonth()) + 1;
        months = Math.max(1, Math.min(12, months));
      }
      return { start, end, months };
    };
    const rows = [];
    const push = (i, amt, book) => {
      const m = fyMeta(fyYear, i);
      rows.push({ fyYear, start: m.start, end: m.end, months: m.months, amount: amt, bookBefore: book, bookAfter: book - amt });
      fyYear += 1;
    };

    // 少額即時償却：供用年度に全額
    if (method === 'immediate') { push(0, cost - residual, cost); return rows; }

    // 一括償却資産：取得価額を3年で均等（月割なし・残存0）
    if (method === 'lump3') {
      let book = cost; const per = Math.floor(cost / 3);
      for (let i = 0; i < 3; i++) { const amt = i === 2 ? book : per; push(i, amt, book); book -= amt; }
      return rows;
    }

    // 定率法（200%定率法・保証額を下回ったら残存年数で均等に切替）
    if (method === 'declining') {
      const rate = 2 / life; // 200%定率法の償却率
      let book = cost, i = 0, switched = false, switchAmt = 0;
      while (book > residual && i < life + 5) {
        const m = fyMeta(fyYear, i);
        let annual;
        if (switched) annual = switchAmt;
        else {
          const decl = Math.floor(book * rate);
          const remYears = Math.max(1, life - i);
          const straight = Math.ceil((book - residual) / remYears);
          if (decl <= straight) { switched = true; switchAmt = straight; annual = straight; }
          else annual = decl;
        }
        let amt = i === 0 ? Math.floor(annual * m.months / 12) : annual;
        if (book - amt < residual) amt = book - residual;
        if (amt <= 0) break;
        push(i, amt, book); book -= amt; i += 1;
      }
      return rows;
    }

    // 定額法
    const annual = Math.floor((cost - residual) / life);
    let book = cost, i = 0;
    while (book > residual && i < life + 3) {
      const m = fyMeta(fyYear, i);
      let amt = Math.floor(annual * m.months / 12);
      if (book - amt < residual) amt = book - residual;
      if (amt <= 0) break;
      push(i, amt, book); book -= amt; i += 1;
    }
    return rows;
  };
  // 指定した会計年度（fyStart='YYYY-MM-DD'）の償却予定額
  const depForFiscalYear = (asset, fsMonth, fyStart) =>
    depSchedule(asset, fsMonth).find((r) => r.start === fyStart) || null;

  /* ---- AI会計アシスタント用の財務診断 ---------------------------------
   * 財務指標・健全性チェック・改善/節税アドバイスを生成する（ルールベース）。
   * ------------------------------------------------------------------- */
  const advisor = (journals, invoices, assets, settings, start, end) => {
    const st = statements(journals, start, end);
    const tax = taxSummary(journals, start, end);
    const bal = (code) => { const i = st.bs.asset.items.concat(st.bs.liability.items, st.bs.equity.items).find((x) => x.code === code); return i ? i.amount : 0; };
    const exp = (code) => { const i = st.pl.expense.items.find((x) => x.code === code); return i ? i.amount : 0; };

    const cash = bal('100') + bal('110');
    const receivable = bal('120');
    const payable = bal('200') + bal('210');
    const curAssets = ['100', '110', '120', '130', '135', '150'].reduce((s, c) => s + bal(c), 0);
    const curLiab = ['200', '210', '220', '230', '250', '260'].reduce((s, c) => s + bal(c), 0);
    const revenue = st.pl.revenue.total, expense = st.pl.expense.total, net = st.pl.netIncome;

    const metrics = {
      revenue, expense, net,
      profitRate: revenue ? net / revenue : 0,
      cash, receivable, payable,
      currentRatio: curLiab ? curAssets / curLiab : null,
      taxableSalesNet: tax.salesNet,
    };

    const alerts = [];
    const add = (level, text) => alerts.push({ level, text });
    if (!st.bs.balanced) add('bad', '貸借対照表が一致していません。仕訳の借方・貸方をご確認ください。');
    if (bal('100') < 0) add('bad', '現金残高がマイナスです。記帳漏れ（入金の未計上）の可能性があります。');
    if (cash < 0) add('bad', '現預金残高がマイナスです。取引の記帳をご確認ください。');
    const reg = settings.invoiceRegNo || '';
    if (!reg || reg === 'T0000000000000') add('info', '適格請求書（インボイス）の登録番号が未設定です。設定画面で登録番号を入力すると請求書に表示されます。');
    if (tax.salesNet > 10000000) add('warn', `課税売上（税抜 ¥${U.yen(tax.salesNet)}）が1,000万円を超えています。翌々期の消費税の課税事業者判定にご注意ください。`);
    if (expense > 0 && exp('550') / expense > 0.1) add('info', `接待交際費が費用の${Math.round(exp('550') / expense * 100)}%を占めます。法人は交際費の損金算入に上限があるためご確認ください。`);
    if (revenue > 0 && receivable > revenue * 0.5) add('warn', '売掛金が売上に対して大きめです。長期滞留の債権がないかご確認ください。');
    const depPending = (assets || []).filter((a) => !a.disposed && depForFiscalYear(a, settings.fiscalStartMonth || 4, start) &&
      !journals.some((j) => j.source === 'depreciation' && j.refId === a.id && U.inRange(j.date, start, end)));
    if (depPending.length) add('warn', `当期の減価償却が未計上の固定資産が${depPending.length}件あります。`);
    if (metrics.currentRatio !== null && metrics.currentRatio < 1) add('warn', `流動比率が${Math.round(metrics.currentRatio * 100)}%（100%未満）です。短期の支払能力にご注意ください。`);

    const tips = [];
    if (net > 300000) {
      tips.push('利益が出ています。30万円未満の資産は「少額減価償却資産の特例」で当期に全額経費化できる場合があります。');
      tips.push('決算賞与（未払計上）や短期前払費用（1年以内の家賃・保険料など）で当期の損金を増やせる場合があります。');
    }
    if (tax.salesNet > 0 && tax.salesNet <= 50000000) tips.push('課税売上5,000万円以下なら、簡易課税や2割特例で消費税額が有利になる場合があります（消費税集計ページで比較できます）。');
    if (metrics.profitRate < 0.05 && revenue > 0) tips.push('利益率が低めです。経費の見直し（固定費・手数料）や単価の再検討をご検討ください。');
    tips.push('経費の計上漏れ（自宅兼事務所の家事按分、旅費・通信費など）がないかご確認ください。');
    tips.push('※ これは会計データに基づく一般的な参考情報です。最終的な判断は税理士等の専門家にご確認ください。');

    return { metrics, alerts, tips };
  };

  /* ---- 月次推移（売上・費用・利益） -----------------------------------
   * 会計年度内の各月について収益・費用・純利益を集計する（決算振替は除外）。
   * ------------------------------------------------------------------- */
  const monthlyTrend = (journals, fyStart, fsMonth) => {
    const startY = Number(fyStart.slice(0, 4)), startM = fsMonth;
    const months = [];
    for (let i = 0; i < 12; i++) {
      const m0 = (startM - 1 + i) % 12;
      const y = startY + Math.floor((startM - 1 + i) / 12);
      months.push({ ym: `${y}-${String(m0 + 1).padStart(2, '0')}`, revenue: 0, expense: 0, net: 0 });
    }
    const idx = {}; months.forEach((m, i) => (idx[m.ym] = i));
    journals.forEach((j) => {
      if (j.source === 'closing') return;
      const ym = (j.date || '').slice(0, 7);
      if (!(ym in idx)) return;
      const m = months[idx[ym]];
      (j.lines || []).forEach((l) => {
        const cat = S.accounts.category(l.account);
        if (cat === 'revenue') m.revenue += (l.side === 'credit' ? 1 : -1) * l.amount;
        else if (cat === 'expense') m.expense += (l.side === 'debit' ? 1 : -1) * l.amount;
      });
    });
    months.forEach((m) => { m.net = m.revenue - m.expense; });
    const total = months.reduce((s, m) => { s.revenue += m.revenue; s.expense += m.expense; s.net += m.net; return s; }, { revenue: 0, expense: 0, net: 0 });
    return { months, total };
  };

  /* ---- 取引先元帳（売掛金：請求＝借方、入金＝貸方） --------------------
   * 請求書（売上計上済み）と入金記録から、取引先ごとの残高推移を作る。
   * ------------------------------------------------------------------- */
  const partnerLedger = (invoices, journals, partnerName, calcInvoice, start, end) => {
    const rows = [];
    invoices.filter((iv) => iv.type === 'invoice' && iv.partnerName === partnerName && iv.posted)
      .forEach((iv) => rows.push({ date: iv.date, desc: `請求 ${iv.no ? '' : ''}${iv.partnerName}`, ref: iv, debit: calcInvoice(iv).total, credit: 0 }));
    // 入金仕訳（source=invoice で 売掛金(120) 貸方）
    journals.filter((j) => j.source === 'invoice' && (j.description || '').includes(partnerName) && (j.description || '').includes('入金'))
      .forEach((j) => { const amt = (j.lines || []).filter((l) => l.account === '120' && l.side === 'credit').reduce((s, l) => s + l.amount, 0); if (amt) rows.push({ date: j.date, desc: '入金', debit: 0, credit: amt }); });
    rows.sort((a, b) => (a.date || '').localeCompare(b.date || ''));
    let bal = 0;
    const inRange = rows.filter((r) => U.inRange(r.date, start, end));
    inRange.forEach((r) => { bal += r.debit - r.credit; r.balance = bal; });
    const totalDebit = inRange.reduce((s, r) => s + r.debit, 0);
    const totalCredit = inRange.reduce((s, r) => s + r.credit, 0);
    return { rows: inRange, closing: totalDebit - totalCredit, totalDebit, totalCredit };
  };

  /* ---- キャッシュフロー計算書（間接法・簡易） -------------------------
   * 当期純利益に非資金項目（減価償却）と運転資本増減を加減し、営業/投資/財務に
   * 区分。実際の現預金増減との差は「調整」に計上して必ず一致させる（概算）。
   * ------------------------------------------------------------------- */
  const cashflow = (journals, start, end) => {
    const CAT2 = A.accounts.CATEGORIES;
    const prev = (() => { if (!start) return null; const d = new Date(start + 'T00:00:00'); d.setDate(d.getDate() - 1); const p = (x) => String(x).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`; })();
    const balAt = (code, date) => {
      let b = 0;
      journals.forEach((j) => {
        if (date && j.date > date) return;
        (j.lines || []).forEach((l) => {
          if (l.account !== code) return;
          const cat = S.accounts.category(l.account); if (!cat) return;
          const dn = CAT2[cat].side === 'debit';
          b += (l.side === 'debit' ? 1 : -1) * (dn ? 1 : -1) * l.amount;
        });
      });
      return b;
    };
    const delta = (codes) => codes.reduce((s, c) => s + (balAt(c, end) - balAt(c, prev)), 0);
    const st = statements(journals, start, end);
    const net = st.pl.netIncome;
    const dep = (st.pl.expense.items.find((i) => i.code === '590') || { amount: 0 }).amount;

    const dAR = delta(['120', '130']);         // 売上債権・未収
    const dInv = delta(['150']);               // 棚卸資産
    const dAP = delta(['200', '210', '220', '230', '250']); // 仕入債務・未払
    const operating = net + dep - dAR - dInv + dAP;
    const investing = -(delta(['180', '185', '186', '135']) + dep); // 固定資産等の取得（減価償却を戻す）
    const financing = delta(['260', '270', '271', '300', '310']); // 借入金・資本の増減

    const cashOpen = balAt('100', prev) + balAt('110', prev);
    const cashClose = balAt('100', end) + balAt('110', end);
    const cashChange = cashClose - cashOpen;
    const adjust = cashChange - (operating + investing + financing);
    return { net, dep, dAR, dInv, dAP, operating, investing, financing, adjust, cashChange, cashOpen, cashClose };
  };

  return { flatLines, ledger, trialBalance, statements, taxSummary, dashboard, depSchedule, depForFiscalYear, DEEMED_RATES, taxPayable, deptSummary, taxReturnCalc, advisor, monthlyTrend, partnerLedger, cashflow };
})();
