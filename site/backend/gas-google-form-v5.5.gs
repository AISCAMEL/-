// ============================================================
// CARMEL バズ自動化フロー GAS v5.5（サブタスク削除版）
// Google Form → GAS → Spreadsheet/Drive → Asana → Netlify LP
// 2026/06/01 更新：列構成修正（A=登録日時、B=UID、C=名前、D=メール）
// 2026/07 変更：Asanaサブタスク（①〜⑨）の自動生成を削除
//   ・削除箇所：doPost内のサブタスク作成呼び出し
//   ・削除関数：createAsanaSubtasks_ / testSubtasksFull
//   ・削除定数：FINANCE_COMPANIES（サブタスク⑤専用だったため）
//   ・親タスク（createAsanaTask_）はそのまま作成します。
// ============================================================

// ===== フィールド名マッピング =====
var FIELD_NAME_MAP = {
  "お名前（フルネーム）":  "name",
  "電話番号":              "phone",
  "メールアドレス":        "email",
  "ご希望の連絡時間":      "contact_time",
  "お住まいの都道府県":    "prefecture",
  "ご希望の車種":          "car",
  "年式":                  "year",
  "走行距離":              "mileage",
  "ご希望のカラー":        "color",
  "車両価格":              "price",
  "車検":                  "inspection",
  "担当スタッフ":          "staff",
  "審査金利":              "rate",
  "支払い回数":            "months",
  "車両サイズ":            "size",
  "支店":                  "branch",
  "画像URL":               "img",
  "備考":                  "notes_extra"
};

// ===== ファイルフィールド =====
var FILE_FIELDS = [
  "免許証（表）",
  "免許証（裏）",
  "保険証",
  "マイナンバーカード",
  "収入証明書"
];

// ===== LP ベースURL =====
var LP_BASE_URL = "https://venerable-fudge-3ec612.netlify.app/";

// ===== 商談予約シートID =====
var SHOUDANDB_SHEET_ID = "1hw9oPxCGLUzKuOxR02tRoOf6D_sFd1M3h4B7w6x0aUY";

// ============================================================
// doGet
// ============================================================
function doGet(e) {
  return ContentService
    .createTextOutput(JSON.stringify({ status: "ok", message: "CARMEL GAS v5.5 稼働中" }))
    .setMimeType(ContentService.MimeType.JSON);
}

// ============================================================
// doPost：メイン処理
// ============================================================
function doPost(e) {
  try {
    var cfg = getConfig_();
    validateConfig_(cfg);

    var params = e.parameter || {};
    if (params.secret !== cfg.SECRET_KEY) {
      return jsonResponse_({ status: "error", message: "認証エラー" });
    }

    var formData = JSON.parse(params.data || "{}");
    var uid      = params.uid || generateUid_();

    // 重複チェック
    if (checkDuplicate_(cfg, uid)) {
      return jsonResponse_({ status: "skip", message: "重複送信をスキップしました" });
    }

    // スプレッドシートへ保存
    var sheet = getOrCreateSheet_(cfg);
    ensureHeaderOnceAndExpand_(sheet, formData);
    writeToSheet_(sheet, formData, uid);

    // Googleドライブ：顧客フォルダ作成
    var customerFolder = createCustomerFolder_(cfg, formData, uid);

    // 添付ファイル保存
    saveAllFiles_(cfg, formData, customerFolder);

    // Asanaタスク作成（親タスクのみ。サブタスクは作成しません）
    var taskId = createAsanaTask_(cfg, formData, uid, customerFolder);

    // ★UID自動紐付け（商談予約シートへ）
    linkUidToCarmelSheet_(uid, formData);

    // LP URL生成
    var lpUrl = generateLpUrl_(formData);

    // メール通知
    sendEmailNotification_(cfg, formData, uid, lpUrl, taskId);

    return jsonResponse_({ status: "ok", uid: uid, taskId: taskId, lpUrl: lpUrl });

  } catch (err) {
    Logger.log("doPost エラー: " + err.message);
    return jsonResponse_({ status: "error", message: err.message });
  }
}

