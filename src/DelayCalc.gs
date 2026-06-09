function runDelayCalc() {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.REPAYMENT);
    const data = sheet.getDataRange().getValues();
    const headers = data[0];
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const nextPayDateIdx = headers.indexOf('次回支払日');
    const delayFlagIdx = headers.indexOf('延滞フラグ');
    const delayDaysIdx = headers.indexOf('延滞日数');
    const delayInterestIdx = headers.indexOf('延滞利息累計');
    const rateIdx = headers.indexOf('金利');
    const balanceIdx = headers.indexOf('残高');
    const caseIdIdx = headers.indexOf('申込番号');

    let delayCount = 0;

    for (var i = 1; i < data.length; i++) {
      const row = data[i];
      if (!row[caseIdIdx]) continue;

      const nextPayDate = new Date(row[nextPayDateIdx]);
      nextPayDate.setHours(0, 0, 0, 0);

      if (nextPayDate >= today) continue;

      const delayDays = Math.floor((today - nextPayDate) / (1000 * 60 * 60 * 24));
      const balance = parseFloat(row[balanceIdx]) || 0;
      const rate = parseFloat(row[rateIdx]) || config.DELAY.RATE_LOAN;
      const dailyRate = rate / 100 / 365;
      const delayInterest = Math.round(balance * dailyRate * delayDays);

      sheet.getRange(i + 1, delayFlagIdx + 1).setValue('あり');
      sheet.getRange(i + 1, delayDaysIdx + 1).setValue(delayDays);
      sheet.getRange(i + 1, delayInterestIdx + 1).setValue(delayInterest);

      Logger.log('延滞検知：' + row[caseIdIdx] + ' | ' + delayDays + '日 | 延滞利息：' + delayInterest + '円');
      delayCount++;
    }

    Logger.log('延滞件数：' + delayCount + '件');
    return delayCount;

  } catch(err) {
    Logger.log('runDelayCalcエラー：' + err.toString());
    return 0;
  }
}

function checkAlertDelays() {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.REPAYMENT);
    const data = sheet.getDataRange().getValues();
    const headers = data[0];

    const delayDaysIdx = headers.indexOf('延滞日数');
    const delayFlagIdx = headers.indexOf('延滞フラグ');
    const caseIdIdx = headers.indexOf('申込番号');
    const customerIdx = headers.indexOf('顧客名');
    const alertDays = config.DELAY.ALERT_DAYS;

    const alerts = [];

    for (var i = 1; i < data.length; i++) {
      const row = data[i];
      if (!row[caseIdIdx]) continue;
      if (row[delayFlagIdx] !== 'あり') continue;

      const delayDays = parseInt(row[delayDaysIdx]) || 0;
      if (delayDays >= alertDays) {
        alerts.push({
          caseId: row[caseIdIdx],
          customerName: row[customerIdx],
          delayDays: delayDays
        });
      }
    }

    Logger.log('アラート対象：' + alerts.length + '件');
    alerts.forEach(function(alert) {
      Logger.log('アラート：' + alert.caseId + ' | ' + alert.customerName + ' | ' + alert.delayDays + '日延滞');
    });

    return alerts;

  } catch(err) {
    Logger.log('checkAlertDelaysエラー：' + err.toString());
    return [];
  }
}

function setupDelayCalcTrigger() {
  ScriptApp.getProjectTriggers().forEach(function(trigger) {
    if (trigger.getHandlerFunction() === 'runDelayCalc') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  ScriptApp.newTrigger('runDelayCalc')
    .timeBased()
    .everyDays(1)
    .atHour(9)
    .create();

  Logger.log('DelayCalcトリガー設定完了（毎日9時）');
}

function testDelayCalc() {
  Logger.log('DelayCalc テスト開始');
  const count = runDelayCalc();
  Logger.log('延滞件数：' + count);
  const alerts = checkAlertDelays();
  Logger.log('アラート件数：' + alerts.length);
}
