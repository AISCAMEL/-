/**
 * Setup.gs — シート初期化
 *
 * 【最初に1回だけ実行】
 * メニューの関数選択で setupAll を選び「実行」を押すと、
 * 必要なシート（オーダー管理／出品管理／ローン審査／会員マスタ／問い合わせ）
 * と見出しを自動作成します。
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

  ensureSheet_(ss, cfg.SHEET_SELL, [
    "受付日時", "出品番号", "ステータス",
    "お名前", "メール",
    "車種", "想定落札価格", "車両状態",
    "出品代行手数料", "手取り目安", "代理搬入",
    "出品票PDF", "AI要約", "AI優先度", "備考"
  ]);

  ensureSheet_(ss, cfg.SHEET_LOAN, [
    "受付日時", "申込番号", "ステータス",
    "お名前", "メール", "電話",
    "希望額", "回数", "雇用形態", "年収",
    "AIスコア", "AI判定", "関連オーダー", "備考"
  ]);

  ensureSheet_(ss, cfg.SHEET_MEMBERS, [
    "登録日時", "会員ID", "お名前", "メール", "希望プラン", "流入元",
    "ランク", "完了件数", "紹介コード", "紹介元コード", "紹介人数", "LINE_UID"
  ]);

  ensureSheet_(ss, cfg.SHEET_CONTACTS, [
    "受付日時", "お名前", "メール", "電話", "お問い合わせ内容", "対応状況"
  ]);

  ensureSheet_(ss, cfg.SHEET_QUOTES, [
    "受付日時", "見積番号", "種別(買取/仕入れ)", "お名前", "メール", "連絡方法",
    "車両情報", "回答相場額", "回答状況"
  ]);

  ensureSheet_(ss, "紹介管理", [
    "日時", "紹介元会員ID", "紹介元メール", "紹介先会員ID", "紹介先メール",
    "紹介コード", "紹介元クーポン", "紹介先クーポン", "ステータス"
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