// ============================================================
// 設定取得
// ============================================================
function getConfig_() {
  var props = PropertiesService.getScriptProperties().getProperties();
  return {
    ASANA_TOKEN:            props["ASANA_TOKEN"]            || "",
    ASANA_PROJECT_ID:       props["ASANA_PROJECT_ID"]       || "",
    ASANA_SECTION_ID:       props["ASANA_SECTION_ID"]       || "",
    ASANA_DEFAULT_ASSIGNEE: props["ASANA_DEFAULT_ASSIGNEE"] || "",
    CARMEL_SHEET_ID:        props["CARMEL_SHEET_ID"]        || "",
    DRIVE_FOLDER_ID:        props["DRIVE_FOLDER_ID"]        || "",
    SECRET_KEY:             props["SECRET_KEY"]             || "",
    NOTIFY_EMAIL:           props["NOTIFY_EMAIL"]           || "",
    TASK_DEADLINE_DAYS:     parseInt(props["TASK_DEADLINE_DAYS"] || "3", 10)
  };
}

// ============================================================
// 設定検証
// ============================================================
function validateConfig_(cfg) {
  Logger.log("TOKEN: "           + (cfg.ASANA_TOKEN ? "設定済み" : "❌ 未設定"));
  Logger.log("PROJECT_ID: "      + cfg.ASANA_PROJECT_ID);
  Logger.log("SECTION_ID: "      + cfg.ASANA_SECTION_ID);
  Logger.log("CARMEL_SHEET_ID: " + cfg.CARMEL_SHEET_ID);
  if (!cfg.ASANA_TOKEN)      throw new Error("ASANA_TOKEN が未設定です");
  if (!cfg.ASANA_PROJECT_ID) throw new Error("ASANA_PROJECT_ID が未設定です");
}

// ============================================================
// ★UID自動紐付け（商談予約シート「予約管理」へ）
// A列=登録日時 / B列=LINE_UID / C列=名前 / D列=メール
// ============================================================
function linkUidToCarmelSheet_(uid, formData) {
  try {
    var ss    = SpreadsheetApp.openById(SHOUDANDB_SHEET_ID);
    var sheet = ss.getSheetByName("予約管理");
    if (!sheet) {
      Logger.log("⚠️ 予約管理シート未発見 - スキップ");
      return;
    }

    var name    = formData["name"]  || formData["お名前（フルネーム）"] || "";
    var email   = formData["email"] || formData["メールアドレス"]       || "";
    var now     = Utilities.formatDate(new Date(), "Asia/Tokyo", "yyyy/MM/dd HH:mm:ss");
    var lastRow = sheet.getLastRow();
    var matched = false;

    if (lastRow >= 2) {
      var data = sheet.getRange(2, 1, lastRow - 1, 4).getValues();
      for (var i = 0; i < data.length; i++) {
        var rowUid   = data[i][1]; // B列 LINE_UID
        var rowEmail = data[i][3]; // D列 メール

        // メール一致 かつ UID空 → UID・名前を補完
        if (rowEmail === email && (!rowUid || rowUid === "未取得")) {
          sheet.getRange(i + 2, 2).setValue(uid);  // B列 UID
          sheet.getRange(i + 2, 3).setValue(name); // C列 名前
          Logger.log("✅ UID紐付け完了: " + email + " → " + uid);
          matched = true;
        }

        // UID一致 かつ メール空 → 名前・メールを補完
        if (rowUid === uid && (!rowEmail || rowEmail === "")) {
          sheet.getRange(i + 2, 3).setValue(name);  // C列 名前
          sheet.getRange(i + 2, 4).setValue(email); // D列 メール
          Logger.log("✅ メール補完完了: " + uid + " → " + email);
          matched = true;
        }
      }
    }

    if (!matched) {
      // 一致なし → 新規仮登録
      sheet.appendRow([now, uid, name, email, "", "", "", "", "", "仮登録"]);
      Logger.log("✅ 新規仮登録: " + name + " / " + email);
    }

  } catch(e) {
    Logger.log("❌ UID紐付けエラー: " + e.toString());
  }
}

