/**
 * Config.gs — 設定の一元管理
 *
 * 機密値（APIキー・各種ID）はコードに直書きせず、
 * GASエディタの[プロジェクトの設定]→[スクリプトプロパティ]に登録します。
 * 登録するキーは CONFIG.PROP を参照。初回は setupCheck() で不足を確認できます。
 */
const CONFIG = {
  // ---- スクリプトプロパティのキー名 ----
  PROP: {
    SPREADSHEET_ID: 'SPREADSHEET_ID',          // 台帳スプレッドシートのID
    DRIVE_ROOT_FOLDER_ID: 'DRIVE_ROOT_FOLDER_ID', // 証憑保存先ルート「請求書」フォルダのID
    OPENROUTER_API_KEY: 'OPENROUTER_API_KEY',  // AI抽出用APIキー
    AI_MODEL_PRIMARY: 'AI_MODEL_PRIMARY',      // 一次モデル（安価）例: google/gemini-2.0-flash-001
    AI_MODEL_FALLBACK: 'AI_MODEL_FALLBACK',    // 再抽出モデル（高精度）例: anthropic/claude-3.5-haiku
    APPROVAL_THRESHOLD: 'APPROVAL_THRESHOLD',  // この金額以上は必ず人の承認へ（例: 50000）
    TAX_INPUT_MODE: 'TAX_INPUT_MODE',          // 'NUKI'(税抜入力) または 'KOMI'(税込入力)
  },

  // ---- Gmailラベル名（既存運用に合わせて変更可）----
  LABEL: {
    TARGET: '請求書/未処理',   // 監視対象
    DONE: '請求書/処理済',     // 正常処理済み
    ERROR: '請求書/エラー',    // 取込・保存に失敗
    REVIEW: '請求書/要確認',   // 抽出は出来たが人の確認が必要
  },

  // ---- シート名（templates/ のCSVと一致させること）----
  SHEET: {
    LEDGER: '請求台帳',
    VENDOR: '業者マスタ',
    PATTERN: '仕訳パターン明細',
    ACCOUNT: '勘定科目マスタ',
    LOG: '処理ログ',
  },

  // ---- 請求台帳の列順（templates/請求台帳.csv と完全一致）----
  LEDGER_HEADERS: [
    '請求ID', '取込日時', 'メールID', '請求書連番', '受信日',
    '業者名(生)', '業者名(正規化)', '請求書番号', '登録番号(T)', '請求日',
    '支払期限', '小計(税抜)', '消費税10%', '消費税8%', '消費税合計',
    '合計(税込)', '源泉税', '通貨', '摘要', '借方勘定科目',
    '税区分', '仕訳パターンID', '証憑リンク', '抽出信頼度', '検証結果',
    'ステータス', '承認者', '承認日時', 'MF連携日', '備考',
  ],

  // ---- 運用パラメータ ----
  BATCH_LIMIT: 20,        // 1回の起動で処理する最大件数（GASの6分制限対策）
  CONFIDENCE_MIN: 0.8,    // 参考値（判定の主役は機械検証。§4参照）
  AI_MAX_RETRY: 3,        // AI抽出のリトライ回数

  // ---- ステータス定義 ----
  STATUS: {
    REVIEW: '要確認',
    WAITING: '承認待ち',
    APPROVED: '承認済',
    POSTED: '仕訳済',
    ERROR: 'エラー',
  },
};

/** スクリプトプロパティを取得（未設定なら例外）。 */
function getProp_(key, required) {
  const v = PropertiesService.getScriptProperties().getProperty(key);
  if (required && !v) throw new Error('スクリプトプロパティ未設定: ' + key);
  return v;
}

/**
 * 初期セットアップ確認。必要なプロパティ・シート・ラベルが揃っているか点検し、
 * 不足をログに出す。手動で1回実行してください。
 */
function setupCheck() {
  const sp = PropertiesService.getScriptProperties();
  const missing = [];
  [CONFIG.PROP.SPREADSHEET_ID, CONFIG.PROP.DRIVE_ROOT_FOLDER_ID, CONFIG.PROP.OPENROUTER_API_KEY,
   CONFIG.PROP.AI_MODEL_PRIMARY].forEach(function (k) {
    if (!sp.getProperty(k)) missing.push('プロパティ:' + k);
  });
  if (missing.length === 0) {
    const ss = SpreadsheetApp.openById(sp.getProperty(CONFIG.PROP.SPREADSHEET_ID));
    Object.keys(CONFIG.SHEET).forEach(function (k) {
      if (!ss.getSheetByName(CONFIG.SHEET[k])) missing.push('シート:' + CONFIG.SHEET[k]);
    });
  }
  if (missing.length) {
    Logger.log('⚠️ セットアップ不足:\n - ' + missing.join('\n - '));
  } else {
    Logger.log('✅ セットアップOK。トリガー(processInvoices)を設定してください。');
  }
  return missing;
}
