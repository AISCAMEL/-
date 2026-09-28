/**
 * WebApi.gs
 * WordPress（本部・加盟店画面）連携API。
 * Main.gs の doGet / doPost から、action パラメータ付きリクエストが振り分けられる。
 *
 * 認証：全リクエストに token=（CONFIG.API.TOKEN）が必須。
 * 注意：ブラウザから直接呼ぶとCORS制限を受けるため、WordPress側はPHP等の
 *       サーバーサイドからこのAPIを呼び出すこと（サーバー間通信ならCORS無し）。
 *
 * エンドポイント（/exec?...）：
 *   GET  action=cases [&franchisee=][&status=][&rank=]   案件一覧（本部=全件 / 加盟店=自店）
 *   POST action=result &caseId=&creditorIndex=（or creditorName=）&result=承認|否決 [&amount=]
 *   POST action=assign &caseId=&franchiseeName=
 */

function handleApiGet(e) {
  if (!apiAuthorized(e)) return jsonOutput({ ok: false, error: 'unauthorized' });
  const action = String(e.parameter.action || '').toLowerCase();
  if (action === 'cases') return jsonOutput(getCasesForApi(e.parameter));
  return jsonOutput({ ok: false, error: 'unknown_action' });
}

function handleApiPost(e) {
  if (!apiAuthorized(e)) return jsonOutput({ ok: false, error: 'unauthorized' });
  const action = String(e.parameter.action || '').toLowerCase();
  if (action === 'result') return jsonOutput(recordCreditorResult(e.parameter));
  if (action === 'assign') return jsonOutput(apiAssignFranchisee(e.parameter));
  if (action === 'apply')  return jsonOutput(applyFromLiff(e.parameter));
  return jsonOutput({ ok: false, error: 'unknown_action' });
}

// LIFF申込アプリからの新規申込を受け付け、案件登録＋スコアリング＋信販提案を行い、
// 結果（スコア・ランク・月々目安）を返す。LINE userId を確実に保存する。
function applyFromLiff(params) {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(30000)) return { ok: false, error: 'busy' };
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];

    const uid  = String(params.userId || '').trim();
    const name = String(params.name || '').trim();
    if (!uid)  return { ok: false, error: 'no_userid' };
    if (!name) return { ok: false, error: 'no_name' };

    const jijou = String(params.jijou || '');
    const ty = String(params.ty || '0'), tm = String(params.tm || '0');
    const caseId = 'LOAN-' + uid.slice(-8) + '-' + Utilities.formatDate(new Date(), 'Asia/Tokyo', 'MMddHHmm');

    const dataMap = {
      '申込番号':       caseId,
      '受付日時':       new Date(),
      'ステータス':     '書類確認中',
      '契約者種別':     '個人',
      '顧客名':         name,
      '生年月日':       String(params.birth || ''),
      '電話番号':       String(params.tel || ''),
      'LINE_ID':        uid,
      '郵便番号':       String(params.zip || ''),
      '住所':           String(params.addr || ''),
      '雇用形態':       String(params.emp || ''),
      '勤続年数':       ty + '年' + tm + 'ヶ月',
      '年収':           String(params.income || '') + '万円',
      '月額支払い可能額': String(params.pay || ''),
      '他社借入総額':   String(params.other || ''),
      '直近6ヶ月審査数': String(params.shinsa || '0'),
      '信用情報':       jijou,
      '債務整理歴':     jijou.indexOf('債務整理') !== -1 ? '債務整理' : '',
      '自己破産歴':     jijou.indexOf('自己破産') !== -1 ? '自己破産' : '',
      '滞納履歴':       jijou,
      '頭金有無':       String(params.down) === '1' ? '頭金あり' : '頭金なし',
      '住居状況':       String(params.house || ''),
      '保証人有無':     String(params.guar) === '1' ? 'はい' : 'いいえ',
      '購入予定車両':   String(params.car || ''),
      '登録日時':       new Date()
    };

    sheet.appendRow(buildRowFromMap(headers, dataMap));
    const lastRow = sheet.getLastRow();
    const sc = runScoring(lastRow);          // スコア・ランク算出（既存）
    proposeCreditors(lastRow);               // 打診信販の自動提案（既存）

    return {
      ok: true,
      caseId: caseId,
      score:  sc ? sc.score : null,
      rank:   sc ? sc.rank : null,
      monthly: sc ? sc.monthly : null
    };
  } catch(err) {
    return { ok: false, error: String(err) };
  } finally {
    lock.releaseLock();
  }
}

function apiAuthorized(e) {
  const config = getConfig();
  const token = (e && e.parameter && e.parameter.token) || '';
  const expected = (config.API && config.API.TOKEN) ? config.API.TOKEN : '';
  return expected !== '' && expected !== 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET' && token === expected;
}

