/**
 * USS精算.gs — USS精算書メールの自動取込 → 明細転写 → 本部手数料自動計算
 *              → 加盟店向け請求書PDF自動生成 → 入金消し込み（システム管理）
 *
 * 【全体の流れ（自動）】
 *   1. USSから届く精算書メールをGmailで自動検知
 *   2. パスワード付き添付（例:U3472）をPDF.coで自動復号→テキスト化（方式A）
 *      ※CSV運用（方式C）なら復号不要
 *   3. 「USS精算_明細」シートへ1台ずつ転写（成約金額・USS各手数料など）
 *   4. スタッフが各行に「加盟店」と「仕入れ」を入力
 *        → 粗利＝成約金額−仕入れ／本部手数料＝固定11,550＋粗利×5% を自動計算
 *   5. generateInvoices() が「件名（オークション開催）×加盟店」ごとに
 *        請求書PDFを自動生成（合同会社アイズ様式）＋「請求書控え」に記録
 *   6. runReconciliation() が銀行CSVと突合して入金消し込み
 *
 * 【最初に1回だけ】 setupUss() → installUssTriggers()
 * 【設定】 Config.gs の ⑦⑧（USS_* / PDFCO_* / INV_* / 手数料 / 5%率）
 *
 * ■ 手数料の考え方（確定仕様）
 *   本部手数料合計 ＝ 落札11,000（固定）＋ 振込550（固定）＋ 粗利×5%
 *   粗利 ＝ 成約金額 − 仕入れ（仕入れは精算書に無いため明細で手入力）
 *   加盟店請求額 ＝ 本部手数料合計
 */

/* =========================================================================
 * 明細シートの列レイアウト（1始まり）
 * ========================================================================= */
var USS_COL = {
  TORIKOMI: 1,   // A 取込日時
  MAILDATE: 2,   // B 精算書メール日時
  KENMEI: 3,     // C 件名（オークション開催）
  PARTNER: 4,    // D 加盟店 ★入力
  LOTNO: 5,      // E 出品NO
  YEAR: 6,       // F 年式
  CARNAME: 7,    // G 車名
  BODYNO: 8,     // H 車体番号
  SEIYAKU: 9,    // I 成約金額
  SHIIRE: 10,    // J 仕入れ ★入力
  ARARI: 11,     // K 粗利（自動）
  USS_SHUPPIN: 12, // L USS出品料
  USS_SEIYAKU: 13, // M USS成約料
  USS_RAKUSATSU: 14, // N USS落札料
  USS_RECYCLE: 15, // O USSリサイクル料
  HONBU_RAKU: 16,  // P 本部_落札(固定)
  HONBU_FURI: 17,  // Q 本部_振込(固定)
  HONBU_5PCT: 18,  // R 本部_粗利5%（自動）
  HONBU_TOTAL: 19, // S 本部手数料合計（自動）
  SEIKYU: 20,      // T 加盟店請求額（自動）
  INVNO: 21,       // U 請求書NO
  NYUKIN: 22,      // V 入金状況
  KESHIKOMI: 23,   // W 消込日
  MSGID: 24,       // X メッセージID
  ROWKEY: 25,      // Y 行キー
  BIKO: 26         // Z 備考
};
var USS_LASTCOL = 26;

/* =========================================================================
 * セットアップ
 * ========================================================================= */

function setupUss() {
  var cfg = getConfig();
  var ss = openBook_();

  ensureSheet_(ss, cfg.SHEET_USS, [
    "取込日時", "精算書メール日時", "件名", "加盟店★入力",
    "出品NO", "年式", "車名", "車体番号",
    "成約金額", "仕入れ★入力", "粗利",
    "USS出品料", "USS成約料", "USS落札料", "USSリサイクル料",
    "本部_落札(固定)", "本部_振込(固定)", "本部_粗利5%", "本部手数料合計",
    "加盟店請求額", "請求書NO", "入金状況", "消込日",
    "メッセージID", "行キー", "備考"
  ]);

  ensureSheet_(ss, cfg.SHEET_NYUKIN, [
    "取込日時", "入金日", "入金者名", "入金額",
    "マッチ行キー", "マッチ出品NO", "マッチ請求額", "差額",
    "消し込み状態", "CSVファイル", "備考"
  ]);

  ensureSheet_(ss, cfg.SHEET_INVOICE_LOG, [
    "発行日", "請求書NO", "件名", "加盟店", "明細件数",
    "本部手数料合計", "請求合計", "PDF URL", "入金状況", "備考"
  ]);

  var pm = ensureSheet_(ss, cfg.SHEET_PARTNER, [
    "加盟店名", "〒", "住所", "敬称", "メール", "備考"
  ]);
  if (pm.getLastRow() === 1) {
    pm.appendRow(["フロックス", "266-0026", "", "御中", "", "サンプル。実際の住所を入力してください"]);
  }

  SpreadsheetApp.flush();
  Logger.log("USSセットアップ完了：" + ss.getUrl());
}

