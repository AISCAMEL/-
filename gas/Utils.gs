/**
 * Utils.gs — 共通ユーティリティ（日付・金額の整形、ログ、リトライ）
 */

/** 金額文字列を半角整数に正規化（¥・円・カンマ・全角を除去）。失敗時 null。 */
function toAmount_(v) {
  if (v === null || v === undefined || v === '') return null;
  if (typeof v === 'number') return Math.round(v);
  let s = String(v)
    .replace(/[０-９]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0xFEE0); })
    .replace(/[，,円¥￥\s]/g, '')
    .replace(/[‐－―ー]/g, '-');
  const n = parseInt(s, 10);
  return isNaN(n) ? null : n;
}

/** 和暦・各種表記を YYYY-MM-DD に正規化。失敗時 null。 */
function toDate_(v) {
  if (!v) return null;
  let s = String(v).trim()
    .replace(/[０-９]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0xFEE0); });
  // 和暦（令和/平成）→西暦の簡易換算
  const wareki = s.match(/(令和|平成)\s*(\d+)\s*年\s*(\d+)\s*月\s*(\d+)\s*日?/);
  if (wareki) {
    const base = wareki[1] === '令和' ? 2018 : 1988; // 令和1=2019, 平成1=1989
    return pad4_(base + parseInt(wareki[2], 10)) + '-' + pad2_(wareki[3]) + '-' + pad2_(wareki[4]);
  }
  // 2026年5月31日 / 2026/5/31 / 2026-05-31
  const m = s.match(/(\d{4})[\/\-年.](\d{1,2})[\/\-月.](\d{1,2})/);
  if (m) return m[1] + '-' + pad2_(m[2]) + '-' + pad2_(m[3]);
  return null;
}

function pad2_(n) { n = String(n); return n.length < 2 ? '0' + n : n; }
function pad4_(n) { n = String(n); while (n.length < 4) n = '0' + n; return n; }

/** YYYY-MM-DD から YYYY / YYYYMM を取り出す（Driveフォルダ用）。 */
function ymParts_(dateStr) {
  const m = (dateStr || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) {
    const now = new Date();
    return { y: String(now.getFullYear()), ym: Utilities.formatDate(now, 'Asia/Tokyo', 'yyyyMM'), ymd: Utilities.formatDate(now, 'Asia/Tokyo', 'yyyyMMdd') };
  }
  return { y: m[1], ym: m[1] + m[2], ymd: m[1] + m[2] + m[3] };
}

/** 現在時刻の文字列 yyyy-MM-dd HH:mm。 */
function nowStr_() {
  return Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy-MM-dd HH:mm');
}

/** ファイル名等に使えない文字を除去。 */
function safeName_(s) {
  return String(s || '').replace(/[\\\/:*?"<>|]/g, '_').trim().slice(0, 60) || '不明';
}

/** 指数バックオフ付きリトライ。fn は成功時に値を返す。 */
function withRetry_(fn, maxRetry, label) {
  let lastErr;
  for (let i = 0; i < maxRetry; i++) {
    try { return fn(); }
    catch (e) {
      lastErr = e;
      Logger.log('リトライ' + (i + 1) + '/' + maxRetry + ' [' + label + ']: ' + e);
      if (i < maxRetry - 1) Utilities.sleep(1000 * Math.pow(2, i));
    }
  }
  throw lastErr;
}

/** 処理ログシートへ1行追記。 */
function logRow_(refId, kind, ok, message) {
  try {
    const ss = SpreadsheetApp.openById(getProp_(CONFIG.PROP.SPREADSHEET_ID, true));
    const sh = ss.getSheetByName(CONFIG.SHEET.LOG);
    sh.appendRow([nowStr_(), refId || '', kind, ok ? '成功' : '失敗', message || '', 'system']);
  } catch (e) {
    Logger.log('ログ書込失敗: ' + e);
  }
}

/** 文字列がコードフェンスで囲まれていれば中身を取り出してJSONパース。 */
function parseJsonLoose_(text) {
  if (!text) throw new Error('空のAI応答');
  let t = String(text).trim();
  const fence = t.match(/```(?:json)?\s*([\s\S]*?)```/i);
  if (fence) t = fence[1].trim();
  const first = t.indexOf('{'), last = t.lastIndexOf('}');
  if (first >= 0 && last > first) t = t.slice(first, last + 1);
  return JSON.parse(t);
}
