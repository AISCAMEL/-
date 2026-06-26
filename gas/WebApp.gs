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

// 動作確認用 ＋ 会員の回答済み相場をJSONPで返す（マイページ反映用）
//   例: <script src="…/exec?action=quotes&email=xxx&callback=cb"></script>
function doGet(e) {
  // ステップメールの配信停止（メール末尾リンク）
  if (e && e.parameter && e.parameter.action === "unsub") {
    var m = (typeof unsubscribeByToken_ === "function")
      ? unsubscribeByToken_(e.parameter.t || "")
      : "受け付けました。";
    return ContentService.createTextOutput(m).setMimeType(ContentService.MimeType.TEXT);
  }
  // 看板ボード：案件一覧（JSONP）。assignee 指定で担当のみ。
  if (e && e.parameter && e.parameter.action === "cases") {
    var cases = (typeof getCasesJson_ === "function") ? getCasesJson_(e.parameter.assignee || "") : [];
    var jbody = JSON.stringify(cases);
    var jcb = e.parameter.callback;
    if (jcb) return ContentService.createTextOutput(jcb + "(" + jbody + ")").setMimeType(ContentService.MimeType.JAVASCRIPT);
    return ContentService.createTextOutput(jbody).setMimeType(ContentService.MimeType.JSON);
  }
  // 会員マイページ：自分の案件（メール一致・JSONP）
  if (e && e.parameter && e.parameter.action === "mycase") {
    var mine = (typeof getMyCasesJson_ === "function") ? getMyCasesJson_(e.parameter.email || "") : [];
    var mbody = JSON.stringify(mine);
    var mcb = e.parameter.callback;
    if (mcb) return ContentService.createTextOutput(mcb + "(" + mbody + ")").setMimeType(ContentService.MimeType.JAVASCRIPT);
    return ContentService.createTextOutput(mbody).setMimeType(ContentService.MimeType.JSON);
  }
  if (e && e.parameter && e.parameter.action === "quotes") {
    var data = getAnsweredQuotes_(e.parameter.email || "");
    var body = JSON.stringify(data);
    var cb = e.parameter.callback;
    if (cb) {
      return ContentService.createTextOutput(cb + "(" + body + ")")
        .setMimeType(ContentService.MimeType.JAVASCRIPT);
    }
    return ContentService.createTextOutput(body).setMimeType(ContentService.MimeType.JSON);
  }
  return ContentService.createTextOutput("AUC-AGENT Web App is running.")
    .setMimeType(ContentService.MimeType.TEXT);
}

