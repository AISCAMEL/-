/**
 * CarmelDocs.gs — カーメル書面作成システム 受け口（バックエンドの「仕組み」）
 * =============================================================================
 * site/carmel-documents.html の「☁ クラウド保存」から送られてくる書面データ
 * （JSON, type:"cmldoc"）を Google スプレッドシートに記録し、スタッフへLINE/Slack
 * 通知します。9種すべての書面を 1 枚のシートで扱えるよう、明細は JSON 列に保持します。
 *
 * 【あとから組み込む手順】────────────────────────────────────────────
 *  1) このファイル（CarmelDocs.gs）を GAS プロジェクトに追加（同プロジェクトに
 *     WebApp.gs / Config.gs / Line.gs がある前提。単体運用も下部の注記参照）。
 *  2) WebApp.gs の doPost 内 switch に次の1行を追加して「組み込み」ます：
 *
 *         case "cmldoc":  result = handleCmlDoc_(data);  break;
 *
 *     ※ すでに追加済みの場合はそのままでOK（このリポジトリでは追加済み）。
 *  3) GAS を「ウェブアプリ」としてデプロイ（実行:自分／アクセス:全員）。
 *  4) 表示された /exec URL を carmel-documents.html の CLOUD.ENDPOINT に貼付。
 *  5) 事前に一度 cmlSetup() を実行するとシートの見出しが作成されます（任意）。
 * ---------------------------------------------------------------------------
 * 依存（WebApp.gs / Setup.gs / Config.gs / Line.gs にある共通関数）：
 *   getConfig(), openBook_(), ensureSheet_(), nextSeq_(), notifyStaff_()
 *   → これらが無い単体プロジェクトでも動くよう、末尾にフォールバックを用意。
 * =============================================================================
 */

/** 書面記録シートの見出し（固定） */
var CML_HEADERS = [
  "受信日時", "書面ID", "書面タイプ", "書面種別コード",
  "お客様氏名", "書類番号", "担当者", "作成日", "端末保存日時",
  "作成者(ログイン)", "MF連携", "MF契約ID",
  "明細(JSON)"
];

/** 書面種別コード → 通知用アイコン（表示のみ） */
var CML_ICONS = {
  d1: "📋", d2: "💴", d3: "🆘", d4: "🔁", d5: "✒️",
  d6: "📑", d7: "📜", d8: "🔑", d9: "🔍"
};

/**
 * 書面データを1件記録し、スタッフへ通知する。
 * @param {Object} d フロントから届く payload（buildPayload の戻り）
 * @return {Object} { ok, id }
 */
function handleCmlDoc_(d) {
  var cfg = getConfig();
  var sheetName = cfg.SHEET_CML_DOCS || "カーメル書面";

  var ss = openBook_();
  var sh = ss.getSheetByName(sheetName) || ensureSheet_(ss, sheetName, CML_HEADERS);
  // 既存シートに見出しが無い場合の保険
  if (sh.getLastRow() === 0) ensureSheet_(ss, sheetName, CML_HEADERS);

  var id = "CML-" + nextSeq_(sh, 1000);
  var fields = d.fields || {};

  // マネーフォワード クラウド契約 連携（MoneyForward.gs があり、有効時のみ実行）
  var mfStatus = "—", mfId = "";
  if (typeof mfForwardIfEligible_ === "function") {
    var mfr = mfForwardIfEligible_(d);
    if (mfr && mfr.skipped) { mfStatus = "—"; }
    else if (mfr && mfr.ok) { mfStatus = "連携済"; mfId = mfr.contractId || ""; }
    else if (mfr) { mfStatus = "失敗"; mfId = (mfr.error || "").slice(0, 120); }
  }

  sh.appendRow([
    new Date(),
    id,
    d.doctypeName || "",
    d.doctype || "",
    d.customer || "",
    d.docNo || "",
    d.staff || "",
    d.date || "",
    d.savedAt || "",
    d.operator || "",
    mfStatus,
    mfId,
    JSON.stringify(fields)
  ]);

  var icon = CML_ICONS[d.doctype] || "📄";
  notifyStaff_(
    icon + " 書面を受信 " + id + "\n" +
    "種別：" + (d.doctypeName || d.doctype || "-") + "\n" +
    "お客様：" + (d.customer || "-") + " 様\n" +
    "書類番号：" + (d.docNo || "-") + "\n" +
    "担当：" + (d.staff || "-") + " ／ 作成日：" + (d.date || "-")
  );

  return { ok: true, id: id };
}

