/**
 * Line.gs — LINEスタッフ通知（Messaging API push）
 *
 * Config.gs に LINE_CHANNEL_ACCESS_TOKEN と LINE_STAFF_IDS を入れると有効。
 * 空欄のときは何もしません（登録・記録はそのまま動きます）。
 */
function notifyStaff_(message) {
  notifyLine_(message);
  notifySlack_(message); // Slack.gs（Webフックがあれば送信）
}

function notifyLine_(message) {
  var cfg = getConfig();
  if (!cfg.LINE_CHANNEL_ACCESS_TOKEN || !cfg.LINE_STAFF_IDS) return;

  var ids = String(cfg.LINE_STAFF_IDS).split(",").map(function (s) { return s.trim(); }).filter(String);
  ids.forEach(function (to) {
    try {
      UrlFetchApp.fetch("https://api.line.me/v2/bot/message/push", {
        method: "post",
        contentType: "application/json",
        headers: { Authorization: "Bearer " + cfg.LINE_CHANNEL_ACCESS_TOKEN },
        payload: JSON.stringify({ to: to, messages: [{ type: "text", text: message }] }),
        muteHttpExceptions: true
      });
    } catch (err) {
      Logger.log("LINE通知エラー: " + err);
    }
  });
}

/* ========== 顧客向け通知 ========== */

/**
 * 顧客へメール通知を送信する共通関数。
 * LINE UID が会員マスタに登録されている場合は LINE にも送信。
 */
function notifyCustomer_(email, name, subject, body) {
  if (email) {
    try {
      MailApp.sendEmail({
        to: email,
        subject: "【AUC-AGENT】" + subject,
        body: body
      });
    } catch (err) { Logger.log("顧客メール送信エラー: " + err); }
  }

  var lineUid = findMemberLineUid_(email);
  if (lineUid) {
    notifyCustomerLine_(lineUid, body.split("\n\n")[0] + "\n\n詳しくはマイページをご確認ください。\nhttps://auc-agent.com/mypage");
  }
}

function findMemberLineUid_(email) {
  if (!email) return null;
  try {
    var cfg = getConfig();
    var ss = openBook_();
    var sh = ss.getSheetByName(cfg.SHEET_MEMBERS);
    if (!sh) return null;
    var v = sh.getDataRange().getValues();
    for (var r = 1; r < v.length; r++) {
      if (String(v[r][3]).toLowerCase() === String(email).toLowerCase() && v[r][11]) {
        return String(v[r][11]);
      }
    }
  } catch (e) { Logger.log("LINE UID検索エラー: " + e); }
  return null;
}

function notifyCustomerLine_(userId, message) {
  var cfg = getConfig();
  if (!cfg.LINE_CHANNEL_ACCESS_TOKEN || !userId) return;
  try {
    UrlFetchApp.fetch("https://api.line.me/v2/bot/message/push", {
      method: "post",
      contentType: "application/json",
      headers: { Authorization: "Bearer " + cfg.LINE_CHANNEL_ACCESS_TOKEN },
      payload: JSON.stringify({ to: userId, messages: [{ type: "text", text: message }] }),
      muteHttpExceptions: true
    });
  } catch (err) { Logger.log("顧客LINE通知エラー: " + err); }
}

/**
 * オーダーのステータス変更時に顧客へ通知。
 * Slack /進捗 コマンドから呼ばれる。
 */
function notifyOrderStatusChange_(id, status, email, name) {
  var templates = {
    "落札成立":    "おめでとうございます！ご希望のお車が落札されました。\n検査・名義変更の準備に入ります。",
    "名変完了":    "名義変更が完了しました。陸送の手配に入ります。",
    "陸送中":      "お車を陸送中です。到着まで今しばらくお待ちください。",
    "納車完了":    "ご納車が完了しました。この度はAUC-AGENTをご利用いただき、誠にありがとうございます。",
    "出品申込":    "出品のお申込みを受け付けました。出品票を確認のうえ、会場への搬入をご案内いたします。",
    "出品中":      "お車が会場に出品されました。落札結果は速やかにお知らせいたします。",
    "成約":        "お車が落札成立しました。代金のお振込みについてご案内をお送りします。",
    "入金完了":    "ご入金を確認しました。お取引完了です。ありがとうございました。"
  };

  var msg = templates[status];
  if (!msg || !email) return;

  notifyCustomer_(email, name,
    "【" + id + "】ステータス更新：" + status,
    (name || "お客様") + " 様\n\n" +
    "ご依頼 " + id + " のステータスが「" + status + "」に更新されました。\n\n" +
    msg + "\n\n" +
    "進捗はマイページからもご確認いただけます。\n\n" +
    "合同会社アイズ（AUC-AGENT）\ninfo@aisjaltd.com"
  );
}

/** 通知テスト用（自分のuserIdを入れて実行） */
function testLine() {
  notifyStaff_("✅ テスト通知：AUC-AGENT 自動化システムは正常です。");
}
