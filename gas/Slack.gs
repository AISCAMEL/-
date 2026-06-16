/**
 * Slack.gs — Slack通知 ＋ Slackから相場見積りに回答する連携
 *
 * 【設定】Config.gs に以下を追加：
 *   SLACK_WEBHOOK_URL   … Incoming Webhook のURL（スタッフ通知の送信先）
 *   SLACK_SLASH_TOKEN   … スラッシュコマンドの検証トークン（Slack App の Verification Token）
 *
 * 【Slackからの回答】チャンネルで次のスラッシュコマンドを実行：
 *   /相場回答 QT-4000 1200000        … 相場見積りに金額を回答（マイページに反映）
 *   /相場回答 QT-4000 1200000 コメント … 任意でコメント
 * GASのウェブアプリURLを Slack App の「Slash Commands」の Request URL に設定してください。
 */

/* スタッフ通知をSlackへ（Incoming Webhook） */
function notifySlack_(message) {
  var cfg = getConfig();
  if (!cfg.SLACK_WEBHOOK_URL) return;
  try {
    UrlFetchApp.fetch(cfg.SLACK_WEBHOOK_URL, {
      method: "post",
      contentType: "application/json",
      payload: JSON.stringify({ text: message }),
      muteHttpExceptions: true
    });
  } catch (err) {
    Logger.log("Slack通知エラー: " + err);
  }
}

/* Slackスラッシュコマンドの受信判定（WebApp.doPostから呼ぶ） */
function isSlackCommand_(e) {
  return !!(e && e.parameter && e.parameter.command && e.parameter.token);
}

/**
 * Slackスラッシュコマンドの処理（コマンド名でルーティング）。
 *   /相場回答 QT-4000 1200000 [コメント]   … 相場見積りに回答→マイページ反映＋メール
 *   /進捗 OD-2041 落札成立                  … 注文/出品のステータス更新
 *   /ローン LN-5001 承認                     … ローン審査ステータス更新
 * 返り値はSlackに表示するテキスト（ephemeral）。
 */
function handleSlackCommand_(e) {
  var cfg = getConfig();
  var p = e.parameter;
  if (cfg.SLACK_SLASH_TOKEN && p.token !== cfg.SLACK_SLASH_TOKEN) {
    return slackText_("⛔ 認証エラー（トークン不一致）");
  }
  var cmd = String(p.command || "").replace("/", "");
  var parts = String(p.text || "").trim().split(/\s+/);
  var id = (parts[0] || "").toUpperCase();
  var by = p.user_name || "staff";

  if (cmd === "相場回答" || cmd === "quote") {
    var amount = Number((parts[1] || "").replace(/[,¥]/g, ""));
    var comment = parts.slice(2).join(" ");
    if (!/^QT-/.test(id) || !amount) return slackText_("使い方： `/相場回答 QT-4000 1200000 [コメント]`");
    var r = answerQuote_(id, amount, comment, by);
    if (!r.ok) return slackText_("⚠️ " + r.error);
    return slackText_("✅ 相場回答を登録（" + id + "）" + r.car + "／" + yen_(amount) + "（" + r.kind + "）→ マイページ反映・" + (r.via || "メール") + "で連絡");
  }

  if (cmd === "進捗" || cmd === "status") {
    var status = parts.slice(1).join(" ");
    if (!id || !status) return slackText_("使い方： `/進捗 OD-2041 落札成立`（OD-/SL-）");
    var u = updateRowStatus_([cfg.SHEET_ORDERS, cfg.SHEET_SELL], id, status, by);
    return slackText_(u.ok ? "✅ ステータス更新：" + id + " → " + status : "⚠️ " + u.error);
  }

  if (cmd === "ローン" || cmd === "loan") {
    var lstatus = parts.slice(1).join(" ");
    if (!/^LN-/.test(id) || !lstatus) return slackText_("使い方： `/ローン LN-5001 承認`");
    var ul = updateRowStatus_([cfg.SHEET_LOAN], id, lstatus, by);
    return slackText_(ul.ok ? "✅ ローン更新：" + id + " → " + lstatus : "⚠️ " + ul.error);
  }

  return slackText_("対応コマンド： `/相場回答` `/進捗` `/ローン`");
}

/* シート群からID一致行を探し、ステータス列(3列目)を更新 */
function updateRowStatus_(sheetNames, id, status, by) {
  var ss = openBook_();
  for (var i = 0; i < sheetNames.length; i++) {
    var sh = ss.getSheetByName(sheetNames[i]);
    if (!sh) continue;
    var v = sh.getDataRange().getValues();
    for (var r = 1; r < v.length; r++) {
      if (String(v[r][1]) === id) {
        sh.getRange(r + 1, 3).setValue(status + (by ? "（" + by + "）" : ""));
        return { ok: true };
      }
    }
  }
  return { ok: false, error: id + " が見つかりません" };
}

/**
 * 相場見積りシートに回答を記入し、状況を「回答済み」に更新。
 * メール連絡が選ばれていれば顧客にメール送付。
 */
function answerQuote_(quoteId, amount, comment, by) {
  var cfg = getConfig();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET_QUOTES);
  if (!sh) return { ok: false, error: "相場見積りシートがありません" };

  var values = sh.getDataRange().getValues();
  // 見出し：受付日時, 見積番号, 種別, お名前, メール, 連絡方法, 車両情報, 回答相場額, 回答状況
  for (var r = 1; r < values.length; r++) {
    if (String(values[r][1]) === quoteId) {
      sh.getRange(r + 1, 8).setValue(amount);              // 回答相場額
      sh.getRange(r + 1, 9).setValue("回答済み" + (by ? "（" + by + "）" : "")); // 回答状況
      var kind = values[r][2], name = values[r][3], email = values[r][4], via = values[r][5], car = values[r][6];
      // メール連絡
      if (email && /メール/.test(String(via))) {
        try {
          MailApp.sendEmail({
            to: email,
            subject: "【AUC-AGENT】" + kind + "のご回答（" + quoteId + "）",
            body: (name || "お客様") + " 様\n\n"
                + "お問い合わせの" + kind + "をご回答します。\n"
                + "対象車両：" + car + "\n"
                + "相場の目安：" + yen_(amount) + "\n"
                + (comment ? "コメント：" + comment + "\n" : "")
                + "\nマイページにも反映しております。ご検討ください。\n\n"
                + "合同会社アイズ（AUC-AGENT）\ninfo@aisjaltd.com"
          });
        } catch (err) { Logger.log("相場回答メールエラー: " + err); }
      }
      return { ok: true, kind: kind, car: car, via: via };
    }
  }
  return { ok: false, error: quoteId + " が見つかりません" };
}

function slackText_(t) {
  return ContentService.createTextOutput(JSON.stringify({ response_type: "ephemeral", text: t }))
    .setMimeType(ContentService.MimeType.JSON);
}

/* 通知テスト */
function testSlack() { notifySlack_("✅ テスト通知：AUC-AGENT Slack連携は正常です。"); }