// ============================================================
// Asanaタスク作成（親タスクのみ）
// ============================================================
function createAsanaTask_(cfg, formData, uid, customerFolder) {
  var name     = formData["name"]  || formData["お名前（フルネーム）"] || "名前未設定";
  var phone    = formData["phone"] || formData["電話番号"]             || "";
  var driveUrl = customerFolder ? customerFolder.getUrl() : "";
  var notes = [
    "【顧客名】"   + name,
    "【電話番号】" + phone,
    "【UID】"      + uid,
    "【受付日時】" + new Date().toLocaleString('ja-JP'),
    "",
    "Googleドライブ：",
    driveUrl,
    "",
    "━━━━━━━━━━━━━━━━━━",
    "フォーム入力内容",
    "━━━━━━━━━━━━━━━━━━"
  ].join("\n");

  Object.keys(formData).forEach(function(key) {
    if (FILE_FIELDS.indexOf(key) === -1) {
      notes += "\n【" + key + "】" + formData[key];
    }
  });

  var payload = {
    data: {
      name:        "【新規】" + name + "　様",
      notes:       notes,
      projects:    [cfg.ASANA_PROJECT_ID],
      memberships: [{ project: cfg.ASANA_PROJECT_ID, section: cfg.ASANA_SECTION_ID }]
    }
  };

  if (cfg.ASANA_DEFAULT_ASSIGNEE) {
    payload.data.assignee = cfg.ASANA_DEFAULT_ASSIGNEE;
  }

  var res  = UrlFetchApp.fetch("https://app.asana.com/api/1.0/tasks", {
    method:             "post",
    headers: {
      "Authorization": "Bearer " + cfg.ASANA_TOKEN,
      "Content-Type":  "application/json"
    },
    payload:            JSON.stringify(payload),
    muteHttpExceptions: true
  });

  var code = res.getResponseCode();
  var body = JSON.parse(res.getContentText());

  if (code === 201) {
    Logger.log("✅ Asanaタスク作成成功: " + body.data.gid);
    return body.data.gid;
  } else {
    Logger.log("❌ Asanaタスク作成失敗: " + res.getContentText());
    return null;
  }
}

// ============================================================
// LP URL生成
// ============================================================
function generateLpUrl_(formData) {
  var params = [];
  var map = {
    name:      formData["name"]      || formData["お名前（フルネーム）"] || "",
    company:   formData["company"]   || "",
    rate:      formData["rate"]      || formData["審査金利"]              || "",
    condition: formData["condition"] || "",
    limit:     formData["limit"]     || "",
    amount:    formData["amount"]    || "",
    months:    formData["months"]    || formData["支払い回数"]            || "",
    staff:     formData["staff"]     || formData["担当スタッフ"]          || "",
    date:      formData["date"]      || "",
    expiry:    formData["expiry"]    || "",
    size:      formData["size"]      || formData["車両サイズ"]            || "",
    branch:    formData["branch"]    || formData["支店"]                  || ""
  };

  Object.keys(map).forEach(function(key) {
    if (map[key]) {
      params.push(encodeURIComponent(key) + "=" + encodeURIComponent(map[key]));
    }
  });

  return LP_BASE_URL + (params.length ? "?" + params.join("&") : "");
}

// ============================================================
// メール通知
// ============================================================
function sendEmailNotification_(cfg, formData, uid, lpUrl, taskId) {
  if (!cfg.NOTIFY_EMAIL) return;
  var name     = formData["name"]  || formData["お名前（フルネーム）"] || "名前未設定";
  var phone    = formData["phone"] || formData["電話番号"]             || "";
  var asanaUrl = taskId
    ? "https://app.asana.com/0/" + cfg.ASANA_PROJECT_ID + "/" + taskId
    : "（タスク作成失敗）";

  var subject = "【CARMEL新規申込】" + name + "　様";
  var body = [
    "新規お申込みがありました。",
    "",
    "━━━━━━━━━━━━━━━",
    "顧客名：" + name,
    "電話番号：" + phone,
    "UID：" + uid,
    "受付日時：" + new Date().toLocaleString('ja-JP'),
    "━━━━━━━━━━━━━━━",
    "",
    "AsanaタスクURL：",
    asanaUrl,
    "",
    "LP URL：",
    lpUrl
  ].join("\n");

  GmailApp.sendEmail(cfg.NOTIFY_EMAIL, subject, body);
  Logger.log("✅ メール送信完了: " + cfg.NOTIFY_EMAIL);
}

// ============================================================
// スプレッドシート操作
// ============================================================
function getOrCreateSheet_(cfg) {
  var ss    = SpreadsheetApp.openById(cfg.CARMEL_SHEET_ID);
  var sheet = ss.getSheetByName("申込データ");
  if (!sheet) sheet = ss.insertSheet("申込データ");
  return sheet;
}

