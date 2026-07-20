/**
 * USS精算.gs — USS精算書メールの自動取込 → 加盟店シート転写 → 本部手数料加算 → 入金消し込み
 *
 * 【この1本でやること（全自動）】
 *   1. USSから届く精算書メールをGmailで自動検知（差出人・件名で絞り込み）
 *   2. パスワード付き添付（例: U3472）を外部API(PDF.co)で自動復号→テキスト化
 *   3. 中身を「USS精算_加盟店」シートへ転写
 *   4. 本部手数料（落札11,000＋振込550＝11,550円）を自動加算し加盟店請求額を計算
 *   5. 銀行CSVと突合して入金消し込み（runReconciliation）
 *
 * 【最初に1回だけ】
 *   - Config.gs の ⑦⑧（USS_*, PDFCO_API_KEY 等）を設定
 *   - setupUss() を実行（シート作成）
 *   - installUssTriggers() を実行（定期実行トリガー設置）
 *
 * 【パスワードについて】
 *   パスワードは Config.gs の USS_PDF_PASSWORD に保存され、毎回自動入力されます。
 *   人が手で開く必要はありません。ただしGAS単体では暗号PDFを開けないため、
 *   復号は PDF.co（USS_DECRYPT_MODE:"pdfco"）に委譲します。社外にファイルを
 *   出したくない場合は、USS管理画面からCSVをDLして USS_DECRYPT_MODE:"none" で運用してください。
 */

/* =========================================================================
 * セットアップ
 * ========================================================================= */

/** シート作成（1回だけ実行） */
function setupUss() {
  var cfg = getConfig();
  var ss = openBook_();

  ensureSheet_(ss, cfg.SHEET_USS, [
    "取込日時", "精算書メール日時", "メール件名",
    "出品番号", "会場", "車名", "落札価格",
    "本部手数料(落札)", "本部手数料(振込)", "本部手数料合計",
    "加盟店請求額", "入金状況", "消込日",
    "メッセージID", "行キー", "備考"
  ]);

  ensureSheet_(ss, cfg.SHEET_NYUKIN, [
    "取込日時", "入金日", "入金者名", "入金額",
    "マッチ行キー", "マッチ出品番号", "マッチ請求額", "差額",
    "消し込み状態", "CSVファイル", "備考"
  ]);

  SpreadsheetApp.flush();
  Logger.log("USSセットアップ完了：" + ss.getUrl());
}

/** 定期実行トリガーを設置（1回だけ実行） */
function installUssTriggers() {
  removeUssTriggers_();
  // 精算書メールの取込：15分ごと
  ScriptApp.newTrigger("runUssSettlement").timeBased().everyMinutes(15).create();
  // 入金消し込み：毎日 9時台
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
 * ① 精算書メールの取込（メイン）
 * ========================================================================= */

/**
 * 未処理のUSS精算書メールを探して取り込む。
 * トリガーからも手動からも実行可。
 */
function runUssSettlement() {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_USS) || (setupUss(), ss.getSheetByName(cfg.SHEET_USS));

  var label = getOrCreateLabel_(cfg.USS_DONE_LABEL);

  // 差出人・件名・未処理ラベルなし・添付ありで絞り込み
  var query = 'has:attachment newer_than:30d'
    + (cfg.USS_MAIL_FROM ? ' from:' + cfg.USS_MAIL_FROM : '')
    + (cfg.USS_MAIL_SUBJECT ? ' subject:' + cfg.USS_MAIL_SUBJECT : '')
    + ' -label:"' + cfg.USS_DONE_LABEL + '"';

  var threads = GmailApp.search(query, 0, 20);
  if (!threads.length) {
    Logger.log("新規のUSS精算書メールはありません。query=" + query);
    return;
  }

  var totalRows = 0, totalMsgs = 0;
  threads.forEach(function (th) {
    th.getMessages().forEach(function (msg) {
      try {
        var added = processSettlementMessage_(sh, cfg, msg);
        totalRows += added;
        totalMsgs++;
      } catch (err) {
        Logger.log("メール処理エラー(" + msg.getId() + "): " + err);
        notifyStaff_("⚠️ USS精算書の取込でエラー: " + err);
      }
    });
    th.addLabel(label); // スレッド単位で処理済みに
  });

  SpreadsheetApp.flush();
  if (totalRows > 0) {
    notifyStaff_("📄 USS精算書を取込みました：" + totalMsgs + "通 / " + totalRows + "明細（本部手数料11,550円加算済）");
  }
  Logger.log("取込完了：" + totalMsgs + "通 / " + totalRows + "明細");
}

