function runReminder() {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const results = {
      noResponse: [],
      waitingResult: [],
      missingDocs: [],
      paymentDue: []
    };

    // ローン案件管理チェック
    const loanSheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
    const loanData = loanSheet.getDataRange().getValues();
    const loanHeaders = loanData[0];

    const statusIdx = loanHeaders.indexOf('ステータス');
    const receivedDateIdx = loanHeaders.indexOf('受付日時');
    const caseIdIdx = loanHeaders.indexOf('申込番号');
    const customerIdx = loanHeaders.indexOf('顧客名');
    const lineIdIdx = loanHeaders.indexOf('LINE_ID');

    for (var i = 1; i < loanData.length; i++) {
      const row = loanData[i];
      if (!row[caseIdIdx]) continue;

      const status = row[statusIdx];
      const receivedDate = new Date(row[receivedDateIdx]);
      receivedDate.setHours(0, 0, 0, 0);
      const daysSinceReceived = Math.floor((today - receivedDate) / (1000 * 60 * 60 * 24));

      // 書類未提出チェック（3日以上経過）
      if (status === '書類確認中' && daysSinceReceived >= 3) {
        results.missingDocs.push({
          caseId: row[caseIdIdx],
          customerName: row[customerIdx],
          lineId: row[lineIdIdx],
          days: daysSinceReceived
        });
      }

      // 審査結果待ちチェック（5日以上経過）
      if (status === '審査中' && daysSinceReceived >= 5) {
        results.waitingResult.push({
          caseId: row[caseIdIdx],
          customerName: row[customerIdx],
          lineId: row[lineIdIdx],
          days: daysSinceReceived
        });
      }
    }

    // 返済管理チェック
    const repaySheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.REPAYMENT);
    const repayData = repaySheet.getDataRange().getValues();
    const repayHeaders = repayData[0];

    const nextPayIdx = repayHeaders.indexOf('次回支払日');
    const repayCaseIdIdx = repayHeaders.indexOf('申込番号');
    const repayCustomerIdx = repayHeaders.indexOf('顧客名');
    const repayLineIdIdx = repayHeaders.indexOf('LINE_ID');

    for (var j = 1; j < repayData.length; j++) {
      const row = repayData[j];
      if (!row[repayCaseIdIdx]) continue;

      const nextPayDate = new Date(row[nextPayIdx]);
      nextPayDate.setHours(0, 0, 0, 0);
      const daysUntilPay = Math.floor((nextPayDate - today) / (1000 * 60 * 60 * 24));

      // 支払い3日前リマインド
      if (daysUntilPay === 3) {
        results.paymentDue.push({
          caseId: row[repayCaseIdIdx],
          customerName: row[repayCustomerIdx],
          lineId: row[repayLineIdIdx],
          daysUntilPay: daysUntilPay
        });
      }
    }

    Logger.log('書類未提出：' + results.missingDocs.length + '件');
    Logger.log('審査結果待ち：' + results.waitingResult.length + '件');
    Logger.log('支払い期日3日前：' + results.paymentDue.length + '件');

    return results;

  } catch(err) {
    Logger.log('runReminderエラー：' + err.toString());
    return null;
  }
}

function setupReminderTrigger() {
  ScriptApp.getProjectTriggers().forEach(function(trigger) {
    if (trigger.getHandlerFunction() === 'runReminder') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  ScriptApp.newTrigger('runReminder')
    .timeBased()
    .everyDays(1)
    .atHour(10)
    .create();

  Logger.log('Reminderトリガー設定完了（毎日10時）');
}

function testReminder() {
  Logger.log('Reminder テスト開始');
  const results = runReminder();
  if (results) {
    Logger.log('書類未提出：' + results.missingDocs.length + '件');
    Logger.log('審査結果待ち：' + results.waitingResult.length + '件');
    Logger.log('支払い期日3日前：' + results.paymentDue.length + '件');
  }
}