function installUssTriggers() {
  removeUssTriggers_();
  ScriptApp.newTrigger("runUssSettlement").timeBased().everyMinutes(15).create();
  ScriptApp.newTrigger("runReconciliation").timeBased().everyDays(1).atHour(9).create();
  Logger.log("トリガー設置完了（取込15分毎／消し込み毎日9時）");
}

function removeUssTriggers_() {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    var fn = t.getHandlerFunction();
    if (fn === "runUssSettlement" || fn === "runReconciliation") ScriptApp.deleteTrigger(t);
  });
}

/* =========================================================================
 * ① 精算書メールの取込
 * ========================================================================= */

function runUssSettlement() {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_USS);
  if (!sh) { setupUss(); sh = ss.getSheetByName(cfg.SHEET_USS); }

  var label = getOrCreateLabel_(cfg.USS_DONE_LABEL);
  var query = 'has:attachment newer_than:60d'
    + (cfg.USS_MAIL_FROM ? ' from:' + cfg.USS_MAIL_FROM : '')
    + (cfg.USS_MAIL_SUBJECT ? ' subject:' + cfg.USS_MAIL_SUBJECT : '')
    + ' -label:"' + cfg.USS_DONE_LABEL + '"';

  var threads = GmailApp.search(query, 0, 20);
  if (!threads.length) { Logger.log("新規のUSS精算書メールなし。query=" + query); return; }

  var totalRows = 0, totalMsgs = 0;
  threads.forEach(function (th) {
    th.getMessages().forEach(function (msg) {
      try {
        totalRows += processSettlementMessage_(sh, cfg, msg);
        totalMsgs++;
      } catch (err) {
        Logger.log("メール処理エラー(" + msg.getId() + "): " + err);
        notifyStaff_("⚠️ USS精算書の取込でエラー: " + err);
      }
    });
    th.addLabel(label);
  });

  SpreadsheetApp.flush();
  if (totalRows > 0) {
    notifyStaff_("📄 USS精算書を取込：" + totalMsgs + "通 / " + totalRows + "台\n"
      + "→ 明細シートで各行に「加盟店」「仕入れ」を入力してください（粗利5%が自動計算されます）。");
  }
  Logger.log("取込完了：" + totalMsgs + "通 / " + totalRows + "台");
}

function processSettlementMessage_(sh, cfg, msg) {
  var attachments = msg.getAttachments();
  if (!attachments.length) return 0;

  var mailDate = msg.getDate();
  var subject = msg.getSubject();
  var msgId = msg.getId();
  var kenmei = extractKenmei_(subject); // 件名（オークション開催）

  var added = 0;
  attachments.forEach(function (att) {
    var lower = (att.getName() || "").toLowerCase();
    var rows = [];
    if (lower.slice(-4) === ".csv") {
      rows = parseUssCsv_(att.getDataAsString(detectCsvCharset_(att)));
    } else if (lower.slice(-4) === ".pdf") {
      if (cfg.USS_DECRYPT_MODE === "pdfco") {
        var text = decryptPdfToText_(att.copyBlob(), cfg.USS_PDF_PASSWORD, cfg.PDFCO_API_KEY);
        rows = parseUssText_(text);
      } else {
        Logger.log("PDF復号OFFのためスキップ: " + att.getName());
      }
    }
    if (rows.length) added += writeSettlementRows_(sh, cfg, rows, mailDate, subject, kenmei, msgId);
  });
  return added;
}

/** 件名から「USS東京 09/08」等のオークション開催名を推定 */
function extractKenmei_(subject) {
  var s = String(subject || "").trim();
  var m = s.match(/(USS[^\s　:：]*[^\n]*)/);
  return m ? m[1].trim() : s;
}

/* =========================================================================
 * ② パスワード付きPDFの復号（PDF.co / 方式A）
 * ========================================================================= */

