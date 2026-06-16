/**
 * Config.gs — オークション代行 自動化システム 設定
 *
 * 【GAS初心者向けメモ】
 * このファイルは「設定値」をまとめた場所です。
 * 下の getConfig() の中の値を、ご自身のものに書き換えてください。
 * （スプレッドシートID・LINEトークン・OpenRouterキー）
 *
 * 値はすべて文字列（"" で囲む）。空欄のままでも登録は動きますが、
 * LINE通知やAI要約は該当キーを入れたときだけ動きます。
 */
function getConfig() {
  return {
    // ① 受け皿となるスプレッドシートのID
    //    スプレッドシートのURL https://docs.google.com/spreadsheets/d/ ★ここ★ /edit
    //    新規作成する場合は空欄のままで setupAll() を実行すると自動作成されます。
    SPREADSHEET_ID: "",

    // ② LINE公式アカウント（Messaging API）— スタッフ宛て通知用
    //    LINE Developers → Messaging API → チャネルアクセストークン（長期）
    LINE_CHANNEL_ACCESS_TOKEN: "",
    //    通知を受け取るスタッフの userId（複数可・カンマ区切り）。
    //    グループに送る場合は groupId を1つだけ入れてもOK。
    LINE_STAFF_IDS: "",

    // ③ OpenRouter（AIで申込内容を要約・優先度づけ）任意
    OPENROUTER_API_KEY: "",
    OPENROUTER_MODEL: "deepseek/deepseek-chat", // 無料〜安価モデル。例: google/gemini-flash-1.5

    // ④ 通知に表示する自社名（任意）
    COMPANY_NAME: "AUC-AGENT（オークション代行）",

    // ⑤ シート名（基本変更不要）
    SHEET_ORDERS: "オーダー管理",     // 購入代行
    SHEET_SELL:    "出品管理",        // 出品代行
    SHEET_LOAN:    "ローン審査",      // ローン申込（オリコ連携）
    SHEET_MEMBERS: "会員マスタ",
    SHEET_CONTACTS: "問い合わせ",
    SHEET_QUOTES: "相場見積り",

    // ⑥ デジタル出品票（PDF）出力先 Google ドライブ フォルダID（任意）
    //    空欄ならマイドライブ直下に作成します。
    SELL_SHEET_PDF_FOLDER_ID: ""
  };
}

/**
 * スプレッドシートを開く（IDが空なら新規作成してIDをログ表示）。
 */
function openBook_() {
  var cfg = getConfig();
  if (cfg.SPREADSHEET_ID) {
    return SpreadsheetApp.openById(cfg.SPREADSHEET_ID);
  }
  var ss = SpreadsheetApp.create("AUC-AGENT_管理DB");
  Logger.log("新しいスプレッドシートを作成しました。Config.gs の SPREADSHEET_ID に次のIDを貼り付けてください：\n" + ss.getId());
  return ss;
}