function ensureHeaderOnceAndExpand_(sheet, formData) {
  if (sheet.getLastRow() > 0) return;
  var headers = ["UID", "受付日時"].concat(Object.keys(formData));
  sheet.appendRow(headers);
  sheet.getRange(1, 1, 1, headers.length)
    .setBackground("#1a2e5a")
    .setFontColor("#ffffff")
    .setFontWeight("bold");
}

function writeToSheet_(sheet, formData, uid) {
  var row = [uid, new Date().toLocaleString('ja-JP')];
  Object.keys(formData).forEach(function(key) {
    row.push(formData[key] || "");
  });
  sheet.appendRow(row);
}

// ============================================================
// Googleドライブ：顧客フォルダ作成
// ============================================================
function createCustomerFolder_(cfg, formData, uid) {
  if (!cfg.DRIVE_FOLDER_ID) return null;
  try {
    var parent = DriveApp.getFolderById(cfg.DRIVE_FOLDER_ID);
    var name   = formData["name"] || formData["お名前（フルネーム）"] || "名前未設定";
    var folder = parent.createFolder(name + "　" + uid);
    Logger.log("✅ Driveフォルダ作成: " + folder.getUrl());
    return folder;
  } catch(e) {
    Logger.log("❌ Driveフォルダ作成失敗: " + e.message);
    return null;
  }
}

// ============================================================
// 添付ファイル保存
// ============================================================
function saveAllFiles_(cfg, formData, folder) {
  if (!folder) return;
  FILE_FIELDS.forEach(function(field) {
    var url = formData[field];
    if (url) saveFileToDrive_(url, field, folder);
  });
}

function saveFileToDrive_(fileUrl, fileName, folder) {
  try {
    var res  = UrlFetchApp.fetch(fileUrl, { muteHttpExceptions: true });
    var blob = res.getBlob().setName(sanitizeFileName_(fileName));
    folder.createFile(blob);
    Logger.log("✅ ファイル保存: " + fileName);
  } catch(e) {
    Logger.log("❌ ファイル保存失敗: " + fileName + " → " + e.message);
  }
}

// ============================================================
// 重複チェック
// ============================================================
function checkDuplicate_(cfg, uid) {
  try {
    var ss    = SpreadsheetApp.openById(cfg.CARMEL_SHEET_ID);
    var sheet = ss.getSheetByName("申込データ");
    if (!sheet || sheet.getLastRow() < 2) return false;
    var uids = sheet.getRange(2, 1, sheet.getLastRow() - 1, 1).getValues().flat();
    return uids.indexOf(uid) !== -1;
  } catch(e) {
    return false;
  }
}

// ============================================================
// UID生成
// ============================================================
function generateUid_() {
  return "CM-" + new Date().getTime() + "-" + Math.random().toString(36).slice(2, 7).toUpperCase();
}

// ============================================================
// ユーティリティ
// ============================================================
function formatDateOffset_(date, days) {
  var d = new Date(date);
  d.setDate(d.getDate() + (days || 0));
  return Utilities.formatDate(d, "Asia/Tokyo", "yyyy-MM-dd");
}

function sanitizeFileName_(name) {
  return name.replace(/[\\/:*?"<>|]/g, "_");
}

function jsonResponse_(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

// ============================================================
// テスト関数
// ============================================================
function testAsana() {
  var cfg = getConfig_();
  validateConfig_(cfg);
  var res = UrlFetchApp.fetch("https://app.asana.com/api/1.0/users/me", {
    headers:            { "Authorization": "Bearer " + cfg.ASANA_TOKEN },
    muteHttpExceptions: true
  });
  Logger.log("接続テスト: " + res.getResponseCode());
}

function testEmail() {
  var cfg = getConfig_();
  sendEmailNotification_(cfg, { "name": "テスト太郎", "phone": "090-0000-0000" }, "CM-TEST-001", LP_BASE_URL, "dummy_task_id");
}

function testGenerateUrl() {
  var url = generateLpUrl_({
    name:    "田中様",
    company: "セイブサポート",
    rate:    "15",
    months:  "60",
    staff:   "吉田",
    size:    "M",
    branch:  "大阪店"
  });
  Logger.log("LP URL: " + url);
}

// ★UID紐付けテスト
function testLinkUid() {
  linkUidToCarmelSheet_("CM-TEST-" + new Date().getTime(), {
    "name":  "テスト太郎",
    "email": "yoshidaippei39@gmail.com"
  });
  Logger.log("商談予約シートの予約管理を確認してください");
}