/** 1通のメールを処理して、追加した明細行数を返す */
function processSettlementMessage_(sh, cfg, msg) {
  var attachments = msg.getAttachments();
  if (!attachments.length) return 0;

  var mailDate = msg.getDate();
  var subject = msg.getSubject();
  var msgId = msg.getId();

  var added = 0;
  attachments.forEach(function (att) {
    var name = att.getName() || "";
    var lower = name.toLowerCase();

    var text = "";
    if (lower.slice(-4) === ".csv") {
      // CSVはそのまま読む（方式C・パスワード不要）
      text = att.getDataAsString(detectCsvCharset_(att));
      var rows = parseUssCsv_(text);
      added += writeSettlementRows_(sh, cfg, rows, mailDate, subject, msgId);
    } else if (lower.slice(-4) === ".pdf") {
      // パスワード付きPDF → 復号してテキスト化
      if (cfg.USS_DECRYPT_MODE === "pdfco") {
        text = decryptPdfToText_(att.copyBlob(), cfg.USS_PDF_PASSWORD, cfg.PDFCO_API_KEY);
        var rowsP = parseUssText_(text);
        added += writeSettlementRows_(sh, cfg, rowsP, mailDate, subject, msgId);
      } else {
        Logger.log("PDF復号がOFF（USS_DECRYPT_MODE≠pdfco）のためスキップ: " + name);
      }
    } else {
      Logger.log("対象外の添付をスキップ: " + name);
    }
  });
  return added;
}

/* =========================================================================
 * ② パスワード付きPDFの復号（PDF.co / 方式A）
 * ========================================================================= */

/**
 * パスワード付きPDFを PDF.co で復号し、テキストを返す。
 *  1) base64アップロードで一時URLを得る
 *  2) convert/to/text にパスワードを渡してテキスト化（inline取得）
 */
function decryptPdfToText_(pdfBlob, password, apiKey) {
  if (!apiKey) throw new Error("PDFCO_API_KEY が未設定です（Config.gs ⑦）。");

  // 1) アップロード
  var upRes = UrlFetchApp.fetch("https://api.pdf.co/v1/file/upload/base64", {
    method: "post",
    contentType: "application/json",
    headers: { "x-api-key": apiKey },
    payload: JSON.stringify({
      name: (pdfBlob.getName() || "uss.pdf"),
      file: Utilities.base64Encode(pdfBlob.getBytes())
    }),
    muteHttpExceptions: true
  });
  var up = JSON.parse(upRes.getContentText() || "{}");
  if (up.error || !up.url) throw new Error("PDF.coアップロード失敗: " + (up.message || upRes.getContentText()));

  // 2) 復号＋テキスト化
  var cvRes = UrlFetchApp.fetch("https://api.pdf.co/v1/pdf/convert/to/text", {
    method: "post",
    contentType: "application/json",
    headers: { "x-api-key": apiKey },
    payload: JSON.stringify({
      url: up.url,
      password: password || "",
      inline: true // 結果テキストを body に直接返す
    }),
    muteHttpExceptions: true
  });
  var cv = JSON.parse(cvRes.getContentText() || "{}");
  if (cv.error) throw new Error("PDF.co変換失敗（パスワード誤り等）: " + (cv.message || cvRes.getContentText()));

  // inline:true のとき body にテキスト、そうでなければ url から取得
  if (cv.body) return cv.body;
  if (cv.url) return UrlFetchApp.fetch(cv.url, { muteHttpExceptions: true }).getContentText();
  throw new Error("PDF.coからテキストを取得できませんでした。");
}

/* =========================================================================
 * ③ パース（テキスト/CSV → 明細行）
 *   ※ USS精算書の実フォーマットに合わせて、下の抽出パターンを微調整してください。
 *      いまは「1明細=出品番号・車名・落札価格」を拾う汎用ロジックです。
 * ========================================================================= */

/** 復号後テキストから明細を抽出（要・実サンプルでの調整ポイント） */
function parseUssText_(text) {
  var rows = [];
  if (!text) return rows;
  var lines = String(text).split(/\r?\n/);

  // 例: 「出品番号 12345  トヨタ アクア  落札 850,000」のような行を拾う汎用パターン。
  //     実際の精算書に合わせて正規表現を調整すると精度が上がります。
  var reNo = /(?:出品|受付|車両|管理)?番?号?\s*[:：]?\s*([0-9]{4,})/;
  var reYen = /([0-9][0-9,]{2,})\s*円?/g;

  lines.forEach(function (line) {
    var s = line.trim();
    if (!s) return;
    var mNo = s.match(reNo);
    if (!mNo) return;

    // 行内の最大の金額を落札価格とみなす（汎用ヒューリスティック）
    var maxYen = 0, m;
    reYen.lastIndex = 0;
    while ((m = reYen.exec(s)) !== null) {
      var v = parseInt(m[1].replace(/,/g, ""), 10);
      if (v > maxYen) maxYen = v;
    }
    if (!maxYen) return;

    // 車名らしき部分（数字と記号を除いた語）を抽出
    var name = s.replace(mNo[0], " ").replace(/[0-9,円:：]/g, " ").replace(/\s+/g, " ").trim();

    rows.push({ lotNo: mNo[1], venue: "USS", carName: name, price: maxYen });
  });
  return rows;
}

