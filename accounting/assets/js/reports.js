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

  return { flatLines, ledger, trialBalance, statements, taxSummary, dashboard, depSchedule, depForFiscalYear, DEEMED_RATES, taxPayable, deptSummary, taxReturnCalc };
})();
