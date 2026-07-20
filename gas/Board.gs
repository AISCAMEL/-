/**
 * Board.gs — BUYMO 案件ボード（看板ボード）＋ 営業案件の管理
 *
 * - 査定/問い合わせ（handleBuymoLead_）が来ると、案件として自動で「新規受付」に積まれます。
 * - 本部/加盟店の看板ボード（hq.html）からステージ移動すると、ここに反映＋Slack通知。
 * - hq.html は doGet(action=cases) で案件一覧を取得します（JSONP）。
 *
 * シート「案件ボード」列：
 *   更新日時 / 案件ID / お名前 / 連絡先 / メール / ジャンル / 担当加盟店 / ステージ / 想定金額 / メモ
 */

var BOARD_STAGES = ["新規受付", "査定中", "商談中", "契約", "入金待ち", "完了"];
var BOARD_SHEET = "案件ボード";

function boardSheet_() {
  var ss = openBook_();
  return ss.getSheetByName(BOARD_SHEET) || ensureSheet_(ss, BOARD_SHEET, [
    "更新日時", "案件ID", "お名前", "連絡先", "メール", "ジャンル", "担当加盟店", "ステージ", "想定金額", "メモ"
  ]);
}

function findCaseRow_(sh, id) {
  var v = sh.getDataRange().getValues();
  for (var r = 1; r < v.length; r++) if (String(v[r][1]) === String(id)) return r + 1;
  return 0;
}

/* 査定リードから案件を自動生成（handleBuymoLead_ から呼ばれる） */
function createCaseFromLead_(d) {
  try {
    var sh = boardSheet_();
    var id = d.id || ("CS-" + nextSeq_(sh, 7000));
    sh.appendRow([new Date(), id, d.name || "", d.phone || "", d.email || "", d.genre || "", "", BOARD_STAGES[0], "", (d.message || "").slice(0, 200)]);
    return id;
  } catch (e) { Logger.log("createCaseFromLead_: " + e); return ""; }
}

/* ボードからの作成/更新（doPost type:"case"） */
function handleCase_(d) {
  var sh = boardSheet_();
  var stage = (BOARD_STAGES.indexOf(d.stage) >= 0) ? d.stage : BOARD_STAGES[0];

  if (d.id) {
    var row = findCaseRow_(sh, d.id);
    if (row) {
      var prevStage    = String(sh.getRange(row, 8).getValue());
      var prevAssignee = String(sh.getRange(row, 7).getValue());
      var caseName     = String(sh.getRange(row, 3).getValue() || "-");
      sh.getRange(row, 1).setValue(new Date());
      if (d.assignee != null) sh.getRange(row, 7).setValue(d.assignee);
      sh.getRange(row, 8).setValue(stage);
      if (d.amount != null) sh.getRange(row, 9).setValue(num_(d.amount));
      if (d.memo != null) sh.getRange(row, 10).setValue(String(d.memo).slice(0, 300));

      var assigneeNow = (d.assignee != null) ? d.assignee : prevAssignee;

      // ステージ変更通知
      if (prevStage !== stage) {
        var stageMsg = "🗂️ 案件ステージ変更 " + d.id + "：" + prevStage + " → " + stage +
          "\nお名前：" + caseName + (assigneeNow ? "\n担当：" + assigneeNow : "");
        notifyStaff_(stageMsg);
        if (assigneeNow && typeof notifyPartnerByName_ === "function") {
          notifyPartnerByName_(assigneeNow,
            "【案件ステージ変更】" +
            "\n案件ID：" + d.id +
            "\nお名前：" + caseName +
            "\nステージ：" + prevStage + " → " + stage
          );
        }
      }

      // 担当変更通知（新たに割り当てられた店舗に通知）
      if (d.assignee != null && d.assignee && d.assignee !== prevAssignee) {
        if (typeof notifyPartnerByName_ === "function") {
          notifyPartnerByName_(d.assignee,
            "【案件割り当て】新しい案件が担当に追加されました。" +
            "\n案件ID：" + d.id +
            "\nお名前：" + caseName +
            "\nジャンル：" + (String(sh.getRange(row, 6).getValue()) || "-") +
            "\nステージ：" + stage
          );
        }
      }

      return { ok: true, id: d.id, stage: stage };
    }
  }
  // 新規
  var id = "CS-" + nextSeq_(sh, 7000);
  sh.appendRow([new Date(), id, d.name || "", d.phone || "", d.email || "", d.genre || "", d.assignee || "", stage, num_(d.amount), String(d.memo || "").slice(0, 300)]);
  notifyStaff_("🆕 新規案件 " + id + "（" + stage + "）\nお名前：" + (d.name || "-"));
  // 担当が付いていれば即通知
  if (d.assignee && typeof notifyPartnerByName_ === "function") {
    notifyPartnerByName_(d.assignee,
      "【新規案件割り当て】" +
      "\n案件ID：" + id +
      "\nお名前：" + (d.name || "-") +
      "\nジャンル：" + (d.genre || "-") +
      "\nステージ：" + stage
    );
  }
  return { ok: true, id: id, stage: stage };
}