/** CSV（方式C）から明細を抽出。ヘッダ名の揺れを吸収して拾う。 */
function parseUssCsv_(csvText) {
  var rows = [];
  var table = Utilities.parseCsv(csvText);
  if (!table || table.length < 2) return rows;

  var header = table[0].map(function (h) { return String(h).replace(/\s/g, ""); });
  var idx = function (cands) {
    for (var i = 0; i < header.length; i++) {
      for (var j = 0; j < cands.length; j++) {
        if (header[i].indexOf(cands[j]) >= 0) return i;
      }
    }
    return -1;
  };
  var iNo = idx(["出品番号", "受付番号", "車両番号", "管理番号"]);
  var iName = idx(["車名", "車種", "車両"]);
  var iVenue = idx(["会場", "会場名"]);
  var iPrice = idx(["落札価格", "成約金額", "落札金額", "金額"]);

  for (var r = 1; r < table.length; r++) {
    var row = table[r];
    if (!row || row.join("") === "") continue;
    var price = iPrice >= 0 ? parseInt(String(row[iPrice]).replace(/[^0-9]/g, ""), 10) : 0;
    rows.push({
      lotNo: iNo >= 0 ? String(row[iNo]).trim() : "",
      venue: iVenue >= 0 ? String(row[iVenue]).trim() : "USS",
      carName: iName >= 0 ? String(row[iName]).trim() : "",
      price: price || 0
    });
  }
  return rows;
}

/* =========================================================================
 * ④ 転写＋本部手数料の加算
 * ========================================================================= */

/**
 * 明細をシートに書き込む。本部手数料（落札＋振込）を自動加算。
 * 行キーで重複取込を防止（同じメール×出品番号は再登録しない）。
 */
function writeSettlementRows_(sh, cfg, rows, mailDate, subject, msgId) {
  if (!rows || !rows.length) return 0;

  var feeR = Number(cfg.USS_FEE_RAKUSATSU) || 0;
  var feeF = Number(cfg.USS_FEE_FURIKOMI) || 0;
  var feeTotal = feeR + feeF; // 11,550円

  var existing = getExistingRowKeys_(sh);
  var now = new Date();
  var out = [];

  rows.forEach(function (row, i) {
    var key = msgId + "#" + (row.lotNo || ("L" + i));
    if (existing[key]) return; // 重複スキップ

    var price = Number(row.price) || 0;
    var claim = price + feeTotal; // 加盟店請求額 ＝ 落札価格 ＋ 本部手数料合計

    out.push([
      now, mailDate, subject,
      row.lotNo || "", row.venue || "USS", row.carName || "", price,
      feeR, feeF, feeTotal,
      claim, "未入金", "",
      msgId, key, ""
    ]);
    existing[key] = true;
  });

  if (!out.length) return 0;
  sh.getRange(sh.getLastRow() + 1, 1, out.length, out[0].length).setValues(out);
  return out.length;
}

/** 既存の行キー集合を取得（重複防止用） */
function getExistingRowKeys_(sh) {
  var map = {};
  var last = sh.getLastRow();
  if (last < 2) return map;
  var headers = sh.getRange(1, 1, 1, sh.getLastColumn()).getValues()[0];
  var col = headers.indexOf("行キー") + 1;
  if (col < 1) return map;
  var keys = sh.getRange(2, col, last - 1, 1).getValues();
  keys.forEach(function (k) { if (k[0]) map[String(k[0])] = true; });
  return map;
}

/* =========================================================================
 * ⑤ 入金消し込み
 *   銀行の入出金明細CSVを読み、「未入金」の加盟店請求額に金額一致でマッチしたら消込。
 * ========================================================================= */

