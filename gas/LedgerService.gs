/**
 * LedgerService.gs — 請求台帳シートの読み書き・採番・重複チェック
 */

/** 台帳の全行をヘッダ名キーのオブジェクト配列で取得。 */
function loadLedger_() {
  const sh = ledgerSheet_();
  const values = sh.getDataRange().getValues();
  if (values.length < 2) return [];
  const headers = values[0];
  const rows = [];
  for (let i = 1; i < values.length; i++) {
    const o = {};
    for (let c = 0; c < headers.length; c++) o[headers[c]] = values[i][c];
    rows.push(o);
  }
  return rows;
}

function ledgerSheet_() {
  const ss = SpreadsheetApp.openById(getProp_(CONFIG.PROP.SPREADSHEET_ID, true));
  return ss.getSheetByName(CONFIG.SHEET.LEDGER);
}

/** メールIDが既に台帳にあるか（冪等性：二重処理防止）。 */
function isMailProcessed_(rows, mailId) {
  for (let i = 0; i < rows.length; i++) {
    if (String(rows[i]['メールID']) === String(mailId)) return true;
  }
  return false;
}

/** 次の請求IDを採番（INV-YYYY-連番）。 */
function nextInvoiceId_(rows) {
  const year = Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy');
  let max = 0;
  rows.forEach(function (r) {
    const m = String(r['請求ID']).match(new RegExp('^INV-' + year + '-(\\d+)$'));
    if (m) max = Math.max(max, parseInt(m[1], 10));
  });
  return 'INV-' + year + '-' + pad4_(max + 1);
}

/** レコード(ヘッダ名キーのオブジェクト)を台帳に1行追記。 */
function appendLedger_(rec) {
  const row = CONFIG.LEDGER_HEADERS.map(function (h) {
    return rec[h] !== undefined && rec[h] !== null ? rec[h] : '';
  });
  ledgerSheet_().appendRow(row);
}
