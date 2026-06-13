/**
 * VendorMaster.gs — 業者マスタの読み込みと名寄せ（表記ゆらぎ吸収）
 * templates/業者マスタ.csv の列順に対応。
 */
const VENDOR_COL = {
  NORM: 0,        // 正規化業者名
  ALIASES: 1,     // 表記ゆらぎ(セミコロン区切り)
  EMAILS: 2,      // 送信元アドレス(セミコロン区切り)
  DR_ACCOUNT: 3,  // 既定借方勘定科目
  DR_SUB: 4,      // 既定補助科目
  TAX_CLASS: 5,   // 既定税区分
  GENSEN: 6,      // 源泉徴収対象(有/無)
  GENSEN_RATE: 7, // 源泉税率
  CR_ACCOUNT: 8,  // 既定貸方勘定科目
  PATTERN: 9,     // 仕訳パターンID
  PAYSITE: 10,    // 支払サイト
  BANK: 11,       // 振込先
  AUTO_OK: 12,    // 自動承認可否(可/否)
  NOTE: 13,
};

/** マスタ全行をオブジェクト配列で取得（キャッシュ的に1回読む想定）。 */
function loadVendors_() {
  const ss = SpreadsheetApp.openById(getProp_(CONFIG.PROP.SPREADSHEET_ID, true));
  const sh = ss.getSheetByName(CONFIG.SHEET.VENDOR);
  const values = sh.getDataRange().getValues();
  const rows = [];
  for (let i = 1; i < values.length; i++) { // 0はヘッダ
    const r = values[i];
    if (!r[VENDOR_COL.NORM]) continue;
    rows.push({
      norm: String(r[VENDOR_COL.NORM]).trim(),
      aliases: splitList_(r[VENDOR_COL.ALIASES]),
      emails: splitList_(r[VENDOR_COL.EMAILS]).map(function (s) { return s.toLowerCase(); }),
      drAccount: r[VENDOR_COL.DR_ACCOUNT], drSub: r[VENDOR_COL.DR_SUB],
      taxClass: r[VENDOR_COL.TAX_CLASS], gensen: String(r[VENDOR_COL.GENSEN]).indexOf('有') >= 0,
      gensenRate: r[VENDOR_COL.GENSEN_RATE], crAccount: r[VENDOR_COL.CR_ACCOUNT] || '未払金',
      pattern: r[VENDOR_COL.PATTERN], autoOk: String(r[VENDOR_COL.AUTO_OK]).indexOf('可') >= 0,
    });
  }
  return rows;
}

function splitList_(v) {
  return String(v || '').split(/[;；]/).map(function (s) { return s.trim(); }).filter(String);
}

/**
 * 送信元アドレス → 業者名 で照合。見つかった業者を返す（無ければ null）。
 * メールの送り主から業者を即特定でき、抽出精度のヒントにもなる。
 */
function findVendorByEmail_(vendors, fromAddress) {
  const addr = (String(fromAddress || '').match(/<([^>]+)>/) || [null, fromAddress])[1];
  const low = String(addr || '').toLowerCase();
  for (let i = 0; i < vendors.length; i++) {
    if (vendors[i].emails.indexOf(low) >= 0) return vendors[i];
  }
  return null;
}

/** 抽出された業者名 → 正規化業者名（表記ゆらぎ吸収）。一致した業者を返す。 */
function findVendorByName_(vendors, rawName) {
  const key = normalizeKey_(rawName);
  for (let i = 0; i < vendors.length; i++) {
    const v = vendors[i];
    if (normalizeKey_(v.norm) === key) return v;
    for (let j = 0; j < v.aliases.length; j++) {
      if (normalizeKey_(v.aliases[j]) === key) return v;
    }
  }
  return null;
}

/** 比較用キー：法人格・空白・記号を落として比較する。 */
function normalizeKey_(s) {
  return String(s || '')
    .replace(/[（）()株式会社有限会社合同会社\s　・,，.。\-‐－]/g, '')
    .replace(/[Ａ-Ｚａ-ｚ]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0xFEE0); })
    .toLowerCase();
}
