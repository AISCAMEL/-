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
    const tb = trialBalance(journals, start, end);
    const group = (cat) => {
      const items = tb.rows.filter((r) => r.category === cat)
        .map((r) => ({ code: r.code, name: r.name, amount: r.balance }))
        .filter((r) => r.amount !== 0);
      const total = items.reduce((s, r) => s + r.amount, 0);
      return { items, total };
    };
    const revenue = group('revenue');
    const expense = group('expense');
    const netIncome = revenue.total - expense.total; // 当期純利益

    const asset = group('asset');
    const liability = group('liability');
    const equity = group('equity');
    const equityWithProfit = equity.total + netIncome;

    return {
      pl: { revenue, expense, netIncome },
      bs: {
        asset, liability, equity, netIncome,
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

  return { flatLines, ledger, trialBalance, statements, taxSummary, dashboard };
})();
