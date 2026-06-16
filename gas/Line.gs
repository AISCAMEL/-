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

/** 通知テスト用（自分のuserIdを入れて実行） */
function testLine() {
  notifyStaff_("✅ テスト通知：AUC-AGENT 自動化システムは正常です。");
}