function runReconciliation() {
  var cfg = getConfig();
  var ss = openBook_();
  var shUss = ss.getSheetByName(cfg.SHEET_USS);
  var shPay = ss.getSheetByName(cfg.SHEET_NYUKIN);
  if (!shUss || !shPay) { setupUss(); shUss = ss.getSheetByName(cfg.SHEET_USS); shPay = ss.getSheetByName(cfg.SHEET_NYUKIN); }

  var deposits = readBankDeposits_(cfg); // [{date,name,amount,file}]
  if (!deposits.length) { Logger.log("入金CSVが見つかりません。"); return; }

  var tol = Number(cfg.USS_MATCH_TOLERANCE) || 0;
  var uss = loadUssUnpaid_(shUss); // 未入金の請求行
  var now = new Date();
  var matched = 0;
  var payOut = [];

  deposits.forEach(function (dep) {
    var hit = null;
    for (var i = 0; i < uss.length; i++) {
      if (uss[i].paid) continue;
      if (Math.abs(uss[i].claim - dep.amount) <= tol) { hit = uss[i]; break; }
    }
    if (hit) {
      hit.paid = true;
      // USSシート側を消込済に更新
      shUss.getRange(hit.rowIndex, hit.colStatus).setValue("消込済");
      shUss.getRange(hit.rowIndex, hit.colDate).setValue(dep.date || now);
      matched++;
      payOut.push([now, dep.date || "", dep.name || "", dep.amount,
        hit.key, hit.lotNo, hit.claim, dep.amount - hit.claim, "消込済", dep.file || "", ""]);
    } else {
      payOut.push([now, dep.date || "", dep.name || "", dep.amount,
        "", "", "", "", "未マッチ", dep.file || "", "要確認"]);
    }
  });

  if (payOut.length) {
    shPay.getRange(shPay.getLastRow() + 1, 1, payOut.length, payOut[0].length).setValues(payOut);
  }
  SpreadsheetApp.flush();
  notifyStaff_("💰 入金消し込み：" + matched + "/" + deposits.length + " 件を消込しました。");
  Logger.log("消し込み完了：" + matched + "/" + deposits.length);
}

/** USSシートから未入金行を読み込む */
function loadUssUnpaid_(sh) {
  var last = sh.getLastRow();
  var res = [];
  if (last < 2) return res;
  var headers = sh.getRange(1, 1, 1, sh.getLastColumn()).getValues()[0];
  var cClaim = headers.indexOf("加盟店請求額") + 1;
  var cStatus = headers.indexOf("入金状況") + 1;
  var cDate = headers.indexOf("消込日") + 1;
  var cLot = headers.indexOf("出品番号") + 1;
  var cKey = headers.indexOf("行キー") + 1;
  var data = sh.getRange(2, 1, last - 1, sh.getLastColumn()).getValues();
  data.forEach(function (row, i) {
    var status = String(row[cStatus - 1] || "");
    if (status === "消込済") return;
    res.push({
      rowIndex: i + 2,
      claim: Number(row[cClaim - 1]) || 0,
      lotNo: row[cLot - 1],
      key: row[cKey - 1],
      paid: false,
      colStatus: cStatus, colDate: cDate
    });
  });
  return res;
}

/** 銀行CSVフォルダから入金明細を読む（列名の揺れを吸収） */
function readBankDeposits_(cfg) {
  var folder = cfg.USS_BANK_CSV_FOLDER_ID
    ? DriveApp.getFolderById(cfg.USS_BANK_CSV_FOLDER_ID)
    : getOrCreateFolder_("USS入金CSV");
  var deposits = [];
  var files = folder.getFilesByType(MimeType.CSV);
  while (files.hasNext()) {
    var f = files.next();
    var text = f.getBlob().getDataAsString(detectCsvCharset_(f.getBlob()));
    var table = Utilities.parseCsv(text);
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
      var amt = iIn >= 0 ? parseInt(String(row[iIn]).replace(/[^0-9]/g, ""), 10) : 0;
      if (!amt) continue; // 出金行などスキップ
      deposits.push({
        date: iDate >= 0 ? row[iDate] : "",
        name: iName >= 0 ? String(row[iName]).trim() : "",
        amount: amt,
        file: f.getName()
      });
    }
  }
  return deposits;
}

/* =========================================================================
 * 補助関数
 * ========================================================================= */

function getOrCreateLabel_(name) {
  var label = GmailApp.getUserLabelByName(name);
  return label ? label : GmailApp.createLabel(name);
}

function getOrCreateFolder_(name) {
  var it = DriveApp.getFoldersByName(name);
  return it.hasNext() ? it.next() : DriveApp.createFolder(name);
}

/** CSVの文字コードを簡易判定（UTF-8で読めなければShift_JIS） */
function detectCsvCharset_(blob) {
  try {
    var t = blob.getDataAsString("UTF-8");
    // UTF-8として不正な置換文字が多い場合はSJISとみなす
    if (t.indexOf("�") >= 0) return "Shift_JIS";
    return "UTF-8";
  } catch (e) {
    return "Shift_JIS";
  }
}

/* =========================================================================
 * 動作確認用
 * ========================================================================= */

/** テキストパーサの動作確認（サンプル文字列で試す） */
function testParseUssText() {
  var sample = "出品番号 12345 トヨタ アクア 落札 850,000 円\n出品番号 22110 日産 ノート 720,000円";
  var rows = parseUssText_(sample);
  Logger.log(JSON.stringify(rows, null, 2));
}

/** 手数料計算の確認 */
function testFee() {
  var cfg = getConfig();
  Logger.log("本部手数料合計 = " + (cfg.USS_FEE_RAKUSATSU + cfg.USS_FEE_FURIKOMI) + " 円");
}
