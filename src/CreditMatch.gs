function runCreditMatching(rowNumber) {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const loanSheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
    const row = loanSheet.getRange(rowNumber, 1, 1, loanSheet.getLastColumn()).getValues()[0];
    const headers = loanSheet.getRange(1, 1, 1, loanSheet.getLastColumn()).getValues()[0];

    const data = {};
    headers.forEach(function(header, i) { data[header] = row[i]; });

    const approvedCreditors = getApprovedCreditors(data, headers, row);
    if (approvedCreditors.length === 0) {
      Logger.log('承認済み信販なし：行' + rowNumber);
      return null;
    }

    const candidates = matchFranchisees(approvedCreditors, ss);
    Logger.log('候補加盟店数：' + candidates.length);

    saveCandidatesToSheet(rowNumber, candidates, loanSheet, headers);

    return {
      caseId: data['申込番号'],
      customerName: data['顧客名'],
      approvedCreditors: approvedCreditors,
      candidates: candidates
    };

  } catch(err) {
    Logger.log('runCreditMatchingエラー：' + err.toString());
    return null;
  }
}

function getApprovedCreditors(data, headers, row) {
  const approved = [];
  for (var i = 1; i <= 8; i++) {
    const nameKey = '信販' + i + '_社名';
    const resultKey = '信販' + i + '_結果';
    const amountKey = '信販' + i + '_承認額';
    const rateKey = '信販' + i + '_金利';

    const name = data[nameKey];
    const result = data[resultKey];

    if (name && result && result.includes('承認')) {
      approved.push({
        name: name,
        result: result,
        amount: data[amountKey] || '',
        rate: data[rateKey] || ''
      });
    }
  }
  return approved;
}

function matchFranchisees(approvedCreditors, ss) {
  try {
    const config = getConfig();
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.FRANCHISEE);
    const data = sheet.getDataRange().getValues();
    const headers = data[0];
    const candidates = [];

    const idIdx = headers.indexOf('加盟店ID');
    const nameIdx = headers.indexOf('加盟店名');
    const areaIdx = headers.indexOf('エリア');
    const creditorIdx = headers.indexOf('対応信販リスト');
    const caseCountIdx = headers.indexOf('現在案件数');
    const statusIdx = headers.indexOf('ステータス');

    for (var i = 1; i < data.length; i++) {
      const row = data[i];
      if (!row[idIdx]) continue;

      const status = row[statusIdx];
      const caseCount = parseInt(row[caseCountIdx]) || 0;
      const creditorList = row[creditorIdx] || '';

      if (status !== '稼働中') continue;
      if (caseCount >= config.FRANCHISEE.MAX_CASES) continue;

      const hasMatch = approvedCreditors.some(function(creditor) {
        return creditorList.includes(creditor.name);
      });

      if (hasMatch) {
        candidates.push({
          id: row[idIdx],
          name: row[nameIdx],
          area: row[areaIdx],
          caseCount: caseCount,
          creditorList: creditorList
        });
      }
    }

    candidates.sort(function(a, b) { return a.caseCount - b.caseCount; });
    return candidates;

  } catch(err) {
    Logger.log('matchFranchiseesエラー：' + err.toString());
    return [];
  }
}

function saveCandidatesToSheet(rowNumber, candidates, sheet, headers) {
  try {
    const statusIdx = headers.indexOf('ステータス') + 1;
    if (statusIdx > 0) {
      sheet.getRange(rowNumber, statusIdx).setValue('加盟店選定中');
    }
    Logger.log('候補加盟店を保存：' + candidates.length + '件');
  } catch(err) {
    Logger.log('saveCandidatesToSheetエラー：' + err.toString());
  }
}

