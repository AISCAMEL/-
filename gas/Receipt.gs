/**
 * Receipt.gs — 領収書（Receipt / Invoice）PDF生成
 *
 * 【概要】
 * オーダーまたは出品の受付番号から領収書PDFを自動生成し、Driveに保存。
 * Slackスラッシュコマンド `/領収書 OD-2041` で呼び出し可。
 */

/**
 * 領収書PDFを Google ドキュメント経由で生成し、ドライブに保存。
 * @param {string} orderId 受付番号（OD-xxxx / SL-xxxx）
 * @param {object} data { name, car, unitPrice, tax, total, date }
 * @return {GoogleAppsScript.Drive.File} 生成したPDFファイル
 */
function makeReceiptPdf_(orderId, data) {
  var cfg = getConfig();
  var today = new Date().toLocaleDateString("ja-JP");
  var doc = DocumentApp.create("領収書_" + orderId);
  var body = doc.getBody();

  // ヘッダー
  body.appendParagraph("領収書")
    .setHeading(DocumentApp.ParagraphHeading.HEADING1);
  body.appendParagraph("発行日：" + (data.date || today) + "　受付番号：" + orderId)
    .setFontSize(10);
  body.appendParagraph("");

  // 発行者情報
  body.appendParagraph("合同会社アイズ（AUC-AGENT）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph("所在地：〒979-0204 福島県いわき市四倉町細谷字大町1番").setFontSize(9);
  body.appendParagraph("電話：050-1722-3365 ／ メール：info@aisjaltd.com").setFontSize(9);
  body.appendParagraph("適格請求書発行事業者 登録番号：T9380003004349").setFontSize(9);
  body.appendParagraph("古物商許可証番号：第25121A010859号").setFontSize(9);
  body.appendParagraph("");

  // 宛名
  body.appendParagraph("宛名：" + (data.name || "＿＿＿＿＿＿＿＿") + " 様")
    .setFontSize(11);
  body.appendParagraph("");

  // 明細テーブル
  var unitPrice = num_(data.unitPrice);
  var tax = Math.round(unitPrice * 0.1);
  var lineTotal = unitPrice + tax;

  body.appendParagraph("明細").setHeading(DocumentApp.ParagraphHeading.HEADING3);
  body.appendTable([
    ["品名（車両名）", "数量", "単価", "消費税（10%）", "合計"],
    [data.car || "車両", "1", yen_(unitPrice), yen_(tax), yen_(lineTotal)]
  ]);

  // 小計・税・合計
  body.appendParagraph("");
  body.appendTable([
    ["小計（税抜）", yen_(unitPrice)],
    ["消費税（10%）", yen_(tax)],
    ["合計（税込）", yen_(lineTotal)]
  ]);

  // 振込先
  body.appendParagraph("");
  body.appendParagraph("振込先").setHeading(DocumentApp.ParagraphHeading.HEADING3);
  body.appendParagraph("金融機関名：＿＿＿＿銀行　支店名：＿＿＿＿支店").setFontSize(10);
  body.appendParagraph("口座種別：普通　口座番号：＿＿＿＿＿＿＿").setFontSize(10);
  body.appendParagraph("口座名義：ゴウドウガイシャアイズ").setFontSize(10);

  // フッター
  body.appendParagraph("");
  body.appendParagraph("上記の金額を正に領収いたしました。").setFontSize(9);
  body.appendParagraph("※ 本書は電子的に発行された領収書です。").setFontSize(8);

  doc.saveAndClose();

  // PDF変換して保存
  var file = DriveApp.getFileById(doc.getId());
  var pdf = file.getAs("application/pdf");
  var pdfFile;
  if (cfg.SELL_SHEET_PDF_FOLDER_ID) {
    pdfFile = DriveApp.getFolderById(cfg.SELL_SHEET_PDF_FOLDER_ID).createFile(pdf).setName("領収書_" + orderId + ".pdf");
  } else {
    pdfFile = DriveApp.createFile(pdf).setName("領収書_" + orderId + ".pdf");
  }
  file.setTrashed(true); // 中間のドキュメントは破棄
  return pdfFile;
}

/**
 * 受付番号から注文を検索し、領収書PDFを生成して URL を返す。
 * @param {string} orderId 受付番号（OD-xxxx / SL-xxxx）
 * @return {object} { ok, url, error }
 */
function issueReceipt_(orderId) {
  var cfg = getConfig();
  var ss = openBook_();
  var sheetNames = [cfg.SHEET_ORDERS, cfg.SHEET_SELL];
  var data = null;

  for (var i = 0; i < sheetNames.length; i++) {
    var sh = ss.getSheetByName(sheetNames[i]);
    if (!sh) continue;
    var v = sh.getDataRange().getValues();
    for (var r = 1; r < v.length; r++) {
      if (String(v[r][1]) === orderId) {
        if (sheetNames[i] === cfg.SHEET_ORDERS) {
          // オーダー管理: 日時,ID,ステータス,名前,メール,プラン,車両,予算,...,合計
          data = {
            name: v[r][3],
            car: v[r][6],
            unitPrice: num_(v[r][11]) || num_(v[r][7]), // total or bid
            date: new Date().toLocaleDateString("ja-JP")
          };
        } else {
          // 出品管理: 日時,ID,ステータス,名前,メール,車両,想定額,...,合計
          data = {
            name: v[r][3],
            car: v[r][5],
            unitPrice: num_(v[r][9]) || num_(v[r][6]), // total or median
            date: new Date().toLocaleDateString("ja-JP")
          };
        }
        break;
      }
    }
    if (data) break;
  }

  if (!data) return { ok: false, error: orderId + " が見つかりません" };

  try {
    var pdfFile = makeReceiptPdf_(orderId, data);
    return { ok: true, url: pdfFile.getUrl() };
  } catch (e) {
    return { ok: false, error: "PDF生成失敗：" + String(e) };
  }
}
