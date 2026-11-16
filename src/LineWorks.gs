function notifyNewCase(caseId, customerName, score, rank, monthly) {
  const message = '【新規申込】\n\n' +
    '申込番号：' + caseId + '\n' +
    '顧客名：' + customerName + '\n\n' +
    '━━━━━━━━━━━━\n' +
    'AIスコア：' + score + '点\n' +
    '判定：' + rank + '判定\n' +
    '月々返済目安：' + parseInt(monthly).toLocaleString() + '円\n' +
    '━━━━━━━━━━━━\n\n' +
    'スプレッドシートで詳細を確認してください。';

  sendToLineWorks(message);
}

function notifyDelayAlert(caseId, customerName, delayDays, delayInterest) {
  const message = '【延滞アラート】\n\n' +
    '申込番号：' + caseId + '\n' +
    '顧客名：' + customerName + '\n\n' +
    '━━━━━━━━━━━━\n' +
    '延滞日数：' + delayDays + '日\n' +
    '延滞利息：' + parseInt(delayInterest).toLocaleString() + '円\n' +
    '━━━━━━━━━━━━\n\n' +
    '至急対応をお願いします。';

  sendToLineWorks(message);
}

function notifyMissingDocsHQ(caseId, customerName, days) {
  const message = '【書類未提出】\n\n' +
    '申込番号：' + caseId + '\n' +
    '顧客名：' + customerName + '\n' +
    '経過日数：' + days + '日\n\n' +
    '書類提出の催促をお願いします。';

  sendToLineWorks(message);
}

function notifyWeeklyReport() {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const loanSheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
    const data = loanSheet.getDataRange().getValues();
    const headers = data[0];
    const statusIdx = headers.indexOf('ステータス');

    let newCount = 0;
    let approvedCount = 0;
    let contractedCount = 0;
    let delayCount = 0;

    const today = new Date();
    const weekAgo = new Date(today - 7 * 24 * 60 * 60 * 1000);

    for (var i = 1; i < data.length; i++) {
      const row = data[i];
      if (!row[0]) continue;
      const status = row[statusIdx];
      const receivedDate = new Date(row[headers.indexOf('受付日時')]);

      if (receivedDate >= weekAgo) newCount++;
      if (status === '審査通過') approvedCount++;
      if (status === '成約') contractedCount++;
      if (status === '延滞') delayCount++;
    }

    const message = '【週次レポート】\n\n' +
      '期間：先週1週間\n\n' +
      '━━━━━━━━━━━━\n' +
      '新規申込：' + newCount + '件\n' +
      '審査通過：' + approvedCount + '件\n' +
      '成約：' + contractedCount + '件\n' +
      '延滞：' + delayCount + '件\n' +
      '━━━━━━━━━━━━\n\n' +
      '詳細はスプレッドシートをご確認ください。';

    sendToLineWorks(message);
    Logger.log('週次レポート送信完了');

  } catch(err) {
    Logger.log('notifyWeeklyReportエラー：' + err.toString());
  }
}

function sendToLineWorks(message) {
  try {
    const config = getConfig();

    if (!config.LINE_WORKS.BOT_ID || !config.LINE_WORKS.HQ_USER_ID) {
      Logger.log('LINE WORKS未設定：メッセージをログに記録');
      Logger.log('【LINE WORKS通知】\n' + message);
      return;
    }

    const token = getLineWorksToken();
    if (!token) {
      Logger.log('LINE WORKSトークン取得失敗');
      return;
    }

    const url = 'https://www.worksapis.com/v1.0/bots/' +
      config.LINE_WORKS.BOT_ID + '/users/' +
      config.LINE_WORKS.HQ_USER_ID + '/messages';

    const options = {
      method: 'post',
      contentType: 'application/json',
      headers: { 'Authorization': 'Bearer ' + token },
      payload: JSON.stringify({
        content: { type: 'text', text: message }
      }),
      muteHttpExceptions: true
    };

    const response = UrlFetchApp.fetch(url, options);
    Logger.log('LINE WORKS送信：' + response.getResponseCode());

  } catch(err) {
    Logger.log('sendToLineWorksエラー：' + err.toString());
  }
}

function getLineWorksToken() {
  try {
    const config = getConfig();
    const url = 'https://auth.worksmobile.com/oauth2/v2.0/token';
    const options = {
      method: 'post',
      contentType: 'application/x-www-form-urlencoded',
      payload: {
        grant_type: 'client_credentials',
        client_id: config.LINE_WORKS.CLIENT_ID,
        client_secret: config.LINE_WORKS.CLIENT_SECRET
      },
      muteHttpExceptions: true
    };
    const response = UrlFetchApp.fetch(url, options);
    const data = JSON.parse(response.getContentText());
    return data.access_token || null;
  } catch(err) {
    Logger.log('getLineWorksTokenエラー：' + err.toString());
    return null;
  }
}

function setupWeeklyReportTrigger() {
  ScriptApp.getProjectTriggers().forEach(function(trigger) {
    if (trigger.getHandlerFunction() === 'notifyWeeklyReport') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  ScriptApp.newTrigger('notifyWeeklyReport')
    .timeBased()
    .onWeekDay(ScriptApp.WeekDay.MONDAY)
    .atHour(9)
    .create();

  Logger.log('週次レポートトリガー設定完了（毎週月曜9時）');
}

function testLineWorks() {
  Logger.log('LineWorks テスト開始');
  sendToLineWorks('テスト通知です。\nCarLoan_Systemからの送信テストです。');
  Logger.log('LineWorks テスト完了');
}