function assignFranchisee(rowNumber, franchiseeName) {
  // セル編集トリガー経由で多重実行されても案件数を二重加算しないよう排他制御
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(30000)) {
    Logger.log('assignFranchisee：ロック取得失敗のためスキップ');
    return false;
  }
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    const row = sheet.getRange(rowNumber, 1, 1, sheet.getLastColumn()).getValues()[0];

    const data = {};
    headers.forEach(function(h, i) { data[h] = row[i]; });

    const prevFranchisee = data['担当加盟店'] || '';

    // 同じ加盟店が再入力された場合は案件数を増やさず終了（冪等性を確保）
    if (prevFranchisee === franchiseeName) {
      Logger.log('加盟店アサイン：変更なし（' + franchiseeName + '）');
      return true;
    }

    const franchiseeIdx = headers.indexOf('担当加盟店') + 1;
    const assignDateIdx = headers.indexOf('アサイン日時') + 1;
    const statusIdx = headers.indexOf('ステータス') + 1;

    sheet.getRange(rowNumber, franchiseeIdx).setValue(franchiseeName);
    sheet.getRange(rowNumber, assignDateIdx).setValue(new Date());
    sheet.getRange(rowNumber, statusIdx).setValue('アサイン済み');

    // 付け替えの場合は旧加盟店の案件数を減らし、新加盟店を増やす
    if (prevFranchisee) {
      adjustFranchiseeCaseCount(prevFranchisee, ss, -1);
    }
    adjustFranchiseeCaseCount(franchiseeName, ss, 1);

    Logger.log('加盟店アサイン完了：' + franchiseeName);
    return true;

  } catch(err) {
    Logger.log('assignFranchiseeエラー：' + err.toString());
    return false;
  } finally {
    lock.releaseLock();
  }
}

function adjustFranchiseeCaseCount(franchiseeName, ss, delta) {
  try {
    const config = getConfig();
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.FRANCHISEE);
    const data = sheet.getDataRange().getValues();
    const headers = data[0];
    const nameIdx = headers.indexOf('加盟店名');
    const countIdx = headers.indexOf('現在案件数');

    for (var i = 1; i < data.length; i++) {
      if (data[i][nameIdx] === franchiseeName) {
        const current = parseInt(data[i][countIdx]) || 0;
        const updated = Math.max(0, current + delta);
        sheet.getRange(i + 1, countIdx + 1).setValue(updated);
        Logger.log('案件数更新：' + franchiseeName + ' → ' + updated);
        break;
      }
    }
  } catch(err) {
    Logger.log('adjustFranchiseeCaseCountエラー：' + err.toString());
  }
}

function onSheetEdit(e) {
  try {
    const sheet = e.source.getActiveSheet();
    const sheetName = sheet.getName();
    const range = e.range;
    const col = range.getColumn();
    const row = range.getRow();

    if (sheetName !== 'ローン案件管理') return;
    if (row <= 1) return;

    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    const franchiseeIdx = headers.indexOf('担当加盟店') + 1;

    if (col !== franchiseeIdx) return;

    const franchiseeName = range.getValue();
    if (!franchiseeName) return;

    Logger.log('加盟店入力検知：' + franchiseeName + '（行' + row + '）');
    assignFranchisee(row, franchiseeName);

  } catch(err) {
    Logger.log('onSheetEditエラー：' + err.toString());
  }
}

function testCreditMatch() {
  const config = getConfig();
  const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
  const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.FRANCHISEE);
  const data = sheet.getDataRange().getValues();
  Logger.log('加盟店マスタ件数：' + (data.length - 1));
  data.slice(1).forEach(function(row) {
    Logger.log('加盟店：' + row[0] + ' | ' + row[1] + ' | 案件数：' + row[8] + ' | 状態：' + row[9]);
  });
}
function setupSheetEditTrigger() {
  const config = getConfig();
  const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
  
  // 既存のトリガーを削除
  ScriptApp.getProjectTriggers().forEach(function(trigger) {
    if (trigger.getHandlerFunction() === 'onSheetEdit') {
      ScriptApp.deleteTrigger(trigger);
    }
  });
  
  // 新しいトリガーを作成
  ScriptApp.newTrigger('onSheetEdit')
    .forSpreadsheet(ss)
    .onEdit()
    .create();
    
  Logger.log('onSheetEditトリガー設定完了');
}
