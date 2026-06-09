function setupAllTriggers() {
  deleteAllTriggers();

  const config = getConfig();
  const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);

  // onProLineFormSubmit（5分おき）
  ScriptApp.newTrigger('onProLineFormSubmit')
    .timeBased()
    .everyMinutes(5)
    .create();
  Logger.log('✅ onProLineFormSubmit：5分おき');

  // runDelayCalc（毎日9時）
  ScriptApp.newTrigger('runDelayCalc')
    .timeBased()
    .everyDays(1)
    .atHour(9)
    .create();
  Logger.log('✅ runDelayCalc：毎日9時');

  // runAfterSupport（毎日9時）
  ScriptApp.newTrigger('runAfterSupport')
    .timeBased()
    .everyDays(1)
    .atHour(9)
    .create();
  Logger.log('✅ runAfterSupport：毎日9時');

  // runReminder（毎日10時）
  ScriptApp.newTrigger('runReminder')
    .timeBased()
    .everyDays(1)
    .atHour(10)
    .create();
  Logger.log('✅ runReminder：毎日10時');

  // notifyWeeklyReport（毎週月曜9時）
  ScriptApp.newTrigger('notifyWeeklyReport')
    .timeBased()
    .onWeekDay(ScriptApp.WeekDay.MONDAY)
    .atHour(9)
    .create();
  Logger.log('✅ notifyWeeklyReport：毎週月曜9時');

  // onSheetEdit（スプレッドシート編集時）
  ScriptApp.newTrigger('onSheetEdit')
    .forSpreadsheet(ss)
    .onEdit()
    .create();
  Logger.log('✅ onSheetEdit：スプレッドシート編集時');

  Logger.log('全トリガー設定完了');
}

function deleteAllTriggers() {
  const triggers = ScriptApp.getProjectTriggers();
  triggers.forEach(function(trigger) {
    ScriptApp.deleteTrigger(trigger);
  });
  Logger.log('全トリガー削除完了：' + triggers.length + '件');
}

function checkAllTriggers() {
  const triggers = ScriptApp.getProjectTriggers();
  Logger.log('現在のトリガー数：' + triggers.length + '件');
  triggers.forEach(function(trigger) {
    Logger.log(
      '関数：' + trigger.getHandlerFunction() +
      ' | タイプ：' + trigger.getEventType() +
      ' | ソース：' + trigger.getTriggerSource()
    );
  });
}

function testTrigger() {
  Logger.log('Trigger テスト開始');
  checkAllTriggers();
  Logger.log('Trigger テスト完了');
}