// LPからのPOST／Slackスラッシュコマンドを受ける本体
function doPost(e) {
  try {
    // Slack スラッシュコマンド（application/x-www-form-urlencoded）
    if (typeof isSlackCommand_ === "function" && isSlackCommand_(e)) {
      return handleSlackCommand_(e);
    }
    var data = JSON.parse((e && e.postData && e.postData.contents) || "{}");
    var result;

    switch (data.type) {
      case "order":    result = handleOrder_(data);    break;
      case "sell":     result = handleSell_(data);     break;
      case "loan":     result = handleLoan_(data);     break;
      case "register": result = handleRegister_(data); break;
      case "contact":  result = handleContact_(data);  break;
      case "quote":    result = handleQuote_(data);    break;
      case "buymo":    result = handleBuymoLead_(data); break;
      case "stepmail": result = handleStepMailTrigger_(data); break; // WP等から後発でステップメール発動
      case "case":     result = handleCase_(data);     break; // 看板ボードの作成/更新
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
    "要約：" + ai.summary + "\n" +
    "▶ 車ひろば（agent.car-hiroba.jp）のログインID/PWを発行し、" + (d.email || "登録メール") + " へ連絡してください。"
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

  // オリコ クレジット申込書（PDF）を裏で自動生成し、ユーザーのメールへ送付
  var formUrl = "", mailed = "";
  try {
    var pdfFile = makeOricoFormPdf_(id, d, score);
    formUrl = pdfFile.getUrl();
    if (d.email) {
      MailApp.sendEmail({
        to: d.email,
        subject: "【AUC-AGENT】オリコ オートローン クレジット申込書のご送付（" + id + "）",
        body: (d.name || "お客様") + " 様\n\n"
            + "この度はオートローンのお申込みありがとうございます。\n"
            + "お見積りの目安：月々 " + yen_(loanMonthly_(d).monthly) + "（" + (d.term || "-") + "回 / 年率 " + (d.rate || "-") + "%）\n\n"
            + "オリコのクレジット申込書（PDF）を添付いたします。ご記入・必要書類とあわせてご返送ください。\n"
            + "審査結果は追ってご連絡いたします。\n\n"
            + "合同会社アイズ（AUC-AGENT）\n福島県いわき市四倉町細谷字大町1番\ninfo@aisjaltd.com",
        attachments: [pdfFile.getBlob()]
      });
      mailed = "送付済(" + d.email + ")";
    }
  } catch (e) { formUrl = "生成/送付失敗:" + e; }

  sh.appendRow([
    new Date(), id, "申込書送付・審査待ち",
    d.name || "", d.email || "", d.phone || "",
    num_(d.amount), num_(d.term), d.job || "", num_(d.income),
    score.score, score.grade, d.orderId || "", "オリコ申込書:" + formUrl + " / " + mailed
  ]);

  notifyStaff_(
    "💳 ローン申込 " + id + "\n" +
    "お名前：" + (d.name || "-") + "\n" +
    "希望額：" + yen_(d.amount) + " / " + (d.term || "-") + "回\n" +
    "AI判定：" + score.grade + "（" + score.score + "点）\n" +
    "オリコ申込書：自動生成し " + (d.email || "-") + " へ送付（" + mailed + "）\n" +
    "→ 返送後、オリコへ審査依頼してください。"
  );
  return { ok: true, id: id, grade: score.grade, form: formUrl };
}

/* ローン月々（元利均等）— GAS側 */
function loanMonthly_(d) {
  var p = num_(d.amount), n = num_(d.term) || 1, r = (num_(d.rate) || 0) / 100 / 12;
  var m = r === 0 ? p / n : p * r / (1 - Math.pow(1 + r, -n));
  return { monthly: Math.round(m), total: Math.round(m) * n };
}

/**
 * オリコ クレジット申込書（PDF）を顧客情報から自動生成し、ドライブに保存。
 */
function makeOricoFormPdf_(id, d, score) {
  var cfg = getConfig();
  var lm = loanMonthly_(d);
  var BL = "________________";  // 記入欄
  var doc = DocumentApp.create("オリコ_クレジット申込書_" + id);
  var b = doc.getBody();

  b.appendParagraph("オリコ オートローン クレジット申込書（個別信用購入あっせん）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING1);
  b.appendParagraph("受付番号：" + id + "　作成日：" + new Date().toLocaleDateString("ja-JP")
    + "　加盟店：合同会社アイズ（AUC-AGENT）").setFontSize(9);
  b.appendParagraph("※ 取得済みの情報は記入済みです。空欄（" + BL + "）はお客様にてご記入・押印のうえご返送ください。").setFontSize(9);

  // 1. 申込者情報
  b.appendParagraph("1. お申込者情報").setHeading(DocumentApp.ParagraphHeading.HEADING2);
  b.appendTable([
    ["お名前（漢字）", d.name || BL, "フリガナ", BL],
    ["生年月日", BL + "（年齢 " + BL + "）", "性別", "男 ・ 女"],
    ["ご自宅住所", "〒" + BL + "　" + BL, "", ""],
    ["自宅TEL", d.phone || BL, "携帯TEL", d.phone || BL],
    ["メール", d.email || "", "", ""],
    ["居住形態", "自己所有 ・ 家族所有 ・ 賃貸 ・ 社宅", "居住年数", BL + " 年"],
    ["家族構成", "世帯人数 " + BL + " 名 ／ 扶養 " + BL + " 名", "住宅ローン/家賃", "月 " + BL + " 円"]
  ]);

  // 2. 勤務先情報
  b.appendParagraph("2. お勤め先情報").setHeading(DocumentApp.ParagraphHeading.HEADING2);
  b.appendTable([
    ["勤務先名", BL, "電話", BL],
    ["所在地", "〒" + BL + "　" + BL, "", ""],
    ["雇用形態", d.job || BL, "業種・職種", BL],
    ["勤続年数", BL + " 年 " + BL + " ヶ月", "税込年収", yen_(d.income)],
    ["健康保険", "社保 ・ 国保 ・ 組合 ・ 共済 ・ その他", "", ""]
  ]);

  // 3. お支払い条件
  b.appendParagraph("3. お支払い条件").setHeading(DocumentApp.ParagraphHeading.HEADING2);
  b.appendTable([
    ["ご利用金額（分割払い元金）", yen_(d.amount)],
    ["実質年率（目安）", (d.rate || "") + " %"],
    ["支払回数", (d.term || "") + " 回"],
    ["月々のお支払い（目安）", yen_(lm.monthly)],
    ["お支払い総額（目安）", yen_(lm.total)],
    ["初回／2回目以降", "初回 " + BL + " 円 ／ 2回目以降 " + yen_(lm.monthly)],
    ["ボーナス加算", "有（" + BL + "円 ×年2回）・ 無"],
    ["お支払い方法", "口座振替（金融機関 " + BL + " / 口座番号 " + BL + "）"],
    ["AI一次判定（社内）", (score ? score.grade : "")]
  ]);

  // 4. 購入商品
  b.appendParagraph("4. ご購入商品（自動車）").setHeading(DocumentApp.ParagraphHeading.HEADING2);
  b.appendTable([
    ["車名・型式", BL, "年式", BL],
    ["走行距離", BL + " km", "車台番号", BL],
    ["現金販売価格", BL + " 円", "頭金", BL + " 円"],
    ["販売／取扱店", "合同会社アイズ（AUC-AGENT）", "", ""]
  ]);

  // 5. 連帯保証人
  b.appendParagraph("5. 連帯保証人（必要な場合のみ）").setHeading(DocumentApp.ParagraphHeading.HEADING2);
  b.appendTable([
    ["お名前", BL, "続柄", BL],
    ["住所", "〒" + BL + "　" + BL, "電話", BL],
    ["勤務先", BL, "年収", BL + " 円"]
  ]);

  // 6. 同意・署名
  b.appendParagraph("6. ご確認・同意").setHeading(DocumentApp.ParagraphHeading.HEADING2);
  b.appendParagraph("・本申込に関する個人情報を、株式会社オリエントコーポレーション（オリコ）および加盟店（合同会社アイズ）が、与信・契約の判断および個人信用情報機関への登録・利用のために取り扱うことに同意します。");
  b.appendParagraph("・記載内容に相違ありません。");
  b.appendParagraph("");
  b.appendParagraph("申込日：" + BL + "　　ご署名：" + BL + "　　㊞");
  b.appendParagraph("");
  b.appendParagraph("※ 本書は加盟店が事前情報を反映した申込書です。正式審査にはオリコ所定の本人確認書類等が必要です。最終様式・項目はオリコ指定の申込書に準拠します。").setFontSize(9);

  doc.saveAndClose();

  var file = DriveApp.getFileById(doc.getId());
  var pdf = file.getAs("application/pdf");
  var pdfFile = cfg.SELL_SHEET_PDF_FOLDER_ID
    ? DriveApp.getFolderById(cfg.SELL_SHEET_PDF_FOLDER_ID).createFile(pdf).setName("オリコ申込書_" + id + ".pdf")
    : DriveApp.createFile(pdf).setName("オリコ申込書_" + id + ".pdf");
  file.setTrashed(true);
  return pdfFile;
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
  var role = d.role || "member"; // member / partner / hq
  sh.appendRow([new Date(), id, d.name || "", d.email || "", d.plan || "", d.source || "LP", role]);

  notifyStaff_("👤 新規登録 " + id + "（" + role + "）\nお名前：" + (d.name || "-") + "\nメール：" + (d.email || "-") + "\n希望プラン：" + (d.plan || "-"));
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

/* ---------- BUYMO 査定/問い合わせ（ステップメール登録あり） ---------- */
function handleBuymoLead_(d) {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_CONTACTS) || ensureSheet_(ss, cfg.SHEET_CONTACTS, []);
  var id = "BM-" + nextSeq_(sh, 5000);

  sh.appendRow([new Date(), d.name || "", d.email || "", d.phone || "", "[BUYMO]" + (d.genre ? "(" + d.genre + ")" : "") + " " + (d.message || ""), "未対応"]);
  notifyStaff_(
    "🐮 BUYMO 査定/問い合わせ " + id + "\n" +
    "お名前：" + (d.name || "-") + "\n" +
    "TEL：" + (d.phone || "-") + " / メール：" + (d.email || "-") + "\n" +
    (d.genre ? "ジャンル：" + d.genre + "\n" : "") +
    (d.message || "")
  );

  // ステップメール（StepMail.gs）に登録。未デプロイでもエラーにしない。
  if (typeof enrollStepMail_ === "function") {
    enrollStepMail_({ id: id, name: d.name, email: d.email, genre: d.genre, source: d.source });
  }
  // 看板ボードに案件を自動生成（Board.gs）
  if (typeof createCaseFromLead_ === "function") {
    createCaseFromLead_({ id: id, name: d.name, phone: d.phone, email: d.email, genre: d.genre, message: d.message });
  }
  return { ok: true, id: id };
}

/* ---------- ステップメールの外部トリガー（WP/Zapier等から） ----------
   POST {type:"stepmail", token, email, name, genre}
   token は StepMail.gs の stepCfg_().TRIGGER_TOKEN と一致が必要。 */
function handleStepMailTrigger_(d) {
  var cfg = (typeof stepCfg_ === "function") ? stepCfg_() : { TRIGGER_TOKEN: "" };
  if (cfg.TRIGGER_TOKEN && String(d.token || "") !== String(cfg.TRIGGER_TOKEN)) {
    return { ok: false, error: "invalid token" };
  }
  if (!d.email) return { ok: false, error: "email required" };
  if (typeof enrollStepMail_ === "function") {
    enrollStepMail_({ id: d.id || "", name: d.name, email: d.email, genre: d.genre, source: d.source || "external" });
    return { ok: true };
  }
  return { ok: false, error: "stepmail module missing" };
}

/* ---------- 相場見積り（買取/仕入れ）受付 ---------- */
function handleQuote_(d) {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_QUOTES) || ensureSheet_(ss, cfg.SHEET_QUOTES, []);
  var id = d.id || ("QT-" + nextSeq_(sh, 4000));
  var kindLabel = d.kind === "sell" ? "買取相場" : "仕入れ相場";

  sh.appendRow([
    new Date(), id, kindLabel,
    d.name || "", d.email || "", d.via || "メール",
    d.car || "", "", "回答待ち"
  ]);

  notifyStaff_(
    "📈 相場見積り依頼 " + id + "（" + kindLabel + "）\n" +
    "お名前：" + (d.name || "-") + "\n" +
    "車両：" + (d.car || "-") + "\n" +
    "連絡方法：" + (d.via || "メール") + "（" + (d.email || "-") + "）\n" +
    "▶ Slackから回答：　/相場回答 " + id + " 金額　（例: /相場回答 " + id + " 1200000）\n" +
    "→ 査定のうえ回答し、マイページに反映します。"
  );
  return { ok: true, id: id };
}

/* 会員の回答済み相場（JSONP用） email一致のものを返す */
function getAnsweredQuotes_(email) {
  var cfg = getConfig();
  try {
    var ss = openBook_();
    var sh = ss.getSheetByName(cfg.SHEET_QUOTES);
    if (!sh || !email) return [];
    var v = sh.getDataRange().getValues();
    var out = [];
    for (var r = 1; r < v.length; r++) {
      if (String(v[r][4]).toLowerCase() === String(email).toLowerCase() && v[r][7]) {
        out.push({ id: v[r][1], kind: v[r][2], car: v[r][6], value: Number(v[r][7]), status: v[r][8] });
      }
    }
    return out;
  } catch (e) { return []; }
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
