/* =========================================================================
 * tests/run.js ― 会計ロジックの回帰テスト（依存ゼロ・Nodeで実行）
 *   実行:  node accounting/tests/run.js   （または accounting/ で node tests/run.js）
 * 主要な計算（試算表・BS/PL・消費税・減価償却・キャッシュフロー・給与）を
 * 期待値と照合する。CI や変更後の動作確認に使う。
 * ========================================================================= */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

// ブラウザ用スクリプトを読み込むための最小環境
global.window = global;
const ROOT = path.join(__dirname, '..', 'assets', 'js');
const load = (rel) => vm.runInThisContext(fs.readFileSync(path.join(ROOT, rel), 'utf8'), { filename: rel });
load('util.js');
load('accounts.js');
const accs = global.A.accounts.DEFAULT_ACCOUNTS;
global.A.store = {
  accounts: {
    all: () => accs,
    byCode: (c) => accs.find((a) => a.code === c) || null,
    name: (c) => { const a = accs.find((x) => x.code === c); return a ? a.name : '?'; },
    category: (c) => { const a = accs.find((x) => x.code === c); return a ? a.category : null; },
  },
};
load('reports.js');
load('payrolltax.js');
const { util: U, reports: R, payrolltax: PT, accounts: AC } = global.A;

let pass = 0, fail = 0;
const eq = (name, got, exp) => {
  const ok = JSON.stringify(got) === JSON.stringify(exp);
  if (ok) { pass += 1; } else { fail += 1; console.log(`  ✗ ${name}\n      期待: ${JSON.stringify(exp)}\n      実際: ${JSON.stringify(got)}`); }
};
const near = (name, got, exp, tol) => { const ok = Math.abs(got - exp) <= (tol || 0); if (ok) pass += 1; else { fail += 1; console.log(`  ✗ ${name} 期待≈${exp} 実際${got}`); } };

/* ---- ユーティリティ ---- */
eq('yen', U.yen(1234567), '1,234,567');
eq('yenSigned(neg)', U.yenSigned(-500), '△500');
eq('内税10%(11000)', U.taxIncludedPortion(11000, 10), 1000);
eq('内税8%(10800)', U.taxIncludedPortion(10800, 8), 800);
eq('税額(10000,10%)', U.taxFromNet(10000, 10), 1000);

/* ---- 試算表・BS/PL・消費税 ---- */
const J = [
  { id: '1', no: 1, date: '2026-05-01', source: 'manual', lines: [{ side: 'debit', account: '110', tax: 'out', amount: 110000 }, { side: 'credit', account: '400', tax: 'sales10', amount: 110000 }] },
  { id: '2', no: 2, date: '2026-05-03', source: 'manual', lines: [{ side: 'debit', account: '500', tax: 'purchase10', amount: 55000 }, { side: 'credit', account: '110', tax: 'out', amount: 55000 }] },
  { id: '3', no: 3, date: '2026-05-10', source: 'expense', lines: [{ side: 'debit', account: '570', tax: 'purchase10', amount: 33000 }, { side: 'credit', account: '110', tax: 'out', amount: 33000 }] },
];
const tb = R.trialBalance(J, null, null);
eq('試算表 貸借一致', tb.balanced, true);
const st = R.statements(J, null, null);
eq('PL 純利益(110000-88000)', st.pl.netIncome, 22000);
eq('BS 貸借一致', st.bs.balanced, true);
eq('普通預金残高', (st.bs.asset.items.find((i) => i.code === '110') || {}).amount, 22000);
const tax = R.taxSummary(J, null, null);
eq('仮受消費税', tax.salesTax, 10000);
eq('仮払消費税', tax.purchaseTax, 8000);
eq('納付税額', tax.payable, 2000);

/* ---- 消費税の課税方式 ---- */
const t2 = { salesTax: 10000, purchaseTax: 8000 };
eq('原則課税', R.taxPayable(t2, 'general', 5), 2000);
eq('2割特例', R.taxPayable(t2, 'special20', 5), 2000);
eq('簡易(第5種50%)', R.taxPayable(t2, 'simplified', 5), 5000);

