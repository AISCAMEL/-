/* =========================================================================
 * payrolltax.js ― 源泉所得税（電算機計算の特例）・年末調整の計算
 *
 * 月次の源泉徴収は「電子計算機等を使用して源泉徴収税額を計算する方法（特例）」
 * の甲欄に基づく。年末調整は年間の給与所得・所得控除から年税額を求め、
 * 源泉徴収済み合計との差額（過不足）を算出する。
 * ※ 税額表は令和2年分以降の区分に基づく概算。最新の改正・乙欄は要確認。
 * ========================================================================= */
window.A = window.A || {};

A.payrolltax = (function () {
  'use strict';

  // 給与所得控除（年額）
  const salaryDeductionAnnual = (income) => {
    if (income <= 1625000) return 550000;
    if (income <= 1800000) return Math.floor(income * 0.4) - 100000;
    if (income <= 3600000) return Math.floor(income * 0.3) + 80000;
    if (income <= 6600000) return Math.floor(income * 0.2) + 440000;
    if (income <= 8500000) return Math.floor(income * 0.1) + 1100000;
    return 1950000;
  };

  /* ---- 月次源泉（電算特例・甲欄） --------------------------------------
   * B = その月の社会保険料等控除後の給与等の金額
   * dependents = 扶養親族等の数
   * ------------------------------------------------------------------- */
  const monthlyWithholding = (B, dependents) => {
    B = Math.max(0, Math.floor(B));
    const dep = Math.max(0, Number(dependents) || 0);
    // 給与所得控除（月額）＝年額換算を12で割る
    const salDedMonthly = Math.floor(salaryDeductionAnnual(B * 12) / 12);
    // 基礎控除（月額 40,000）＋扶養控除等（1人 31,667）
    const basic = 40000;
    const depDed = dep * 31667;
    // 課税給与所得金額（月額・1,000円未満切捨）
    let A = B - salDedMonthly - basic - depDed;
    A = Math.max(0, Math.floor(A / 1000) * 1000);
    // 別表第二（月額・復興特別所得税込み）
    let tax;
    if (A <= 162500) tax = A * 0.05105;
    else if (A <= 275000) tax = A * 0.1021 - 8296;
    else if (A <= 579166) tax = A * 0.2042 - 36374;
    else if (A <= 750000) tax = A * 0.23483 - 54113;
    else if (A <= 1500000) tax = A * 0.33693 - 130688;
    else if (A <= 3333333) tax = A * 0.4084 - 237893;
    else tax = A * 0.45945 - 408061;
    return Math.max(0, Math.floor(tax / 10) * 10); // 10円未満切捨
  };

  /* ---- 年末調整（年税額・過不足） --------------------------------------
   * data = {
   *   salaryIncome(年間課税支給＝総支給−非課税通勤費),
   *   socialInsurance(年間社会保険料・本人負担),
   *   dependents(一般扶養親族等の数), hasSpouse(配偶者控除), withheldTotal(源泉徴収済み合計)
   * }
   * ------------------------------------------------------------------- */
  const NATIONAL_BRACKETS = [
    [1950000, 0.05, 0], [3300000, 0.10, 97500], [6950000, 0.20, 427500],
    [9000000, 0.23, 636000], [18000000, 0.33, 1536000], [40000000, 0.40, 2796000],
    [Infinity, 0.45, 4796000],
  ];
  const yearEnd = (data) => {
    const income = Math.max(0, Math.floor(data.salaryIncome) || 0);
    const salaryDeduction = salaryDeductionAnnual(income);
    const employmentIncome = income - salaryDeduction; // 給与所得
    const basic = 480000; // 基礎控除（合計所得2,400万円以下）
    const spouse = data.hasSpouse ? 380000 : 0;
    const dependentsDed = (Math.max(0, Number(data.dependents) || 0)) * 380000;
    const social = Math.max(0, Math.floor(data.socialInsurance) || 0);
    // 各種控除（金額は入力値を採用）
    const num = (v) => Math.max(0, Math.floor(Number(v) || 0));
    const lifeIns = num(data.lifeInsurance);          // 生命保険料控除
    const quakeIns = num(data.earthquakeInsurance);   // 地震保険料控除
    const smallMutual = num(data.smallMutual);        // 小規模企業共済等掛金控除（iDeco等）
    const otherDed = num(data.otherDeduction);        // その他所得控除
    const housingCredit = num(data.housingCredit);    // 住宅借入金等特別控除（税額控除）
    const totalDeduction = basic + spouse + dependentsDed + social + lifeIns + quakeIns + smallMutual + otherDed;
    let taxable = employmentIncome - totalDeduction;
    taxable = Math.max(0, Math.floor(taxable / 1000) * 1000); // 課税所得（1,000円未満切捨）
    const br = NATIONAL_BRACKETS.find((b) => taxable <= b[0]);
    const baseTax = Math.max(0, Math.floor(taxable * br[1] - br[2]));
    const afterCredit = Math.max(0, baseTax - housingCredit); // 住宅ローン控除は税額控除
    const yearTax = Math.floor(afterCredit * 1.021 / 100) * 100; // 復興特別所得税込み・年税額（100円未満切捨）
    const withheld = Math.max(0, Math.floor(data.withheldTotal) || 0);
    const diff = withheld - yearTax; // プラス＝還付、マイナス＝追加徴収
    return { salaryDeduction, employmentIncome, totalDeduction, taxable, baseTax, housingCredit, afterCredit, yearTax, withheld, diff };
  };

  return { salaryDeductionAnnual, monthlyWithholding, yearEnd };
})();