function decryptPdfToText_(pdfBlob, password, apiKey) {
  if (!apiKey) throw new Error("PDFCO_API_KEY が未設定です（Config.gs ⑦）。");

  var upRes = UrlFetchApp.fetch("https://api.pdf.co/v1/file/upload/base64", {
    method: "post", contentType: "application/json",
    headers: { "x-api-key": apiKey },
    payload: JSON.stringify({
      name: (pdfBlob.getName() || "uss.pdf"),
      file: Utilities.base64Encode(pdfBlob.getBytes())
    }),
    muteHttpExceptions: true
  });
  var up = JSON.parse(upRes.getContentText() || "{}");
  if (up.error || !up.url) throw new Error("PDF.coアップロード失敗: " + (up.message || upRes.getContentText()));

  var cvRes = UrlFetchApp.fetch("https://api.pdf.co/v1/pdf/convert/to/text", {
    method: "post", contentType: "application/json",
    headers: { "x-api-key": apiKey },
    payload: JSON.stringify({ url: up.url, password: password || "", inline: true }),
    muteHttpExceptions: true
  });
  var cv = JSON.parse(cvRes.getContentText() || "{}");
  if (cv.error) throw new Error("PDF.co変換失敗（パスワード誤り等）: " + (cv.message || cvRes.getContentText()));
  if (cv.body) return cv.body;
  if (cv.url) return UrlFetchApp.fetch(cv.url, { muteHttpExceptions: true }).getContentText();
  throw new Error("PDF.coからテキストを取得できませんでした。");
}

/* =========================================================================
 * ③ パース（テキスト/CSV → 明細）
 *   ※ 実サンプルに合わせて抽出パターンを微調整してください。
 * ========================================================================= */

function parseUssText_(text) {
  var rows = [];
  if (!text) return rows;
  String(text).split(/\r?\n/).forEach(function (line) {
    var s = line.trim();
    if (!s) return;
    var mNo = s.match(/(?:出品|受付|車両|管理)?番?号?\s*[:：]?\s*([0-9]{4,})/);
    if (!mNo) return;
    var maxYen = 0, m, re = /([0-9][0-9,]{2,})/g;
    while ((m = re.exec(s)) !== null) {
      var v = parseInt(m[1].replace(/,/g, ""), 10);
      if (v > maxYen) maxYen = v;
    }
    var name = s.replace(mNo[0], " ").replace(/[0-9,円:：]/g, " ").replace(/\s+/g, " ").trim();
    rows.push({ lotNo: mNo[1], year: "", carName: name, bodyNo: "", seiyaku: maxYen,
      ussShuppin: 0, ussSeiyaku: 0, ussRakusatsu: 0, ussRecycle: 0 });
  });
  return rows;
}

function parseUssCsv_(csvText) {
  var rows = [];
  var table = Utilities.parseCsv(csvText);
  if (!table || table.length < 2) return rows;
  var header = table[0].map(function (h) { return String(h).replace(/\s/g, ""); });
  var idx = function (cands) {
    for (var i = 0; i < header.length; i++)
      for (var j = 0; j < cands.length; j++)
        if (header[i].indexOf(cands[j]) >= 0) return i;
    return -1;
  };
  var iNo = idx(["出品NO", "出品番号", "受付番号", "管理番号"]);
  var iYear = idx(["年式"]);
  var iName = idx(["車名", "車種", "品名"]);
  var iBody = idx(["車体番号", "車台番号"]);
  var iSei = idx(["成約金額", "落札価格", "落札金額"]);
  var iSp = idx(["出品料"]);
  var iSr = idx(["成約料"]);
  var iRk = idx(["落札料"]);
  var iRe = idx(["リサイクル"]);
  var num = function (row, i) { return i >= 0 ? (parseInt(String(row[i]).replace(/[^0-9]/g, ""), 10) || 0) : 0; };

  for (var r = 1; r < table.length; r++) {
    var row = table[r];
    if (!row || row.join("") === "") continue;
    if (iNo >= 0 && !String(row[iNo]).trim()) continue;
    rows.push({
      lotNo: iNo >= 0 ? String(row[iNo]).trim() : "",
      year: iYear >= 0 ? String(row[iYear]).trim() : "",
      carName: iName >= 0 ? String(row[iName]).trim() : "",
      bodyNo: iBody >= 0 ? String(row[iBody]).trim() : "",
      seiyaku: num(row, iSei),
      ussShuppin: num(row, iSp), ussSeiyaku: num(row, iSr),
      ussRakusatsu: num(row, iRk), ussRecycle: num(row, iRe)
    });
  }
  return rows;
}

