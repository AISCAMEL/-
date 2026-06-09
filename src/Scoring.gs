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
    // フォームには借入元金・返済期間がないため、申込者が回答した
    // 「月額支払い可能額」を月々返済の目安としてそのまま使う
    const monthly = normalizeMonthly(data['月額支払い可能額']);

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
  const config = getConfig();
  let score = 0;

  // 雇用形態（25点）
  const employment = data['雇用形態'] || '';
  if (employment.includes('正社員') || employment.includes('公務員')) score += 25;
  else if (employment.includes('法人')) score += 20;
  else if (employment.includes('個人事業主')) score += 15;
  else if (employment.includes('契約') || employment.includes('派遣')) score += 10;
  else if (employment.includes('パート') || employment.includes('アルバイト')) score += 5;

  // 勤続年数（20点）：年だけでなく月数も合算して評価
  const tenureMonths = parseTenureMonths(data['勤続年数']);
  if (tenureMonths >= 60) score += 20;       // 5年以上
  else if (tenureMonths >= 36) score += 15;  // 3年以上
  else if (tenureMonths >= 12) score += 8;   // 1年以上
  else if (tenureMonths >= 6) score += 5;    // 6ヶ月以上（試用期間明けの目安）
  else score += 3;

  // 返済負担率（25点）
  // 借入元金・期間はフォームになく、年収・月額は単位がまちまちの
  // 自由入力のため正規化してから算出する
  const income = normalizeYen(data['年収']);
  const monthly = normalizeMonthly(data['月額支払い可能額']);
  if (income > 0 && monthly > 0) {
    const ratio = (monthly * 12) / income * 100;
    if (ratio <= 25) score += 25;
    else if (ratio <= 35) score += 15;
    else if (ratio <= 45) score += 5;
  }

  // 他社借入（15点）
  const otherLoan = normalizeYen(data['他社借入総額']);
  if (otherLoan === 0) score += 15;
  else if (otherLoan <= 500000) score += 10;
  else if (otherLoan <= 1000000) score += 5;

  // 他社審査履歴（±5点〜）：多重申込ほど他社否決の可能性が高くリスク
  const recentChecks = normalizeShinsaCount(data['直近6ヶ月審査数']);
  if (recentChecks === 0) score += 5;        // はじめて
  else if (recentChecks >= 3) score -= 10;   // 3社以上
  else if (recentChecks >= 2) score -= 5;    // 2社目

  // 信用情報（15点）
  const credit = data['信用情報'] || '';
  const debt = data['債務整理歴'] || '';
  const bankrupt = data['自己破産歴'] || '';
  const overdue = data['滞納履歴'] || '';

  if (!credit && !debt && !bankrupt && !overdue) {
    score += 15; // 事故情報なし
  } else if (overdue && overdue.includes('現在')) {
    score -= 10; // 現在進行形の滞納
  } else if (debt || bankrupt) {
    // 債務整理・自己破産歴あり：加点なし（据え置き）
  } else {
    score += 5; // 軽微な情報あり
  }

  // --- 加点：与信補完要因（C/D層でも前向きな材料があれば押し上げる）---
  // 頭金あり
  if (String(data['頭金有無'] || '').indexOf('あり') !== -1) {
    score += config.SCORING.BONUS_DOWNPAYMENT;
  }
  // 貯金額
  const savings = normalizeYen(data['貯金額']);
  if (savings >= 1000000) score += config.SCORING.BONUS_SAVINGS_HIGH;
  else if (savings >= 500000) score += config.SCORING.BONUS_SAVINGS_MID;
  // 住居状況（持家は安定、実家・親族宅は住居コスト低）
  const housing = String(data['住居状況'] || '');
  if (housing.indexOf('持家') !== -1) score += config.SCORING.BONUS_OWN_HOME;
  else if (housing.indexOf('実家') !== -1 || housing.indexOf('親族') !== -1) {
    score += config.SCORING.BONUS_FAMILY_HOME;
  }
  // 保証人あり
  if (String(data['保証人有無'] || '').indexOf('はい') !== -1) {
    score += config.SCORING.BONUS_GUARANTOR;
  }

  // 年齢（60歳以上は減点：完済までの就業・健康リスクを反映）
  // 生年月日から年齢を算出（フォームに項目があるため新規列は不要）
  const age = calcAge(data['生年月日']);
  if (age >= 70) score += config.SCORING.AGE_SENIOR_70;
  else if (age >= 65) score += config.SCORING.AGE_SENIOR_65;
  else if (age >= 60) score += config.SCORING.AGE_SENIOR_60;

  return Math.max(0, Math.min(100, score));
}

// 生年月日（「1981年09月17日」やDate型）から満年齢を算出。解釈不能なら0
function calcAge(birth) {
  let d;
  if (birth instanceof Date) {
    d = birth;
  } else {
    const s = toHalfWidth(birth);
    const m = s.match(/(\d{4})\D+(\d{1,2})\D+(\d{1,2})/);
    if (m) {
      d = new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
    } else {
      const y = s.match(/\d{4}/);
      if (!y) return 0;
      d = new Date(parseInt(y[0], 10), 0, 1);
    }
  }
  if (isNaN(d.getTime())) return 0;
  const t = new Date();
  let age = t.getFullYear() - d.getFullYear();
  const mo = t.getMonth() - d.getMonth();
  if (mo < 0 || (mo === 0 && t.getDate() < d.getDate())) age--;
  return age;
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

// 全角数字を半角へ
function toHalfWidth(str) {
  return String(str == null ? '' : str).replace(/[０-９]/g, function(c) {
    return String.fromCharCode(c.charCodeAt(0) - 0xFEE0);
  });
}

// 「450万円」「4900000」「416」「200万円～250万円未満」「非公開」「なし」などを
// 円単位の数値へ正規化する。先頭の数値を採用し、「万」表記や万単位省略を補正。
function normalizeYen(value) {
  const s = toHalfWidth(value);
  if (!s || s.indexOf('非公開') !== -1) return 0;
  const m = s.match(/[0-9]+(?:\.[0-9]+)?/);
  if (!m) return 0;                       // 「なし」など数字なし → 0
  const num = parseFloat(m[0]);
  if (isNaN(num) || num === 0) return 0;
  if (s.indexOf('万') !== -1) return Math.round(num * 10000);
  if (num < 10000) return Math.round(num * 10000); // 「416」=416万円とみなす
  return Math.round(num);
}

// 「10,000 〜 20,000」「〜 10,000」「0」などの月額レンジを代表値（上限）へ
function normalizeMonthly(value) {
  const s = toHalfWidth(value).replace(/,/g, '');
  const nums = s.match(/[0-9]+/g);
  if (!nums) return 0;
  return Math.max.apply(null, nums.map(Number));
}

// 「9年11ヶ月」「0年6ヶ月」などの勤続表記を総月数へ（年×12＋月）
function parseTenureMonths(value) {
  const s = toHalfWidth(value);
  const y = s.match(/(\d+)\s*年/);
  const m = s.match(/(\d+)\s*[ヶケヵカか]?月/);
  const years = y ? parseInt(y[1], 10) : 0;
  const months = m ? parseInt(m[1], 10) : 0;
  return years * 12 + months;
}

// 「はじめて」「２社目」「５社目」「それ以上」などを審査社数の数値へ
function normalizeShinsaCount(value) {
  const s = toHalfWidth(value);
  if (s.indexOf('はじめて') !== -1) return 0;
  if (s.indexOf('それ以上') !== -1) return 5;
  const m = s.match(/[0-9]+/);
  return m ? parseInt(m[0], 10) : 0;
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