/* 会員マイページ用：メールアドレス一致の自分の案件を返す（doGet action=mycase）。 */
function getMyCasesJson_(email) {
  var out = [];
  email = String(email || "").trim().toLowerCase();
  if (!email) return out;
  try {
    var sh = openBook_().getSheetByName(BOARD_SHEET);
    if (!sh) return out;
    var v = sh.getDataRange().getValues();
    for (var r = 1; r < v.length; r++) {
      if (String(v[r][4]).trim().toLowerCase() !== email) continue;
      out.push({ id: v[r][1], name: v[r][2], genre: v[r][5], stage: v[r][7], amount: Number(v[r][8]) || 0, updated: v[r][0] });
    }
  } catch (e) { Logger.log("getMyCasesJson_: " + e); }
  return out;
}

/* 対応履歴メモ（doPost type:"note"）→ 「対応履歴」シートに追記＋通知 */
function handleNote_(d) {
  if (!d.id || !d.text) return { ok: false, error: "id/text required" };
  var ss = openBook_();
  var sh = ss.getSheetByName("対応履歴") || ensureSheet_(ss, "対応履歴", ["日時", "案件ID", "内容"]);
  sh.appendRow([new Date(), d.id, String(d.text).slice(0, 500)]);
  notifyStaff_("📝 対応メモ " + d.id + "\n" + d.text);
  return { ok: true };
}

/* 本部→加盟店 お知らせ（doPost type:"notice" / doGet action=notices） */
function handleNotice_(d) {
  if (!d.title) return { ok: false, error: "title required" };
  var ss = openBook_();
  var sh = ss.getSheetByName("お知らせ") || ensureSheet_(ss, "お知らせ", ["日時", "ID", "タイトル", "本文", "重要度"]);
  sh.appendRow([new Date(), d.id || ("N-" + nextSeq_(sh, 1000)), d.title, d.body || "", d.level || "info"]);
  var noticeMsg = "📢 お知らせ投稿：" + d.title + "\n" + (d.body || "");
  notifyStaff_(noticeMsg);
  // 全加盟店に一斉通知（メール＋Slack）
  if (typeof broadcastToAllPartners_ === "function") {
    var broadcast = "📢 本部からのお知らせ" + (d.level === "warn" ? "【重要】" : "") +
      "\n\n" + d.title + "\n" + (d.body || "");
    broadcastToAllPartners_(broadcast);
  }
  return { ok: true };
}
function getNoticesJson_() {
  var out = [];
  try {
    var sh = openBook_().getSheetByName("お知らせ");
    if (!sh) return out;
    var v = sh.getDataRange().getValues();
    for (var r = v.length - 1; r >= 1; r--) {
      out.push({ id: v[r][1], t: v[r][2], b: v[r][3], lv: v[r][4], date: Utilities.formatDate(new Date(v[r][0]), "Asia/Tokyo", "yyyy/MM/dd") });
    }
  } catch (e) { Logger.log("getNoticesJson_: " + e); }
  return out;
}

/* 案件一覧を返す（doGet action=cases）。assignee 指定で担当の案件のみ。 */
function getCasesJson_(assignee) {
  var out = [];
  try {
    var sh = openBook_().getSheetByName(BOARD_SHEET);
    if (!sh) return out;
    var v = sh.getDataRange().getValues();
    for (var r = 1; r < v.length; r++) {
      if (assignee && String(v[r][6]) !== String(assignee)) continue;
      out.push({
        id: v[r][1], name: v[r][2], tel: v[r][3], email: v[r][4],
        genre: v[r][5], assignee: v[r][6], stage: v[r][7],
        amount: Number(v[r][8]) || 0, memo: v[r][9]
      });
    }
  } catch (e) { Logger.log("getCasesJson_: " + e); }
  return out;
}
