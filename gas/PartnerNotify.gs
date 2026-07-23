/**
 * PartnerNotify.gs — 加盟店への通知（メール＋Slack）
 *
 * 【何をする？】
 * - 案件が割り当て / ステージ変更されたとき → 担当加盟店にメール or Slack 通知
 * - 本部がお知らせを投稿したとき → 全有効加盟店に一斉通知
 *
 * 【セットアップ】
 * 1) hq-stores.html から加盟店を追加・更新する際にメール/Slack Webhook URL を入力
 *    → 「加盟店マスタ」シートに自動保存（handleStore_ が受け取る）
 * 2) Config.gs の COMPANY_NAME を自社名に変更（メール署名で使用）
 *
 * 「加盟店マスタ」シート列：
 *   登録日時 / 店名 / エリア / 電話 / メール / Slack Webhook URL / ステータス
 */

var PARTNER_SHEET = "加盟店マスタ";

function partnerSheet_() {
  var ss = openBook_();
  return ss.getSheetByName(PARTNER_SHEET) || ensureSheet_(ss, PARTNER_SHEET,
    ["登録日時", "店名", "エリア", "電話", "メール", "Slack Webhook URL", "ステータス"]
  );
}

/* 店名で加盟店レコードを探す。{name, area, tel, email, slack, status} または null。 */
function getPartnerStore_(storeName) {
  if (!storeName) return null;
  try {
    var sh = partnerSheet_();
    var v = sh.getDataRange().getValues();
    for (var r = 1; r < v.length; r++) {
      if (String(v[r][1]).trim() === String(storeName).trim()) {
        return {
          name:   String(v[r][1]),
          area:   String(v[r][2]),
          tel:    String(v[r][3]),
          email:  String(v[r][4]).trim(),
          slack:  String(v[r][5]).trim(),
          status: String(v[r][6])
        };
      }
    }
  } catch (e) { Logger.log("getPartnerStore_: " + e); }
  return null;
}

/* 担当加盟店1店に通知（メール＋店舗 Slack Webhook） */
function notifyPartnerByName_(storeName, message) {
  var store = getPartnerStore_(storeName);
  if (!store) {
    Logger.log("notifyPartnerByName_: 加盟店が見つかりません: " + storeName);
    return;
  }
  sendPartnerNotify_(store, message);
}

/* 全有効加盟店に一斉通知（本部からのお知らせ等） */
function broadcastToAllPartners_(message) {
  try {
    var sh = partnerSheet_();
    var v = sh.getDataRange().getValues();
    for (var r = 1; r < v.length; r++) {
      if (!v[r][1]) continue;
      var store = {
        name:   String(v[r][1]),
        email:  String(v[r][4]).trim(),
        slack:  String(v[r][5]).trim(),
        status: String(v[r][6])
      };
      if (store.status === "停止") continue;
      if (!store.email && !store.slack) continue;
      try { sendPartnerNotify_(store, message); } catch (e) {
        Logger.log("broadcastToAllPartners_ " + store.name + ": " + e);
      }
    }
  } catch (e) { Logger.log("broadcastToAllPartners_: " + e); }
}

/* メール＆ Slack Webhook に通知を送る（内部共通） */
function sendPartnerNotify_(store, message) {
  var cfg = getConfig();
  var brand = cfg.COMPANY_NAME || "BUYMO本部";
  var subject = "【" + brand + "】" + message.slice(0, 45).replace(/\n/g, " ") + (message.length > 45 ? "…" : "");

  // ---- メール ----
  if (store.email && store.email.indexOf("@") > 0) {
    try {
      var htmlBody =
        '<div style="font-family:sans-serif;max-width:540px;color:#333;">' +
        '<div style="background:#0e1b33;color:#fff;padding:14px 18px;border-radius:10px 10px 0 0;font-weight:700;">🐮 ' + esc_(brand) + '</div>' +
        '<div style="border:1px solid #ddd;border-top:none;border-radius:0 0 10px 10px;padding:20px;">' +
        '<p style="white-space:pre-wrap;margin:0 0 18px;">' + esc_(message) + '</p>' +
        '<p style="font-size:12px;color:#999;border-top:1px solid #eee;padding-top:12px;margin:0;">' + esc_(brand) + ' / info@aisjaltd.com</p>' +
        '</div></div>';
      MailApp.sendEmail({
        to: store.email,
        subject: subject,
        body: brand + " から " + store.name + " 様へのご連絡です。\n\n" + message + "\n\n---\n" + brand + "\ninfo@aisjaltd.com",
        htmlBody: htmlBody,
        name: brand
      });
    } catch (e) { Logger.log("sendPartnerNotify_ mail " + store.name + ": " + e); }
  }

  // ---- 加盟店個別 Slack Webhook ----
  if (store.slack && /^https:\/\/hooks\.slack\.com\//.test(store.slack)) {
    try {
      UrlFetchApp.fetch(store.slack, {
        method: "post",
        contentType: "application/json",
        payload: JSON.stringify({
          text: "🏪 *" + store.name + "* 向けBUYMO本部通知",
          blocks: [
            { type: "section", text: { type: "mrkdwn", text: "*🏪 " + store.name + " 向けご連絡*\n" + message } }
          ]
        }),
        muteHttpExceptions: true
      });
    } catch (e) { Logger.log("sendPartnerNotify_ slack " + store.name + ": " + e); }
  }
}

/* 加盟店の追加/更新（doPost type:"store"） */
function handleStore_(d) {
  if (!d.name) return { ok: false, error: "name required" };
  var sh = partnerSheet_();
  var v = sh.getDataRange().getValues();
  for (var r = 1; r < v.length; r++) {
    if (String(v[r][1]).trim() === String(d.name).trim()) {
      sh.getRange(r + 1, 1).setValue(new Date());
      if (d.area   != null) sh.getRange(r + 1, 3).setValue(d.area);
      if (d.tel    != null) sh.getRange(r + 1, 4).setValue(d.tel);
      if (d.email  != null) sh.getRange(r + 1, 5).setValue(d.email);
      if (d.slack  != null) sh.getRange(r + 1, 6).setValue(d.slack);
      if (d.status != null) sh.getRange(r + 1, 7).setValue(d.status);
      return { ok: true, action: "updated", name: d.name };
    }
  }
  sh.appendRow([new Date(), d.name, d.area || "", d.tel || "", d.email || "", d.slack || "", d.status || "稼働中"]);
  return { ok: true, action: "created", name: d.name };
}

/* 加盟店一覧を返す（doGet action=stores） */
function getStoresJson_() {
  var out = [];
  try {
    var sh = partnerSheet_();
    var v = sh.getDataRange().getValues();
    for (var r = 1; r < v.length; r++) {
      if (!v[r][1]) continue;
      out.push({
        name:   String(v[r][1]),
        area:   String(v[r][2]),
        tel:    String(v[r][3]),
        email:  String(v[r][4]),
        slack:  String(v[r][5]),
        status: String(v[r][6])
      });
    }
  } catch (e) { Logger.log("getStoresJson_: " + e); }
  return out;
}

function esc_(s) { return String(s || "").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"); }

/* 通知テスト（1店舗を直接指定して実行） */
function testPartnerNotify() {
  notifyPartnerByName_("いわき店", "✅ テスト通知：加盟店通知システムが正常に動作しています。");
}
