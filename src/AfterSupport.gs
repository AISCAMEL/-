function runAfterSupport() {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.AFTER_SUPPORT);
    const data = sheet.getDataRange().getValues();
    const headers = data[0];
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const results = {
      shaken: [],
      insurance: [],
      completion: [],
      switchover: []
    };

    for (var i = 1; i < data.length; i++) {
      const row = data[i];
      const supportId = row[headers.indexOf('サポートID')];
      if (!supportId) continue;

      const customerName = row[headers.indexOf('顧客名')];
      const lineId = row[headers.indexOf('LINE_ID')];
      const caseId = row[headers.indexOf('申込番号')];

      // 車検満了日チェック
      const shakenDate = new Date(row[headers.indexOf('車検満了日')]);
      const shakenNotified = row[headers.indexOf('車検通知済')];
      if (shakenDate && !shakenNotified) {
        const daysLeft = Math.floor((shakenDate - today) / (1000 * 60 * 60 * 24));
        if (daysLeft <= config.AFTER_SUPPORT.SHAKEN_DAYS && daysLeft >= 0) {
          results.shaken.push({ supportId, customerName, lineId, caseId, daysLeft });
          sheet.getRange(i + 1, headers.indexOf('車検通知済') + 1).setValue('済');
        }
      }

      // 保険満了日チェック
      const insuranceDate = new Date(row[headers.indexOf('保険満了日')]);
      const insuranceNotified = row[headers.indexOf('保険通知済')];
      if (insuranceDate && !insuranceNotified) {
        const daysLeft = Math.floor((insuranceDate - today) / (1000 * 60 * 60 * 24));
        if (daysLeft <= config.AFTER_SUPPORT.INSURANCE_DAYS && daysLeft >= 0) {
          results.insurance.push({ supportId, customerName, lineId, caseId, daysLeft });
          sheet.getRange(i + 1, headers.indexOf('保険通知済') + 1).setValue('済');
        }
      }

      // 完済予定日チェック
      const completionDate = new Date(row[headers.indexOf('完済予定日')]);
      const completionNotified = row[headers.indexOf('完済前通知済')];
      if (completionDate && !completionNotified) {
        const daysLeft = Math.floor((completionDate - today) / (1000 * 60 * 60 * 24));
        if (daysLeft <= config.AFTER_SUPPORT.COMPLETION_DAYS && daysLeft >= 0) {
          results.completion.push({ supportId, customerName, lineId, caseId, daysLeft });
          sheet.getRange(i + 1, headers.indexOf('完済前通知済') + 1).setValue('済');
        }
      }

      // 乗換え検討日チェック
      const switchDate = new Date(row[headers.indexOf('乗換え検討日')]);
      const switchNotified = row[headers.indexOf('乗換え通知済')];
      if (switchDate && !switchNotified) {
        const daysLeft = Math.floor((switchDate - today) / (1000 * 60 * 60 * 24));
        if (daysLeft <= config.AFTER_SUPPORT.SWITCH_NOTIFY_DAYS && daysLeft >= 0) {
          results.switchover.push({ supportId, customerName, lineId, caseId, daysLeft });
          sheet.getRange(i + 1, headers.indexOf('乗換え通知済') + 1).setValue('済');
        }
      }
    }

    Logger.log('車検通知対象：' + results.shaken.length + '件');
    Logger.log('保険通知対象：' + results.insurance.length + '件');
    Logger.log('完済通知対象：' + results.completion.length + '件');
    Logger.log('乗換え通知対象：' + results.switchover.length + '件');

    return results;

  } catch(err) {
    Logger.log('runAfterSupportエラー：' + err.toString());
    return null;
  }
}

function registerAfterSupport(caseId, lineId, customerName, shakenDate, insuranceDate, completionDate) {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.AFTER_SUPPORT);
    const now = new Date();

    const today = new Date();
    const switchDate = new Date(today.setFullYear(today.getFullYear() + 1));

    const supportId = 'SUP-' + Utilities.formatDate(now, 'Asia/Tokyo', 'yyyyMMddHHmmss');

    sheet.appendRow([
      supportId, '', customerName, lineId,
      caseId, shakenDate, insuranceDate,
      completionDate, switchDate,
      '', '', '', '',
      '', now
    ]);

    Logger.log('アフターサポート登録：' + supportId);
    return supportId;

  } catch(err) {
    Logger.log('registerAfterSupportエラー：' + err.toString());
    return null;
  }
}

function setupAfterSupportTrigger() {
  ScriptApp.getProjectTriggers().forEach(function(trigger) {
    if (trigger.getHandlerFunction() === 'runAfterSupport') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  ScriptApp.newTrigger('runAfterSupport')
    .timeBased()
    .everyDays(1)
    .atHour(9)
    .create();

  Logger.log('AfterSupportトリガー設定完了（毎日9時）');
}

function testAfterSupport() {
  Logger.log('AfterSupport テスト開始');
  const results = runAfterSupport();
  if (results) {
    Logger.log('車検：' + results.shaken.length + '件');
    Logger.log('保険：' + results.insurance.length + '件');
    Logger.log('完済：' + results.completion.length + '件');
    Logger.log('乗換え：' + results.switchover.length + '件');
  }
}
