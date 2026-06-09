function onProLineFormSubmit() {
  try {
    const config = getConfig();
    const formSS = SpreadsheetApp.openById(config.SPREADSHEET.PROLINE_FORM_ID);
    const formSheet = formSS.getSheetByName(config.SPREADSHEET.PROLINE_FORM_SHEET);
    const loanSS = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const loanSheet = loanSS.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
    const formData = formSheet.getDataRange().getValues();
    const loanData = loanSheet.getDataRange().getValues();
    const processedIds = [];
    for (var i = 1; i < loanData.length; i++) {
      if (loanData[i][0]) processedIds.push(loanData[i][0]);
    }
    for (var j = formData.length - 1; j >= 1; j--) {
      const row = formData[j];
      const uid = row[1];
      const caseId = 'LOAN-' + uid;
      if (processedIds.indexOf(caseId) !== -1) continue;
      const caseData = mapFormToLoan(row, caseId);
      loanSheet.appendRow(caseData);
      const lastRow = loanSheet.getLastRow();
      const scoreResult = runScoring(lastRow);
      //const lineId = getLineIdByUid(uid, loanSS);
// if (lineId) {
//   notifyCustomerReceived(lineId, scoreResult);
// }
      Logger.log('新規案件登録：' + caseId);
      break;
    }
  } catch(err) {
    Logger.log('onProLineFormSubmitエラー：' + err.toString());
  }
}

function mapFormToLoan(row, caseId) {
  const now = new Date();
  return [
    caseId, row[0], '書類確認中', '個人',
    row[10], row[14], row[22], row[1],
    row[16], row[17] + row[18] + row[19],
    row[35], row[43] + '年' + row[44] + 'ヶ月',
    row[45], '', '', row[32], row[31], row[8],
    '', '', '', row[5], row[47],
    '', '', '',
    '', '', '', '', '',
    '', '', '', '', '', '', '', '',
    '', '', '', '', '', '', '', '',
    '', '', '', '', '', '', '', '',
    '', '', '', '', '', '', '', '',
    '', '', '', '', '', '', '', '',
    '', '', '', '', '', '', '', '',
    '', '', '', '', '', '', '', '',
    '', '', '', '', now
  ];
}

function getLineIdByUid(uid, ss) {
  try {
    const sheet = ss.getSheetByName('顧客マスタ');
    const data = sheet.getDataRange().getValues();
    for (var i = 1; i < data.length; i++) {
      if (data[i][1] === uid) return data[i][1];
    }
    return null;
  } catch(err) {
    return null;
  }
}

function notifyCustomerReceived(lineId, scoreResult) {
  try {
    var message = '申込を受け付けました。\n\n審査を開始いたします。\n結果は最短即日〜翌営業日に\nこちらのLINEにてご連絡いたします。\n\nしばらくお待ちください。';
    if (scoreResult && (scoreResult.rank === 'A' || scoreResult.rank === 'B')) {
      message += '\n\n仮審査スコア：' + scoreResult.score + '点\n判定：' + scoreResult.rank + '判定\n月々返済目安：' + scoreResult.monthly.toLocaleString() + '円';
    }
    pushToLine(lineId, message);
  } catch(err) {
    Logger.log('notifyCustomerReceivedエラー：' + err.toString());
  }
}

function pushToLine(lineId, message) {
  try {
    const config = getConfig();
    const options = {
      method: 'post',
      contentType: 'application/json',
      headers: { 'Authorization': 'Bearer ' + config.LINE.CHANNEL_TOKEN },
      payload: JSON.stringify({
        to: lineId,
        messages: [{ type: 'text', text: message }]
      }),
      muteHttpExceptions: true
    };
    const response = UrlFetchApp.fetch('https://api.line.me/v2/bot/message/push', options);
    Logger.log('プッシュ通知：' + response.getResponseCode());
  } catch(err) {
    Logger.log('pushToLineエラー：' + err.toString());
  }
}

function generateCaseId() {
  const config = getConfig();
  const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
  const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
  const lastRow = sheet.getLastRow();
  const num = String(lastRow).padStart(4, '0');
  return 'LOAN-' + num;
}

function testFormHandler() {
  Logger.log('FormHandler テスト開始');
  onProLineFormSubmit();
  Logger.log('FormHandler テスト完了');
}
