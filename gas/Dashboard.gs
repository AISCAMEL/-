/**
 * Dashboard.gs — スタッフ用ダッシュボード集計・週次レポート
 *
 * 以下の機能を提供:
 * 1. getStats() — 今月の申込数・成約率・売上目安を集計
 * 2. sendWeeklyReport() — Slack/LINEに週次レポートを送信（トリガー設定推奨）
 *
 * 【トリガー設定手順】
 *   GAS エディタ → トリガー → 「トリガーを追加」
 *   関数: sendWeeklyReport / 時間主導型 / 週ベース / 毎週月曜 9:00
 */

function getStats() {
  var cfg = getConfig();
  var ss = openBook_();

  var orders = readSheet_(ss, cfg.SHEET_ORDERS);
  var sells = readSheet_(ss, cfg.SHEET_SELL);
  var loans = readSheet_(ss, cfg.SHEET_LOAN);
  var members = readSheet_(ss, cfg.SHEET_MEMBERS);
  var quotes = readSheet_(ss, cfg.SHEET_QUOTES);

  var now = new Date();
  var thisMonth = now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");

  var monthOrders = filterByMonth_(orders, thisMonth);
  var monthSells = filterByMonth_(sells, thisMonth);
  var monthLoans = filterByMonth_(loans, thisMonth);
  var monthMembers = filterByMonth_(members, thisMonth);

  var totalOrders = monthOrders.length;
  var doneOrders = monthOrders.filter(function (r) { return r["ステータス"] === "納車完了"; }).length;
  var totalSells = monthSells.length;
  var doneSells = monthSells.filter(function (r) { return r["ステータス"] === "成約"; }).length;

  return {
    period: thisMonth,
    newMembers: monthMembers.length,
    orders: { total: totalOrders, done: doneOrders },
    sells: { total: totalSells, done: doneSells },
    loans: monthLoans.length,
    quotes: filterByMonth_(quotes, thisMonth).length,
    allMembers: members.length,
    allOrders: orders.length,
    allSells: sells.length
  };
}

function sendWeeklyReport() {
  var stats = getStats();
  var cfg = getConfig();

  var text =
    "📊 " + cfg.COMPANY_NAME + " 週次レポート\n" +
    "━━━━━━━━━━━━━━━━━━━━\n" +
    "📅 集計期間: " + stats.period + "\n\n" +
    "👥 新規会員: " + stats.newMembers + "名（累計 " + stats.allMembers + "名）\n" +
    "🚗 購入代行: " + stats.orders.total + "件（完了 " + stats.orders.done + "件）\n" +
    "🏷️ 出品代行: " + stats.sells.total + "件（成約 " + stats.sells.done + "件）\n" +
    "💳 ローン申込: " + stats.loans + "件\n" +
    "📈 相場見積り: " + stats.quotes + "件\n" +
    "━━━━━━━━━━━━━━━━━━━━\n" +
    "累計: 購入 " + stats.allOrders + "件 ／ 出品 " + stats.allSells + "件";

  notifyStaff_(text);
}

function readSheet_(ss, sheetName) {
  var sheet = ss.getSheetByName(sheetName);
  if (!sheet || sheet.getLastRow() < 2) return [];
  var data = sheet.getDataRange().getValues();
  var headers = data[0];
  var rows = [];
  for (var i = 1; i < data.length; i++) {
    var row = {};
    for (var j = 0; j < headers.length; j++) {
      row[headers[j]] = data[i][j];
    }
    rows.push(row);
  }
  return rows;
}

function filterByMonth_(rows, yearMonth) {
  return rows.filter(function (r) {
    var d = r["受付日"] || r["登録日"] || r["申込日"] || "";
    if (d instanceof Date) {
      d = d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0");
    }
    return String(d).indexOf(yearMonth) === 0;
  });
}