/* ---- 消費税申告書（割戻し） ---- */
const Jsale = [
  { date: '2026-05-01', lines: [{ side: 'debit', account: '110', tax: 'out', amount: 1100000 }, { side: 'credit', account: '400', tax: 'sales10', amount: 1100000 }] },
  { date: '2026-05-05', lines: [{ side: 'debit', account: '500', tax: 'purchase10', amount: 330000 }, { side: 'credit', account: '110', tax: 'out', amount: 330000 }] },
];
const ret = R.taxReturnCalc(R.taxSummary(Jsale, null, null), 'general', 5);
eq('課税標準額', ret.taxableBase, 1000000);
eq('消費税額(国税)', ret.nationalTax, 78000);
eq('差引税額(国税)', ret.nationalPayable, 54600);
eq('納付税額合計', ret.totalPayable, 70000);

/* ---- 減価償却 ---- */
const depTotal = (a) => R.depSchedule(a, 4).reduce((s, r) => s + r.amount, 0);
eq('定額法 初年度', R.depSchedule({ acquireCost: 1200000, residual: 0, usefulLife: 5, method: 'straight', acquireDate: '2026-04-01', startDate: '2026-04-01' }, 4)[0].amount, 240000);
eq('定率法200% 初年度', R.depSchedule({ acquireCost: 1200000, residual: 0, usefulLife: 5, method: 'declining', acquireDate: '2026-04-01', startDate: '2026-04-01' }, 4)[0].amount, 480000);
eq('定率法 合計=取得価額', depTotal({ acquireCost: 1200000, residual: 0, usefulLife: 5, method: 'declining', acquireDate: '2026-04-01', startDate: '2026-04-01' }), 1200000);
eq('一括償却 3年', depTotal({ acquireCost: 180000, method: 'lump3', acquireDate: '2026-04-01', startDate: '2026-04-01' }), 180000);
eq('少額即時', R.depSchedule({ acquireCost: 250000, method: 'immediate', acquireDate: '2026-04-01', startDate: '2026-04-01' }, 4)[0].amount, 250000);

/* ---- キャッシュフロー（reconcile） ---- */
const Jcf = [
  { date: '2026-05-01', source: 'manual', lines: [{ side: 'debit', account: '110', amount: 550000 }, { side: 'credit', account: '400', amount: 550000 }] },
  { date: '2026-05-02', source: 'manual', lines: [{ side: 'debit', account: '570', amount: 110000 }, { side: 'credit', account: '110', amount: 110000 }] },
  { date: '2026-06-01', source: 'manual', lines: [{ side: 'debit', account: '120', amount: 100000 }, { side: 'credit', account: '400', amount: 100000 }] },
  { date: '2027-03-31', source: 'depreciation', lines: [{ side: 'debit', account: '590', amount: 240000 }, { side: 'credit', account: '180', amount: 240000 }] },
];
const cf = R.cashflow(Jcf, '2026-04-01', '2027-03-31');
eq('CF 営業', cf.operating, 440000);
eq('CF 現預金増減と一致', cf.operating + cf.investing + cf.financing + cf.adjust, cf.cashChange);

/* ---- 月次推移 ---- */
const mt = R.monthlyTrend(Jcf, '2026-04-01', 4);
eq('月次 売上合計', mt.total.revenue, 650000);
eq('月次 5月売上', mt.months.find((m) => m.ym === '2026-05').revenue, 550000);

/* ---- 給与：源泉・年末調整 ---- */
near('月次源泉(255690,扶養1)', PT.monthlyWithholding(255690, 1), 5100, 500);
const ye = PT.yearEnd({ salaryIncome: 4000000, socialInsurance: 600000, dependents: 0, hasSpouse: false, withheldTotal: 100000 });
eq('年末調整 課税所得', ye.taxable, 1680000);
eq('年末調整 年税額', ye.yearTax, 85700);
eq('年末調整 差引(還付)', ye.diff, 14300);
eq('生保控除(一般8万)', PT.lifeInsuranceDeduction(80000, 0, 0), 40000);
eq('生保控除 合算上限', PT.lifeInsuranceDeduction(100000, 100000, 100000), 120000);
eq('地震保険控除(5万)', PT.earthquakeInsuranceDeduction(50000, 0), 50000);

/* ---- 結果 ---- */
console.log(`\n合計: ${pass + fail} 件 / 成功 ${pass} / 失敗 ${fail}`);
if (fail) { console.log('❌ テスト失敗'); process.exit(1); }
console.log('✅ すべて成功');