/**
 * 初回セットアップ：記録シートを作成（見出し付き）。
 * GASエディタで直接実行してください（任意）。
 */
function cmlSetup() {
  var cfg = getConfig();
  var sheetName = cfg.SHEET_CML_DOCS || "カーメル書面";
  var ss = openBook_();
  ensureSheet_(ss, sheetName, CML_HEADERS);
  Logger.log("✅ シート「" + sheetName + "」を用意しました：" + ss.getUrl());
}

/**
 * 通知テスト（サンプルデータで1件記録＋通知）。
 */
function cmlTest() {
  var res = handleCmlDoc_({
    type: "cmldoc",
    doctype: "d1",
    doctypeName: "車両購入意向確認書",
    docNo: "CML-2026-TEST",
    customer: "テスト 太郎",
    staff: "動作確認",
    date: "2026-08-13",
    savedAt: new Date().toLocaleString("ja-JP"),
    fields: { d1_car: "トヨタ アルファード", d1_pricemax: "3500000" }
  });
  Logger.log("cmlTest → " + JSON.stringify(res));
}

/* ===========================================================================
 * フォールバック（単体プロジェクトで WebApp.gs 等が無い場合のみ有効化）
 * ---------------------------------------------------------------------------
 * 既存の AUC-AGENT プロジェクトに同居させる場合は、これらは定義済みのため
 * この節を「有効化」しないでください（重複定義エラーになります）。
 * 単体で使う場合は下の各コメントを外し、doGet/doPost を有効にしてください。
 * ===========================================================================
 *
 * function getConfig(){ return { SPREADSHEET_ID:"", SHEET_CML_DOCS:"カーメル書面",
 *   LINE_CHANNEL_ACCESS_TOKEN:"", LINE_STAFF_IDS:"" }; }
 *
 * function openBook_(){ var c=getConfig();
 *   if(c.SPREADSHEET_ID) return SpreadsheetApp.openById(c.SPREADSHEET_ID);
 *   var ss=SpreadsheetApp.create("カーメル書面DB");
 *   Logger.log("SPREADSHEET_ID: "+ss.getId()); return ss; }
 *
 * function ensureSheet_(ss,name,headers){ var sh=ss.getSheetByName(name)||ss.insertSheet(name);
 *   if(sh.getLastRow()===0){ sh.getRange(1,1,1,headers.length).setValues([headers])
 *     .setFontWeight("bold").setBackground("#0e1b33").setFontColor("#fff"); sh.setFrozenRows(1); }
 *   return sh; }
 *
 * function nextSeq_(sh,base){ return base + Math.max(0, sh.getLastRow()-1) + 1; }
 *
 * function notifyStaff_(msg){ var c=getConfig();
 *   if(!c.LINE_CHANNEL_ACCESS_TOKEN||!c.LINE_STAFF_IDS) return;
 *   String(c.LINE_STAFF_IDS).split(",").map(function(s){return s.trim();}).filter(String)
 *     .forEach(function(to){ UrlFetchApp.fetch("https://api.line.me/v2/bot/message/push",{
 *       method:"post", contentType:"application/json",
 *       headers:{Authorization:"Bearer "+c.LINE_CHANNEL_ACCESS_TOKEN},
 *       payload:JSON.stringify({to:to,messages:[{type:"text",text:msg}]}),
 *       muteHttpExceptions:true }); }); }
 *
 * function doPost(e){ try{ var d=JSON.parse((e&&e.postData&&e.postData.contents)||"{}");
 *   if(d.type==="cmldoc") return ContentService.createTextOutput(JSON.stringify(handleCmlDoc_(d)))
 *     .setMimeType(ContentService.MimeType.JSON);
 *   return ContentService.createTextOutput(JSON.stringify({ok:false,error:"unknown type"}))
 *     .setMimeType(ContentService.MimeType.JSON);
 *   }catch(err){ return ContentService.createTextOutput(JSON.stringify({ok:false,error:String(err)}))
 *     .setMimeType(ContentService.MimeType.JSON); } }
 *
 * function doGet(){ return ContentService.createTextOutput("Carmel Docs Web App is running.")
 *   .setMimeType(ContentService.MimeType.TEXT); }
 */