/* =========================================================================
 * ④ 転写＋本部手数料の自動計算（数式で仕入れ入力に追従）
 * ========================================================================= */

function writeSettlementRows_(sh, cfg, rows, mailDate, subject, kenmei, msgId) {
  if (!rows || !rows.length) return 0;
  var feeR = Number(cfg.USS_FEE_RAKUSATSU) || 0;
  var feeF = Number(cfg.USS_FEE_FURIKOMI) || 0;
  var rate = Number(cfg.USS_ROYALTY_RATE) || 0;

  var existing = getExistingRowKeys_(sh);
  var now = new Date();
  var startRow = sh.getLastRow() + 1;
  var values = [], formulas = [], n = 0;

  rows.forEach(function (row, i) {
    var key = msgId + "#" + (row.lotNo || ("L" + i));
    if (existing[key]) return;
    existing[key] = true;
    var r = startRow + n; // 実際の書き込み行
    // 値の行（数式列は仮に空文字→後で数式で上書き）
    values.push([
      now, mailDate, kenmei, "",              // 取込/メール日時/件名/加盟店(入力)
      row.lotNo || "", row.year || "", row.carName || "", row.bodyNo || "",
      row.seiyaku || 0, "", "",               // 成約金額/仕入れ(入力)/粗利(数式)
      row.ussShuppin || 0, row.ussSeiyaku || 0, row.ussRakusatsu || 0, row.ussRecycle || 0,
      feeR, feeF, "", "", "",                 // 本部落札/振込/粗利5%/合計/請求額(数式)
      "", "未請求", "",                        // 請求書NO/入金状況/消込日
      msgId, key, ""
    ]);
    // 数式（K粗利, R粗利5%, S合計, T請求額）
    formulas.push({
      row: r,
      K: '=IF($J' + r + '="","",$I' + r + '-$J' + r + ')',
      R: '=IF($J' + r + '="",0,ROUND(MAX($I' + r + '-$J' + r + ',0)*' + rate + ',0))',
      S: '=$P' + r + '+$Q' + r + '+$R' + r,
      T: '=$S' + r
    });
    n++;
  });

  if (!n) return 0;
  sh.getRange(startRow, 1, n, USS_LASTCOL).setValues(values);
  // 数式をまとめて設定
  formulas.forEach(function (f) {
    sh.getRange(f.row, USS_COL.ARARI).setFormula(f.K);
    sh.getRange(f.row, USS_COL.HONBU_5PCT).setFormula(f.R);
    sh.getRange(f.row, USS_COL.HONBU_TOTAL).setFormula(f.S);
    sh.getRange(f.row, USS_COL.SEIKYU).setFormula(f.T);
  });
  // 金額列の表示形式
  sh.getRange(startRow, USS_COL.SEIYAKU, n, USS_COL.SEIKYU - USS_COL.SEIYAKU + 1).setNumberFormat("#,##0");
  return n;
}

function getExistingRowKeys_(sh) {
  var map = {};
  var last = sh.getLastRow();
  if (last < 2) return map;
  var keys = sh.getRange(2, USS_COL.ROWKEY, last - 1, 1).getValues();
  keys.forEach(function (k) { if (k[0]) map[String(k[0])] = true; });
  return map;
}

/* =========================================================================
 * ⑤ 請求書の自動生成（件名×加盟店ごと・合同会社アイズ様式PDF）
 * ========================================================================= */

/**
 * 「加盟店」「仕入れ」が入力済みで未請求の明細を、件名×加盟店ごとに
 * まとめて請求書PDFを生成し、Driveへ保存。明細に請求書NOを刻印し控えを記録。
 */
