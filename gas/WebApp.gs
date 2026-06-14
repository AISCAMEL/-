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
      case "sell":     result = handleSell_(data);     break;
      case "loan":     result = handleLoan_(data);     break;
      case "register": result = handleRegister_(data); break;
      case "contact":  result = handleContact_(data);  break;
      case "partner":  result = handlePartner_(data);  break;
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

/* ---------- 出品申込（出品代行） ---------- */
function handleSell_(d) {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_SELL) || ensureSheet_(ss, cfg.SHEET_SELL, []);

  var id = "SL-" + nextSeq_(sh, 3000);
  var ai = aiSummarize_(d);

  // デジタル出品票（PDF）を顧客情報から自動生成
  var pdfUrl = "";
  try { pdfUrl = makeSellSheetPdf_(id, d); } catch (e) { pdfUrl = "PDF生成失敗:" + e; }

  sh.appendRow([
    new Date(), id, "出品申込",
    d.name || "", d.email || "",
    d.car || "", num_(d.median), d.cond || "",
    num_(d.sellFee), num_(d.total), num_(d.carrierFee),
    pdfUrl, ai.summary, ai.priority, ""
  ]);

  notifyStaff_(
    "🏷️ 新規出品申込 " + id + "\n" +
    "お名前：" + (d.name || "-") + "\n" +
    "車両：" + (d.car || "-") + "\n" +
    "想定落札：" + yen_(d.median) + " / 手取り目安：" + yen_(d.total) + "\n" +
    "出品票PDF：" + (pdfUrl || "-") + "\n" +
    "AI優先度：" + ai.priority
  );
  return { ok: true, id: id, pdf: pdfUrl };
}

/* ---------- ローン申込（オリコ連携の起点） ---------- */
function handleLoan_(d) {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_LOAN) || ensureSheet_(ss, cfg.SHEET_LOAN, []);

  var id = "LN-" + nextSeq_(sh, 5000);
  // 簡易スコアリング（既存CarLoan_Systemの考え方を流用）
  var score = loanScore_(d);

  sh.appendRow([
    new Date(), id, "審査待ち",
    d.name || "", d.email || "", d.phone || "",
    num_(d.amount), num_(d.term), d.job || "", num_(d.income),
    score.score, score.grade, d.orderId || "", ""
  ]);

  notifyStaff_(
    "💳 ローン申込 " + id + "\n" +
    "お名前：" + (d.name || "-") + "\n" +
    "希望額：" + yen_(d.amount) + " / " + (d.term || "-") + "回\n" +
    "AI判定：" + score.grade + "（" + score.score + "点）\n" +
    "→ オリコ等へ審査依頼してください。"
  );
  return { ok: true, id: id, grade: score.grade };
}

/**
 * デジタル出品票（PDF）を Google ドキュメント経由で生成し、ドライブに保存。
 * 顧客が入力した車両情報をテンプレートに差し込む。
 */
function makeSellSheetPdf_(id, d) {
  var cfg = getConfig();
  var doc = DocumentApp.create("出品票_" + id);
  var body = doc.getBody();
  body.appendParagraph("デジタル出品票").setHeading(DocumentApp.ParagraphHeading.HEADING1);
  body.appendParagraph("管理番号：" + id + "　作成日：" + new Date().toLocaleDateString("ja-JP"));
  var t = body.appendTable([
    ["出品者", d.name || ""],
    ["車種・モデル", d.car || ""],
    ["想定落札価格", yen_(d.median)],
    ["車両状態", d.cond || ""],
    ["走行距離", d.mileage || "（申告）"],
    ["修復歴", d.repair || "（申告）"],
    ["装備", d.equip || "（申告）"],
    ["特記事項", d.note || ""]
  ]);
  doc.saveAndClose();

  var file = DriveApp.getFileById(doc.getId());
  var pdf = file.getAs("application/pdf");
  var pdfFile;
  if (cfg.SELL_SHEET_PDF_FOLDER_ID) {
    pdfFile = DriveApp.getFolderById(cfg.SELL_SHEET_PDF_FOLDER_ID).createFile(pdf).setName("出品票_" + id + ".pdf");
  } else {
    pdfFile = DriveApp.createFile(pdf).setName("出品票_" + id + ".pdf");
  }
  file.setTrashed(true); // 中間のドキュメントは破棄
  return pdfFile.getUrl();
}

/* 簡易ローンスコア（雇用形態・収入・返済負担率の超簡易版） */
function loanScore_(d) {
  var s = 50;
  var job = String(d.job || "");
  if (/正社員|公務員/.test(job)) s += 25; else if (/個人事業|法人/.test(job)) s += 12; else if (/契約|派遣|パート/.test(job)) s += 6;
  var income = num_(d.income), amount = num_(d.amount);
  if (income > 0 && amount > 0) {
    var ratio = (amount / Math.max(1, income));
    if (ratio < 0.5) s += 20; else if (ratio < 1) s += 12; else if (ratio < 2) s += 4;
  }
  s = Math.max(0, Math.min(100, s));
  var grade = s >= 80 ? "A" : s >= 60 ? "B" : s >= 40 ? "C" : "D";
  return { score: s, grade: grade };
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

/* ---------- パートナー申込（別サイト） ---------- */
function handlePartner_(d) {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_PARTNERS) || ensureSheet_(ss, cfg.SHEET_PARTNERS, []);

  sh.appendRow([
    new Date(), d.name || "", d.ptype || "", d.email || "",
    d.phone || "", d.area || "", d.message || "", "未対応"
  ]);
  notifyStaff_(
    "🤝 パートナー申込\n" +
    "名称：" + (d.name || "-") + "\n" +
    "タイプ：" + (d.ptype || "-") + "\n" +
    "エリア：" + (d.area || "-") + "\n" +
    "連絡先：" + (d.email || "-") + " / " + (d.phone || "-")
  );
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
