function onProLineFormSubmit() {
  // 5分間隔トリガーと手動実行の同時走行による二重登録を防ぐ
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(30000)) {
    Logger.log('onProLineFormSubmit：ロック取得失敗のためスキップ');
    return;
  }
  try {
    const config = getConfig();
    const formSS = SpreadsheetApp.openById(config.SPREADSHEET.PROLINE_FORM_ID);
    const formSheet = formSS.getSheetByName(config.SPREADSHEET.PROLINE_FORM_SHEET);
    const loanSS = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const loanSheet = loanSS.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
    const formData = formSheet.getDataRange().getValues();
    const loanData = loanSheet.getDataRange().getValues();

    // 登録済み申込番号をマップ化（indexOf より高速・重複判定を明確化）
    const processedIds = {};
    for (var i = 1; i < loanData.length; i++) {
      if (loanData[i][0]) processedIds[loanData[i][0]] = true;
    }

    // 古い順に走査し、未処理の申込をすべて登録する
    var registered = 0;
    for (var j = 1; j < formData.length; j++) {
      const row = formData[j];
      const uid = row[1];
      if (!uid) continue;
      const caseId = 'LOAN-' + uid;
      if (processedIds[caseId]) continue;

      const dataMap = mapFormToLoan(row, caseId);
      const caseData = buildRowFromMap(loanData[0], dataMap);
      loanSheet.appendRow(caseData);
      processedIds[caseId] = true; // 同一実行内での二重登録も防ぐ
      const lastRow = loanSheet.getLastRow();
      const scoreResult = runScoring(lastRow);
      //const lineId = getLineIdByUid(uid, loanSS);
      //if (lineId) {
      //  notifyCustomerReceived(lineId, scoreResult);
      //}
      Logger.log('新規案件登録：' + caseId);
      registered++;
    }
    Logger.log('onProLineFormSubmit：' + registered + '件登録');
  } catch(err) {
    Logger.log('onProLineFormSubmitエラー：' + err.toString());
  } finally {
    lock.releaseLock();
  }
}

// ProLineフォーム（form_3：かんたん審査）の回答列 → ローン案件管理の項目
// ※ row[n] の n は回答シートの列番号（0始まり）。フォーム項目追加で
//   ずれないよう、項目名キーのオブジェクトで返し buildRowFromMap で並べ替える。
function mapFormToLoan(row, caseId) {
  const jijou = String(row[5] || '');   // 現在の事情を教えてください
  return {
    '申込番号':       caseId,
    '受付日時':       row[0],
    'ステータス':     '書類確認中',
    '契約者種別':     '個人',
    '顧客名':         row[10],
    '生年月日':       row[14],
    '電話番号':       row[22],
    'LINE_ID':        row[1],
    '郵便番号':       row[16],
    '住所':           [row[17], row[18], row[19], row[20]].filter(String).join(''),
    '雇用形態':       row[36],
    '勤続年数':       (row[45] || '') + '年' + (row[46] || '') + 'ヶ月',
    '年収':           row[47],
    '希望借入額':     '',               // フォームに項目なし
    '希望返済期間':   '',               // フォームに項目なし
    '月額支払い可能額': row[59],          // 返済負担率の算定に使用
    '他社借入総額':   row[34],
    '他社借入件数':   row[33],
    '直近6ヶ月審査数': row[8],
    '信用情報':       jijou,
    '債務整理歴':     jijou.indexOf('債務整理') !== -1 ? '債務整理' : '',
    '自己破産歴':     jijou.indexOf('自己破産') !== -1 ? '自己破産' : '',
    '滞納履歴':       row[71],           // 未払い・滞納状況
    '購入予定車両':   [row[52], row[53]].filter(String).join(' '),
    '頭金有無':       row[61],           // 頭金問診
    '頭金額':         row[62],           // 頭金ありの場合
    '貯金額':         row[35],
    '住居状況':       row[29],
    '保証人有無':     row[72],           // 保証人の有無
    '登録日時':       new Date()
  };
}

// シートのヘッダー順に合わせて値配列を組み立てる（未設定の列は空欄）
function buildRowFromMap(headers, dataMap) {
  return headers.map(function(h) {
    return Object.prototype.hasOwnProperty.call(dataMap, h) ? dataMap[h] : '';
  });
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

// pushToLine() は LineNotify.gs に一本化（重複定義を解消）

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