function generateInvoices() {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_USS);
  var log = ss.getSheetByName(cfg.SHEET_INVOICE_LOG);
  if (!sh || !log) { setupUss(); sh = ss.getSheetByName(cfg.SHEET_USS); log = ss.getSheetByName(cfg.SHEET_INVOICE_LOG); }

  var last = sh.getLastRow();
  if (last < 2) { Logger.log("明細がありません。"); return; }
  var data = sh.getRange(2, 1, last - 1, USS_LASTCOL).getValues();

  // 準備完了行を件名×加盟店でグループ化
  var groups = {}; // key -> {kenmei,partner,rows:[{rowIndex,...}]}
  data.forEach(function (row, i) {
    var partner = String(row[USS_COL.PARTNER - 1] || "").trim();
    var shiire = row[USS_COL.SHIIRE - 1];
    var invNo = String(row[USS_COL.INVNO - 1] || "").trim();
    if (!partner) return;                 // 加盟店未入力
    if (shiire === "" || shiire === null) return; // 仕入れ未入力
    if (invNo) return;                    // 既に請求書発行済み
    var kenmei = String(row[USS_COL.KENMEI - 1] || "").trim();
    var gkey = kenmei + "｜" + partner;
    (groups[gkey] = groups[gkey] || { kenmei: kenmei, partner: partner, rows: [] })
      .rows.push({ rowIndex: i + 2, data: row });
  });

  var gkeys = Object.keys(groups);
  if (!gkeys.length) { Logger.log("請求可能な明細なし（加盟店・仕入れ入力済み／未請求）。"); return; }

  var folder = cfg.INV_PDF_FOLDER_ID ? DriveApp.getFolderById(cfg.INV_PDF_FOLDER_ID) : getOrCreateFolder_("USS請求書PDF");
  var made = 0;
  gkeys.forEach(function (gkey) {
    var g = groups[gkey];
    var invNo = nextInvoiceNo_(log);
    var result = buildInvoicePdf_(cfg, ss, g, invNo, folder);
    // 明細へ請求書NO刻印＋入金状況
    g.rows.forEach(function (rr) {
      sh.getRange(rr.rowIndex, USS_COL.INVNO).setValue(invNo);
      sh.getRange(rr.rowIndex, USS_COL.NYUKIN).setValue("未入金");
    });
    // 控え記録
    log.appendRow([new Date(), invNo, g.kenmei, g.partner, g.rows.length,
      result.honbuTotal, result.seikyuTotal, result.url, "未入金", ""]);
    made++;
  });

  SpreadsheetApp.flush();
  notifyStaff_("🧾 請求書を発行：" + made + "件（件名×加盟店単位）。控えシートとDriveに保存しました。");
  Logger.log("請求書発行：" + made + "件");
}

/** 請求書番号を採番（INV-YYYYMMDD-#### 連番） */
function nextInvoiceNo_(log) {
  var count = Math.max(0, log.getLastRow() - 1);
  var d = Utilities.formatDate(new Date(), "Asia/Tokyo", "yyyyMMdd");
  return "INV-" + d + "-" + ("000" + (count + 1)).slice(-4);
}

/**
 * 1グループ分の請求書をアイズ様式で「_請求書出力」シートに描画→PDF化して保存。
 * 戻り値 {url, honbuTotal, seikyuTotal}
 */
