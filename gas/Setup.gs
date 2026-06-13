/**
 * Setup.gs — シート初期化
 *
 * 【最初に1回だけ実行】
 * メニューの関数選択で setupAll を選び「実行」を押すと、
 * 必要な3シート（オーダー管理／会員マスタ／問い合わせ）と見出しを自動作成します。
 */
function setupAll() {
  var cfg = getConfig();
  var ss = openBook_();

  ensureSheet_(ss, cfg.SHEET_ORDERS, [
    "受付日時", "オーダー番号", "ステータス",
    "お名前", "メール", "プラン",
    "希望車種", "落札上限予算", "車両クラス", "納車エリア",
    "代行手数料", "想定総額", "オプション",
    "AI要約", "AI優先度", "備考"
  ]);

  ensureSheet_(ss, cfg.SHEET_MEMBERS, [
    "登録日時", "会員ID", "お名前", "メール", "希望プラン", "流入元"
  ]);

  ensureSheet_(ss, cfg.SHEET_CONTACTS, [
    "受付日時", "お名前", "メール", "電話", "お問い合わせ内容", "対応状況"
  ]);

  SpreadsheetApp.flush();
  Logger.log("セットアップ完了：" + ss.getUrl());
}

/**
 * シートが無ければ作成し、1行目に見出しを設定する。
 */
function ensureSheet_(ss, name, headers) {
  var sh = ss.getSheetByName(name);
  if (!sh) sh = ss.insertSheet(name);
  if (sh.getLastRow() === 0) {
    sh.getRange(1, 1, 1, headers.length).setValues([headers]);
    sh.getRange(1, 1, 1, headers.length).setFontWeight("bold").setBackground("#0e1b33").setFontColor("#ffffff");
    sh.setFrozenRows(1);
  }
  return sh;
}
