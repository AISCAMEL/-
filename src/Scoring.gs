/**
 * Scoring.gs
 * AIスコアリングエンジン
 */

function runScoring(rowNumber) {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
    const row = sheet.getRange(rowNumber, 1, 1, sheet.getLastColumn()).getValues()[0];

    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    const data = {};
    headers.forEach(function(header, i) {
      data[header] = row[i];
    });

    const score = calculateScore(data);
    const rank = getRank(score, config);
    const monthly = calcMonthlyPayment(
      data['希望借入額'],
      4.9,
      data['希望返済期間']
    );

    const scoreCol = headers.indexOf('AIスコア') + 1;
    const rankCol = headers.indexOf('見込みランク') + 1;
    const dateCol = headers.indexOf('スコア算出日') + 1;

    sheet.getRange(rowNumber, scoreCol).setValue(score);
    sheet.getRange(rowNumber, rankCol).setValue(rank);
    sheet.getRange(rowNumber, dateCol).setValue(new Date());

    Logger.log('スコア：' + score + ' 判定：' + rank + ' 月々：' + monthly);
    return { score: score, rank: rank, monthly: monthly };

  } catch(err) {
    Logger.log('runScoringエラー：' + err.toString());
    return null;
  }
}

function calculateScore(data) {
  let score = 0;

  // 雇用形態（25点）
  const employment = data['雇用形態'] || '';
  if (employment.includes('正社員') || employment.includes('公務員')) score += 25;
  else if (employment.includes('法人')) score += 20;
  else if (employment.includes('個人事業主')) score += 15;
  else if (employment.includes('契約') || employment.includes('派遣')) score += 10;
  else if (employment.includes('パート') || employment.includes('アルバイト')) score += 5;

  // 勤続年数（20点）
  const years = parseFloat(data['勤続年数']) || 0;
  if (years >= 5) score += 20;
  else if (years >= 3) score += 15;
  else if (years >= 1) score += 8;
  else score += 3;

  // 返済負担率（25点）
  const income = parseFloat(data['年収']) || 0;
  const loan = parseFloat(data['希望借入額']) || 0;
  const period = parseFloat(data['希望返済期間']) || 60;
  if (income > 0) {
    const monthly = calcMonthlyPayment(loan, 4.9, period);
    const ratio = (monthly * 12) / income * 100;
    if (ratio <= 25) score += 25;
    else if (ratio <= 35) score += 15;
    else if (ratio <= 45) score += 5;
  }

  // 他社借入（15点）
  const otherLoan = parseFloat(data['他社借入総額']) || 0;
  if (otherLoan === 0) score += 15;
  else if (otherLoan <= 500000) score += 10;
  else if (otherLoan <= 1000000) score += 5;

  // 直近審査数（±5点）
  const recentChecks = parseFloat(data['直近6ヶ月審査数']) || 0;
  if (recentChecks === 0) score += 5;
  else if (recentChecks >= 2) score -= 5;

  // 信用情報（15点）
  const credit = data['信用情報'] || '';
  const debt = data['債務整理歴'] || '';
  const bankrupt = data['自己破産歴'] || '';
  const overdue = data['滞納履歴'] || '';

  if (!credit && !debt && !bankrupt && !overdue) score += 15;
  else if (overdue && overdue.includes('現在')) score -= 10;
  else if (debt || bankrupt) score += 0;
  else score += 5;

  return Math.max(0, Math.min(100, score));
}

function getRank(score, config) {
  if (score >= config.SCORING.RANK_A) return 'A';
  if (score >= config.SCORING.RANK_B) return 'B';
  if (score >= config.SCORING.RANK_C) return 'C';
  return 'D';
}

function calcMonthlyPayment(principal, annualRate, months) {
  if (!principal || !months) return 0;
  principal = parseFloat(principal);
  months = parseFloat(months);
  if (annualRate === 0) return Math.round(principal / months);
  const r = annualRate / 100 / 12;
  const monthly = principal * r * Math.pow(1 + r, months) / (Math.pow(1 + r, months) - 1);
  return Math.round(monthly);
}

function testScoring() {
  const testData = {
    '雇用形態': '正社員',
    '勤続年数': 5,
    '年収': 4000000,
    '希望借入額': 2000000,
    '希望返済期間': 60,
    '他社借入総額': 0,
    '直近6ヶ月審査数': 0,
    '信用情報': '',
    '債務整理歴': '',
    '自己破産歴': '',
    '滞納履歴': ''
  };

  const score = calculateScore(testData);
  const config = getConfig();
  const rank = getRank(score, config);
  const monthly = calcMonthlyPayment(2000000, 4.9, 60);

  Logger.log('テストスコア：' + score + '点');
  Logger.log('判定：' + rank);
  Logger.log('月々返済：' + monthly + '円');
}