function buildInvoicePdf_(cfg, ss, g, invNo, folder) {
  var out = ss.getSheetByName("_請求書出力") || ss.insertSheet("_請求書出力");
  out.clear();
  out.getRange(1, 1, out.getMaxRows(), out.getMaxColumns()).setBackground("#ffffff").setBorder(false, false, false, false, false, false);
  try { out.hideSheet(); } catch (e) {}

  var partner = lookupPartner_(ss, cfg, g.partner);
  var today = Utilities.formatDate(new Date(), "Asia/Tokyo", "yyyy/MM/dd");

  // ヘッダ
  out.getRange("A1").setValue("〒 " + cfg.INV_ISSUER_ZIP);
  out.getRange("H1").setValue("No. " + invNo);
  out.getRange("H2").setValue("Date  " + today);
  out.getRange("A3").setValue((partner.name || g.partner) + "　" + (partner.title || "御中")).setFontSize(13).setFontWeight("bold");
  if (partner.zip) out.getRange("A4").setValue("〒" + partner.zip);
  if (partner.addr) out.getRange("A5").setValue(partner.addr);

  out.getRange("H4").setValue(cfg.INV_ISSUER_NAME).setFontWeight("bold");
  out.getRange("H5").setValue(cfg.INV_ISSUER_ADDR);
  out.getRange("H6").setValue("TEL：" + cfg.INV_ISSUER_TEL);
  out.getRange("H7").setValue("FAX：" + cfg.INV_ISSUER_FAX);

  out.getRange("A6").setValue("件名：" + g.kenmei).setFontWeight("bold");
  out.getRange("A7").setValue("以下の通りご請求します。");

  // 明細テーブル見出し（row 9,10）
  var head1 = ["出品NO", "年式", "品名 / 車体番号", "成約金額", "ご請求金額", "", "", "", "その他"];
  var head2 = ["", "", "", "（参考）", "出品料", "成約料", "落札料", "リサイクル料", "委託手数料"];
  out.getRange(9, 1, 1, head1.length).setValues([head1]).setFontWeight("bold").setBackground("#0e1b33").setFontColor("#ffffff").setHorizontalAlignment("center");
  out.getRange(10, 1, 1, head2.length).setValues([head2]).setFontWeight("bold").setBackground("#33465f").setFontColor("#ffffff").setHorizontalAlignment("center");

  // 明細行
  //  落札料列 = 本部固定11,000 ／ 委託手数料列 = 振込550 + 粗利5%
  var startRow = 11;
  var body = [];
  var sumSei = 0, sumShuppin = 0, sumSeiyakuFee = 0, sumRakusatsu = 0, sumRecycle = 0, sumItaku = 0;
  g.rows.forEach(function (rr) {
    var d = rr.data;
    var sei = Number(d[USS_COL.SEIYAKU - 1]) || 0;
    var ussSp = Number(d[USS_COL.USS_SHUPPIN - 1]) || 0;
    var ussSr = Number(d[USS_COL.USS_SEIYAKU - 1]) || 0;
    var raku = Number(cfg.USS_FEE_RAKUSATSU) || 0;                  // 本部固定：落札
    var pct5 = Number(d[USS_COL.HONBU_5PCT - 1]) || 0;              // 粗利5%
    var itaku = (Number(cfg.USS_FEE_FURIKOMI) || 0) + pct5;         // 委託手数料＝振込550＋粗利5%
    var ussRe = Number(d[USS_COL.USS_RECYCLE - 1]) || 0;
    var name = (d[USS_COL.CARNAME - 1] || "") + (d[USS_COL.BODYNO - 1] ? "／" + d[USS_COL.BODYNO - 1] : "");
    body.push([
      d[USS_COL.LOTNO - 1], d[USS_COL.YEAR - 1], name, sei,
      ussSp, ussSr, raku, ussRe, itaku
    ]);
    sumSei += sei; sumShuppin += ussSp; sumSeiyakuFee += ussSr;
    sumRakusatsu += raku; sumRecycle += ussRe; sumItaku += itaku;
  });
  if (body.length) {
    out.getRange(startRow, 1, body.length, 9).setValues(body);
  }

  // 合計行
  var totalRow = startRow + body.length + 1;
  out.getRange(totalRow, 1).setValue("各合計").setFontWeight("bold");
  out.getRange(totalRow, 4, 1, 6).setValues([[sumSei, sumShuppin, sumSeiyakuFee, sumRakusatsu, sumRecycle, sumItaku]]).setFontWeight("bold");

  // 請求合計（＝ご請求金額＋委託手数料。ここでは本部手数料合計に相当）
  var seikyuTotal = sumShuppin + sumSeiyakuFee + sumRakusatsu + sumRecycle + sumItaku;
  var honbuTotal = sumRakusatsu + sumItaku; // 本部手数料合計（落札固定＋振込＋粗利5%）
  var sumRow = totalRow + 1;
  out.getRange(sumRow, 1).setValue("ご請求金額 合計").setFontWeight("bold");
  out.getRange(sumRow, 8).setValue("合計").setFontWeight("bold").setHorizontalAlignment("right");
  out.getRange(sumRow, 9).setValue(seikyuTotal).setFontWeight("bold");

  // 振込先
  var bankRow = sumRow + 2;
  out.getRange(bankRow, 1).setValue("お振込先");
  out.getRange(bankRow, 3).setValue(cfg.INV_BANK);
  out.getRange(bankRow + 1, 3).setValue(cfg.INV_BANK_HOLDER);

  // 体裁
  out.getRange(9, 1, bankRow + 1, 9).setNumberFormats(
    buildNumberFormats_(9, bankRow + 1) // 金額列だけ #,##0
  );
  out.setColumnWidths(1, 9, 90);
  out.setColumnWidth(3, 220);
  SpreadsheetApp.flush();

  // PDF化（このシートのgidをrange指定でエクスポート）
  var url = exportSheetRangeToPdf_(ss, out, "A1:I" + (bankRow + 2), invNo + "_" + g.partner, folder);

  // 出力シートは残さない（毎回作り直し）
  try { ss.deleteSheet(out); } catch (e) {}
  return { url: url, honbuTotal: honbuTotal, seikyuTotal: seikyuTotal };
}

