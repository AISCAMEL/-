/**
 * WebApp.gs — LPからの送信を受け取る入口（Webアプリ）
 *
 * 【デプロイ手順（初回のみ）】
 * 1) 右上「デプロイ」→「新しいデプロイ」
 * 2) 種類：ウェブアプリ
 * 3) 実行ユーザー：自分　／　アクセスできるユーザー：全員
 * 4) デプロイ → 表示される「ウェブアプリのURL」をコピー
 * 5) site/assets/js/app.js の AucConfig.endpoint にそのURLを貼り付け
 *
 * LPの「会員登録」「かんたんオーダー」「お問い合わせ」がここに届き、
 * スプレッドシート登録 → AI要約 → LINE通知 まで自動で行います。
 */

// 動作確認用（ブラウザでURLを開くと表示される）
function doGet() {
  return ContentService
    .createTextOutput("AUC-AGENT Web App is running.")
    .setMimeType(ContentService.MimeType.TEXT);
}

// LPからのPOSTを受ける本体
function doPost(e) {
  try {
    var data = JSON.parse((e && e.postData && e.postData.contents) || "{}");
    var result;

    switch (data.type) {
      case "order":    result = handleOrder_(data);    break;
      case "register": result = handleRegister_(data); break;
      case "contact":  result = handleContact_(data);  break;
      default:         result = { ok: false, error: "unknown type" };
    }
    return json_(result);
  } catch (err) {
    return json_({ ok: false, error: String(err) });
  }
}

/* ---------- オーダー（かんたんオーダー） ---------- */
function handleOrder_(d) {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_ORDERS) || ensureSheet_(ss, cfg.SHEET_ORDERS, []);

  var id = "OD-" + nextSeq_(sh, 2050);
  var ai = aiSummarize_(d); // {summary, priority}

  sh.appendRow([
    new Date(), id, "受付",
    d.name || "", d.email || "", d.plan || "",
    d.car || "", num_(d.bid), d.clsLabel || d.cls || "", d.regionLabel || "",
    num_(d.fee), num_(d.total), d.optionsLabel || "",
    ai.summary, ai.priority, ""
  ]);

  notifyStaff_(
    "🚗 新規オーダー " + id + "\n" +
    "お名前：" + (d.name || "-") + "\n" +
    "希望車種：" + (d.car || "-") + "\n" +
    "予算：" + yen_(d.bid) + " / 想定総額：" + yen_(d.total) + "\n" +
    "AI優先度：" + ai.priority + "\n" +
    "要約：" + ai.summary
  );

  return { ok: true, id: id };
}

/* ---------- 会員登録 ---------- */
function handleRegister_(d) {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_MEMBERS) || ensureSheet_(ss, cfg.SHEET_MEMBERS, []);

  var id = "M-" + nextSeq_(sh, 1000);
  sh.appendRow([new Date(), id, d.name || "", d.email || "", d.plan || "", d.source || "LP"]);

  notifyStaff_("👤 新規会員登録 " + id + "\nお名前：" + (d.name || "-") + "\nメール：" + (d.email || "-") + "\n希望プラン：" + (d.plan || "-"));
  return { ok: true, id: id };
}

/* ---------- お問い合わせ ---------- */
function handleContact_(d) {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_CONTACTS) || ensureSheet_(ss, cfg.SHEET_CONTACTS, []);

  sh.appendRow([new Date(), d.name || "", d.email || "", d.phone || "", d.message || "", "未対応"]);
  notifyStaff_("✉️ お問い合わせ\nお名前：" + (d.name || "-") + "\n内容：" + (d.message || "-"));
  return { ok: true };
}

/* ---------- 共通ユーティリティ ---------- */
function nextSeq_(sh, base) {
  var rows = Math.max(0, sh.getLastRow() - 1); // 見出し除く
  return base + rows + 1;
}
function num_(v) { var n = Number(v); return isNaN(n) ? 0 : n; }
function yen_(v) { return "¥" + num_(v).toLocaleString("en-US"); }
function json_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
