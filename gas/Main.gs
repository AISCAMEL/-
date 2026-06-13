/**
 * Main.gs — 全体制御（時間トリガーのエントリポイント）
 *
 * 処理の流れ（§8-1）:
 *  1. ラベル付き未処理メールを取得
 *  2. メールID重複チェック（冪等性）
 *  3. 添付/本文の取得
 *  4. 送信元から業者特定（ヒント）→ AI抽出
 *  5. 機械検証（V1〜V8）
 *  6. Drive保存
 *  7. 仕訳組み立て
 *  8. 台帳記録（ステータス判定）
 *  9. ラベル張替
 * 10. ログ
 *
 * トリガー設定: processInvoices を「時間主導 → 1時間ごと」で登録。
 */
function processInvoices() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(10000)) { Logger.log('別実行中のためスキップ'); return; }
  try {
    const vendors = loadVendors_();
    const patterns = loadPatterns_();
    const ledger = loadLedger_();
    const threshold = parseInt(getProp_(CONFIG.PROP.APPROVAL_THRESHOLD, false) || '0', 10);

    const messages = fetchTargetMessages_(CONFIG.BATCH_LIMIT);
    Logger.log('対象メッセージ: ' + messages.length + '件');

    for (let i = 0; i < messages.length; i++) {
      const msg = messages[i];
      const mailId = msg.getId();
      if (isMailProcessed_(ledger, mailId)) continue; // 重複防止

      try {
        processOneMessage_(msg, vendors, patterns, ledger, threshold);
      } catch (e) {
        Logger.log('メッセージ処理失敗 ' + mailId + ': ' + e);
        logRow_(mailId, '取込', false, String(e).slice(0, 400));
        try { moveLabel_(msg, CONFIG.LABEL.ERROR); } catch (e2) {}
      }
    }
  } finally {
    lock.releaseLock();
  }
}

/** 1メッセージ分の処理。 */
function processOneMessage_(msg, vendors, patterns, ledger, threshold) {
  const mailId = msg.getId();
  const payload = extractPayload_(msg);
  logRow_(mailId, '取込', true, '添付' + payload.files.length + '件');

  // 添付も本文も無ければエラー隔離（§9-1）
  if (!payload.files.length && (!payload.bodyText || payload.bodyText.length < 20)) {
    logRow_(mailId, '抽出', false, '添付・本文ともに内容なし');
    moveLabel_(msg, CONFIG.LABEL.ERROR);
    return;
  }

  // 送信元から業者を特定（抽出ヒント）
  const vendorByEmail = findVendorByEmail_(vendors, payload.from);

  // AI抽出
  const ext = aiExtract_(payload, vendorByEmail);
  logRow_(mailId, '抽出', true, 'total=' + ext.total + ' model=' + ext._model);

  // 業者の確定（送信元優先、なければ抽出名で名寄せ）
  const vendor = vendorByEmail || findVendorByName_(vendors, ext.vendor_name);
  ext._normVendor = vendor ? vendor.norm : (ext.vendor_name || '');

  // 機械検証
  const errs = validateExtraction_(ext);
  const dup = detectDuplicate_(ext, ledger);
  if (dup) errs.push(dup);
  const verifyResult = errs.length ? errs.join(' / ') : 'OK';
  logRow_(mailId, '検証', errs.length === 0, verifyResult);

  // レコード組み立て
  const invoiceId = nextInvoiceId_(ledger);
  const rec = buildLedgerRecord_(invoiceId, mailId, payload, ext, vendor, verifyResult);

  // Drive保存
  try {
    rec['証憑リンク'] = saveEvidence_(payload, rec);
    logRow_(mailId, '保存', true, rec['証憑リンク']);
  } catch (e) {
    rec['証憑リンク'] = '';
    rec['備考'] = (rec['備考'] || '') + ' [証憑未保存:' + e + ']';
    logRow_(mailId, '保存', false, String(e).slice(0, 200));
  }

  // ステータス判定（§4）
  rec['ステータス'] = decideStatus_(ext, vendor, errs, threshold);

  // 台帳記録
  appendLedger_(rec);
  ledger.push(rec); // 同一バッチ内の重複検知にも反映

  // ラベル張替
  const toLabel = rec['ステータス'] === CONFIG.STATUS.ERROR ? CONFIG.LABEL.ERROR
                : rec['ステータス'] === CONFIG.STATUS.REVIEW ? CONFIG.LABEL.REVIEW
                : CONFIG.LABEL.DONE;
  moveLabel_(msg, toLabel);
}

/** 台帳の1レコード（ヘッダ名キー）を組み立てる。 */
function buildLedgerRecord_(invoiceId, mailId, payload, ext, vendor, verifyResult) {
  return {
    '請求ID': invoiceId,
    '取込日時': nowStr_(),
    'メールID': mailId,
    '請求書連番': 1,
    '受信日': Utilities.formatDate(payload.date, 'Asia/Tokyo', 'yyyy-MM-dd'),
    '業者名(生)': ext.vendor_name || '',
    '業者名(正規化)': ext._normVendor || '',
    '請求書番号': ext.invoice_number || '',
    '登録番号(T)': ext.vendor_registration_no || '',
    '請求日': ext.invoice_date || '',
    '支払期限': ext.due_date || '',
    '小計(税抜)': ext.subtotal,
    '消費税10%': ext.tax_10,
    '消費税8%': ext.tax_8,
    '消費税合計': ext.tax_total,
    '合計(税込)': ext.total,
    '源泉税': ext.withholding_tax,
    '通貨': ext.currency,
    '摘要': ext.description || '',
    '借方勘定科目': (vendor && vendor.drAccount) || '',
    '税区分': (vendor && vendor.taxClass) || '',
    '仕訳パターンID': (vendor && vendor.pattern) || defaultPatternId_(),
    '証憑リンク': '',
    '抽出信頼度': ext.confidence,
    '検証結果': verifyResult,
    'ステータス': '',
    '承認者': '',
    '承認日時': '',
    'MF連携日': '',
    '備考': ext.ambiguous_notes || '',
  };
}

/**
 * ステータス判定（§4）:
 *  自動承認OK = 検証ゼロ件 AND 業者マスタ登録済(自動承認可) AND 金額が閾値未満
 *  それ以外 = 要確認
 */
function decideStatus_(ext, vendor, errs, threshold) {
  if (errs.length) return CONFIG.STATUS.REVIEW;
  if (!vendor || !vendor.autoOk) return CONFIG.STATUS.REVIEW;        // 新規/未昇格業者は人が見る
  if (threshold && ext.total != null && ext.total >= threshold) return CONFIG.STATUS.REVIEW;
  return CONFIG.STATUS.WAITING; // 承認待ち（軽い最終確認）
}