/** 金額列(D..I)のみ #,##0、他は @ の書式配列を作る */
function buildNumberFormats_(fromRow, toRow) {
  var formats = [];
  for (var r = fromRow; r <= toRow; r++) {
    formats.push(["@", "@", "@", "#,##0", "#,##0", "#,##0", "#,##0", "#,##0", "#,##0"]);
  }
  return formats;
}

/** 加盟店マスタから宛先情報を引く */
function lookupPartner_(ss, cfg, name) {
  var pm = ss.getSheetByName(cfg.SHEET_PARTNER);
  var res = { name: name, zip: "", addr: "", title: "御中" };
  if (!pm || pm.getLastRow() < 2) return res;
  var rows = pm.getRange(2, 1, pm.getLastRow() - 1, 4).getValues();
  for (var i = 0; i < rows.length; i++) {
    if (String(rows[i][0]).trim() === String(name).trim()) {
      res.name = rows[i][0]; res.zip = rows[i][1]; res.addr = rows[i][2];
      res.title = rows[i][3] || "御中";
      break;
    }
  }
  return res;
}

/** 指定シートの範囲をPDF化してフォルダへ保存し、URLを返す */
function exportSheetRangeToPdf_(ss, sheet, a1Range, fileBase, folder) {
  var ssId = ss.getId();
  var gid = sheet.getSheetId();
  var params = {
    format: "pdf", gid: String(gid), range: a1Range,
    size: "A4", portrait: "true", fitw: "true",
    gridlines: "false", printtitle: "false", sheetnames: "false",
    pagenumbers: "false", fzr: "false",
    top_margin: "0.5", bottom_margin: "0.5", left_margin: "0.5", right_margin: "0.5"
  };
  var qs = Object.keys(params).map(function (k) { return k + "=" + encodeURIComponent(params[k]); }).join("&");
  var url = "https://docs.google.com/spreadsheets/d/" + ssId + "/export?" + qs;
  var res = UrlFetchApp.fetch(url, {
    headers: { Authorization: "Bearer " + ScriptApp.getOAuthToken() },
    muteHttpExceptions: true
  });
  if (res.getResponseCode() !== 200) throw new Error("PDFエクスポート失敗: " + res.getResponseCode());
  var blob = res.getBlob().setName(fileBase.replace(/[\\\/:*?"<>|]/g, "_") + ".pdf");
  var file = folder.createFile(blob);
  return file.getUrl();
}

/* =========================================================================
 * ⑥ 入金消し込み
 * ========================================================================= */

function runReconciliation() {
  var cfg = getConfig();
  var ss = openBook_();
  var shUss = ss.getSheetByName(cfg.SHEET_USS);
  var shPay = ss.getSheetByName(cfg.SHEET_NYUKIN);
  var log = ss.getSheetByName(cfg.SHEET_INVOICE_LOG);
  if (!shUss || !shPay) { setupUss(); shUss = ss.getSheetByName(cfg.SHEET_USS); shPay = ss.getSheetByName(cfg.SHEET_NYUKIN); log = ss.getSheetByName(cfg.SHEET_INVOICE_LOG); }

  var deposits = readBankDeposits_(cfg);
  if (!deposits.length) { Logger.log("入金CSVが見つかりません。"); return; }

  var tol = Number(cfg.USS_MATCH_TOLERANCE) || 0;
  var uss = loadUssUnpaid_(shUss);
  var now = new Date();
  var matched = 0, payOut = [];

  deposits.forEach(function (dep) {
    var hit = null;
    for (var i = 0; i < uss.length; i++) {
      if (uss[i].paid) continue;
      if (Math.abs(uss[i].claim - dep.amount) <= tol) { hit = uss[i]; break; }
    }
    if (hit) {
      hit.paid = true;
      shUss.getRange(hit.rowIndex, USS_COL.NYUKIN).setValue("消込済");
      shUss.getRange(hit.rowIndex, USS_COL.KESHIKOMI).setValue(dep.date || now);
      if (log && hit.invNo) markInvoicePaid_(log, hit.invNo);
      matched++;
      payOut.push([now, dep.date || "", dep.name || "", dep.amount,
        hit.key, hit.lotNo, hit.claim, dep.amount - hit.claim, "消込済", dep.file || "", ""]);
    } else {
      payOut.push([now, dep.date || "", dep.name || "", dep.amount,
        "", "", "", "", "未マッチ", dep.file || "", "要確認"]);
    }
  });

  if (payOut.length) shPay.getRange(shPay.getLastRow() + 1, 1, payOut.length, payOut[0].length).setValues(payOut);
  SpreadsheetApp.flush();
  notifyStaff_("💰 入金消し込み：" + matched + "/" + deposits.length + " 件を消込しました。");
  Logger.log("消し込み完了：" + matched + "/" + deposits.length);
}

function loadUssUnpaid_(sh) {
  var last = sh.getLastRow(), res = [];
  if (last < 2) return res;
  var data = sh.getRange(2, 1, last - 1, USS_LASTCOL).getValues();
  data.forEach(function (row, i) {
    if (String(row[USS_COL.NYUKIN - 1] || "") === "消込済") return;
    res.push({
      rowIndex: i + 2,
      claim: Number(row[USS_COL.SEIKYU - 1]) || 0,
      lotNo: row[USS_COL.LOTNO - 1],
      key: row[USS_COL.ROWKEY - 1],
      invNo: row[USS_COL.INVNO - 1],
      paid: false
    });
  });
  return res;
}

function markInvoicePaid_(log, invNo) {
  var last = log.getLastRow();
  if (last < 2) return;
  var vals = log.getRange(2, 2, last - 1, 1).getValues(); // 請求書NO列
  for (var i = 0; i < vals.length; i++) {
    if (String(vals[i][0]) === String(invNo)) { log.getRange(i + 2, 9).setValue("入金済"); return; }
  }
}

function readBankDeposits_(cfg) {
  var folder = cfg.USS_BANK_CSV_FOLDER_ID ? DriveApp.getFolderById(cfg.USS_BANK_CSV_FOLDER_ID) : getOrCreateFolder_("USS入金CSV");
  var deposits = [];
  var files = folder.getFilesByType(MimeType.CSV);
  while (files.hasNext()) {
    var f = files.next();
    var table = Utilities.parseCsv(f.getBlob().getDataAsString(detectCsvCharset_(f.getBlob())));
    if (!table || table.length < 2) continue;
    var header = table[0].map(function (h) { return String(h).replace(/\s/g, ""); });
    var find = function (cands) {
      for (var i = 0; i < header.length; i++)
        for (var j = 0; j < cands.length; j++)
          if (header[i].indexOf(cands[j]) >= 0) return i;
      return -1;
    };
    var iDate = find(["日付", "取引日", "入金日"]);
    var iName = find(["振込人", "お名前", "摘要", "内容", "取引先"]);
    var iIn = find(["入金", "お預り", "入金額", "金額"]);
    for (var r = 1; r < table.length; r++) {
      var row = table[r];
      if (!row || row.join("") === "") continue;
      var amt = iIn >= 0 ? (parseInt(String(row[iIn]).replace(/[^0-9]/g, ""), 10) || 0) : 0;
      if (!amt) continue;
      deposits.push({ date: iDate >= 0 ? row[iDate] : "", name: iName >= 0 ? String(row[iName]).trim() : "", amount: amt, file: f.getName() });
    }
  }
  return deposits;
}

/* =========================================================================
 * 補助
 * ========================================================================= */

function getOrCreateLabel_(name) {
  var label = GmailApp.getUserLabelByName(name);
  return label ? label : GmailApp.createLabel(name);
}
function getOrCreateFolder_(name) {
  var it = DriveApp.getFoldersByName(name);
  return it.hasNext() ? it.next() : DriveApp.createFolder(name);
}
function detectCsvCharset_(blob) {
  try {
    var t = blob.getDataAsString("UTF-8");
    if (t.indexOf("�") >= 0) return "Shift_JIS";
    return "UTF-8";
  } catch (e) { return "Shift_JIS"; }
}

/* =========================================================================
 * 動作確認用
 * ========================================================================= */

function testFee() {
  var cfg = getConfig();
  var arari = 300000; // 例：粗利30万
  var pct5 = Math.round(Math.max(arari, 0) * cfg.USS_ROYALTY_RATE);
  Logger.log("固定＝" + (cfg.USS_FEE_RAKUSATSU + cfg.USS_FEE_FURIKOMI) + "円 ／ 粗利5%＝" + pct5
    + "円 ／ 本部手数料合計＝" + (cfg.USS_FEE_RAKUSATSU + cfg.USS_FEE_FURIKOMI + pct5) + "円");
}

function testParseUssText() {
  var sample = "出品番号 26677 三菱アウトランダー 成約 850,000 円\n出品番号 22110 日産ノート 720,000円";
  Logger.log(JSON.stringify(parseUssText_(sample), null, 2));
}
