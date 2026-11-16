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

// 信販マッチング：スコア/ランクから打診すべき信販会社を提案し、信販1〜8列へ自動セットする。
// 対応判定（A / A〜B / B / C / C〜D / D）に申込者ランクが含まれ、契約者種別に対応し、
// 稼働中の信販会社を、打診優先順位の昇順で最大8社まで割り当てる。
function proposeCreditors(rowNumber) {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const loanSheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
    const headers = loanSheet.getRange(1, 1, 1, loanSheet.getLastColumn()).getValues()[0];
    const row = loanSheet.getRange(rowNumber, 1, 1, loanSheet.getLastColumn()).getValues()[0];

    const data = {};
    headers.forEach(function(h, i) { data[h] = row[i]; });

    const rank = String(data['見込みランク'] || '').trim();
    const score = parseFloat(data['AIスコア']);
    const contractType = String(data['契約者種別'] || '個人');

    // 審査不可（スコア0）やランク未算出はスキップ
    if (!rank || score === 0) {
      Logger.log('信販提案スキップ（ランク=' + rank + ' / スコア=' + score + '）：行' + rowNumber);
      return null;
    }

    const creditors = getMatchingCreditors(rank, contractType, ss);
    if (creditors.length === 0) {
      Logger.log('対応信販なし（ランク' + rank + '）：行' + rowNumber);
      return null;
    }

    writeProposedCreditors(rowNumber, creditors, loanSheet, headers);
    Logger.log('信販提案：行' + rowNumber + ' ランク' + rank + ' → ' + creditors.length + '社');
    return creditors;

  } catch(err) {
    Logger.log('proposeCreditorsエラー：' + err.toString());
    return null;
  }
}

// 申込者ランク・契約者種別に合致する稼働中の信販会社を、打診優先順位の昇順で返す
function getMatchingCreditors(rank, contractType, ss) {
  const config = getConfig();
  const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.CREDITOR);
  const data = sheet.getDataRange().getValues();
  const h = data[0];

  const nameIdx     = h.indexOf('信販会社名');
  const rateMinIdx  = h.indexOf('最低金利');
  const rateMaxIdx  = h.indexOf('最高金利');
  const judgeIdx    = h.indexOf('対応判定');
  const priorityIdx = h.indexOf('打診優先順位');
  const statusIdx   = h.indexOf('ステータス');

  // 契約者種別 → 対応列
  let typeCol = '個人対応';
  if (contractType.indexOf('法人') !== -1) typeCol = '法人対応';
  else if (contractType.indexOf('個人事業主') !== -1) typeCol = '個人事業主対応';
  const typeIdx = h.indexOf(typeCol);

  const matched = [];
  for (var i = 1; i < data.length; i++) {
    const r = data[i];
    if (!r[nameIdx]) continue;
    if (String(r[statusIdx]) !== '稼働中') continue;
    if (String(r[judgeIdx]).indexOf(rank) === -1) continue;          // 対応判定にランクが含まれる
    if (typeIdx !== -1 && String(r[typeIdx]).indexOf('○') === -1) continue; // 契約者種別に対応

    matched.push({
      name: r[nameIdx],
      priority: parseInt(r[priorityIdx]) || 99,
      rate: (r[rateMinIdx] !== '' ? r[rateMinIdx] : '') + '〜' + (r[rateMaxIdx] !== '' ? r[rateMaxIdx] : '') + '%'
    });
  }
  matched.sort(function(a, b) { return a.priority - b.priority; });
  return matched;
}

// 提案信販を 信販1〜8 の各列（社名/結果/承認額/金利）へ書き込む
function writeProposedCreditors(rowNumber, creditors, loanSheet, headers) {
  const startCol = headers.indexOf('信販1_社名') + 1;
  if (startCol <= 0) return;

  const block = [];
  for (var k = 0; k < 8; k++) {
    const c = creditors[k];
    if (c) block.push(c.name, '打診予定', '', c.rate);
    else   block.push('', '', '', '');
  }
  loanSheet.getRange(rowNumber, startCol, 1, 32).setValues([block]);

  const statusIdx = headers.indexOf('ステータス') + 1;
  if (statusIdx > 0) loanSheet.getRange(rowNumber, statusIdx).setValue('信販打診中');
}

// 【保守用】既存の全ローン案件に対して信販提案をまとめて実行
function proposeCreditorsForAll() {
  const config = getConfig();
  const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
  const loanSheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
  const lastRow = loanSheet.getLastRow();
  var count = 0;
  for (var r = 2; r <= lastRow; r++) {
    if (proposeCreditors(r)) count++;
  }
  Logger.log('信販提案（一括）：' + count + '件に提案');
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