function jsonOutput(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

function apiFmt(v) {
  if (v instanceof Date) return Utilities.formatDate(v, 'Asia/Tokyo', 'yyyy/MM/dd HH:mm');
  return v == null ? '' : String(v);
}

// 案件一覧を返す（本部=全件、加盟店=franchisee指定で自店のみ）
function getCasesForApi(params) {
  const config = getConfig();
  const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
  const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
  const values = sheet.getDataRange().getValues();
  const headers = values[0];
  const idx = {};
  headers.forEach(function(h, i) { idx[h] = i; });

  const fFranchisee = String(params.franchisee || '').trim();
  const fStatus     = String(params.status || '').trim();
  const fRank       = String(params.rank || '').trim();

  const cases = [];
  for (var r = 1; r < values.length; r++) {
    const row = values[r];
    if (!row[idx['申込番号']]) continue;
    if (fFranchisee && String(row[idx['担当加盟店']] || '') !== fFranchisee) continue;
    if (fStatus && String(row[idx['ステータス']] || '') !== fStatus) continue;
    if (fRank && String(row[idx['見込みランク']] || '') !== fRank) continue;

    const creditors = [];
    for (var k = 1; k <= 8; k++) {
      const cname = row[idx['信販' + k + '_社名']];
      if (!cname) continue;
      creditors.push({
        index:  k,
        name:   cname,
        rate:   row[idx['信販' + k + '_金利']] || '',
        result: row[idx['信販' + k + '_結果']] || '',
        amount: row[idx['信販' + k + '_承認額']] || ''
      });
    }

    cases.push({
      caseId:     row[idx['申込番号']],
      name:       row[idx['顧客名']] || '',
      receivedAt: apiFmt(row[idx['受付日時']]),
      rank:       row[idx['見込みランク']] || '',
      score:      row[idx['AIスコア']] || '',
      status:     row[idx['ステータス']] || '',
      franchisee: row[idx['担当加盟店']] || '',
      summary: {
        income:      row[idx['年収']] || '',
        employment:  row[idx['雇用形態']] || '',
        tenure:      row[idx['勤続年数']] || '',
        otherLoan:   row[idx['他社借入総額']] || '',
        overdue:     row[idx['滞納履歴']] || '',
        downPayment: row[idx['頭金有無']] || '',
        guarantor:   row[idx['保証人有無']] || ''
      },
      creditors: creditors
    });
  }
  return { ok: true, count: cases.length, cases: cases };
}

// 信販の承認/否決を記録（承認額も）。承認が1社でも出たらステータスを「承認あり」に。
function recordCreditorResult(params) {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(30000)) return { ok: false, error: 'busy' };
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
    const values = sheet.getDataRange().getValues();
    const headers = values[0];
    const idx = {};
    headers.forEach(function(h, i) { idx[h] = i; });

    const caseId = String(params.caseId || '').trim();
    const result = String(params.result || '').trim(); // '承認' or '否決'
    const amount = params.amount || '';
    if (!caseId || (result !== '承認' && result !== '否決')) {
      return { ok: false, error: 'invalid_params' };
    }

    var rowNumber = -1, rowVals = null;
    for (var r = 1; r < values.length; r++) {
      if (String(values[r][idx['申込番号']]) === caseId) { rowNumber = r + 1; rowVals = values[r]; break; }
    }
    if (rowNumber === -1) return { ok: false, error: 'case_not_found' };

    // 対象の信販スロット（1〜8）を特定
    var slot = parseInt(params.creditorIndex, 10);
    if (!slot) {
      const cname = String(params.creditorName || '').trim();
      for (var k = 1; k <= 8; k++) {
        if (cname && String(rowVals[idx['信販' + k + '_社名']]) === cname) { slot = k; break; }
      }
    }
    if (!slot || slot < 1 || slot > 8) return { ok: false, error: 'creditor_not_found' };

    sheet.getRange(rowNumber, idx['信販' + slot + '_結果'] + 1).setValue(result);
    if (result === '承認') {
      sheet.getRange(rowNumber, idx['信販' + slot + '_承認額'] + 1).setValue(amount);
    }

    // 承認が1社でもあればステータスを「承認あり」に
    var hasApproved = (result === '承認');
    if (!hasApproved) {
      for (var k2 = 1; k2 <= 8; k2++) {
        if (String(rowVals[idx['信販' + k2 + '_結果']]).indexOf('承認') !== -1) { hasApproved = true; break; }
      }
    }
    if (hasApproved) {
      sheet.getRange(rowNumber, idx['ステータス'] + 1).setValue('承認あり');
    }

    return { ok: true, caseId: caseId, slot: slot, result: result, statusUpdated: hasApproved };

  } catch(err) {
    return { ok: false, error: String(err) };
  } finally {
    lock.releaseLock();
  }
}

// 加盟店アサイン（既存の assignFranchisee を申込番号で呼ぶ）
function apiAssignFranchisee(params) {
  const config = getConfig();
  const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
  const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.LOAN);
  const values = sheet.getDataRange().getValues();
  const caseIdCol = values[0].indexOf('申込番号');

  const caseId = String(params.caseId || '').trim();
  const franchiseeName = String(params.franchiseeName || '').trim();
  if (!caseId || !franchiseeName) return { ok: false, error: 'invalid_params' };

  var rowNumber = -1;
  for (var r = 1; r < values.length; r++) {
    if (String(values[r][caseIdCol]) === caseId) { rowNumber = r + 1; break; }
  }
  if (rowNumber === -1) return { ok: false, error: 'case_not_found' };

  const ok = assignFranchisee(rowNumber, franchiseeName);
  return { ok: ok, caseId: caseId, franchisee: franchiseeName };
}
