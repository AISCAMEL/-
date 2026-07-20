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

    // ③-2 Slack（スタッフ通知＋Slackから相場回答）任意
    SLACK_WEBHOOK_URL: "",   // Incoming Webhook のURL（通知先）
    SLACK_SLASH_TOKEN: "",   // スラッシュコマンドの Verification Token

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
    SELL_SHEET_PDF_FOLDER_ID: "",

    // ============================================================
    // ⑦ USS精算書 自動取込（USS精算.gs）用の設定
    // ============================================================

    // 精算書メールの差出人（部分一致でOK）。例: "uss.co.jp"
    USS_MAIL_FROM: "uss",
    // 精算書メールの件名キーワード（部分一致）。例: "精算" / "精算書"
    USS_MAIL_SUBJECT: "精算",
    // 未処理メールだけ拾うためのGmailラベル名（自動作成されます）
    USS_DONE_LABEL: "USS処理済み",

    // 添付パスワード（USSから事前共有される固定パスワード）
    // 例では U3472。実運用の値を入れてください。
    USS_PDF_PASSWORD: "U3472",

    // ── パスワード付き添付の「復号」方式 ──
    //   "pdfco" … PDF.co API で復号＋テキスト化（方式A・手軽）
    //   "none"  … 復号せず、USS管理画面からDLしたCSVを取り込む（方式C）
    USS_DECRYPT_MODE: "pdfco",
    // PDF.co の APIキー（https://pdf.co でサインアップ後に取得）
    PDFCO_API_KEY: "",

    // ── 本部手数料（加盟店へ請求する固定手数料）──
    USS_FEE_RAKUSATSU: 11000, // 落札手数料
    USS_FEE_FURIKOMI: 550,    // 振込手数料
    // 合計＝11,550円（＝上の2つの合計。コード側で自動計算）

    // ── 入金消し込み（runReconciliation）用 ──
    //   銀行の入出金明細CSVを置くGoogleドライブ フォルダID（任意）。
    //   空欄ならマイドライブ直下の "USS入金CSV" フォルダを使います。
    USS_BANK_CSV_FOLDER_ID: "",
    // 消し込みの金額許容誤差（円）。振込手数料の差など吸収用。
    USS_MATCH_TOLERANCE: 0,

    // ⑧ USS関連のシート名（基本変更不要）
    SHEET_USS: "USS精算_加盟店",   // 加盟店向け精算（転写先）
    SHEET_NYUKIN: "入金消し込み"    // 入金突合・消し込み管理
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
