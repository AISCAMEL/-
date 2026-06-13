/**
 * MfExporter.gs — 承認済み行をマネーフォワード仕訳インポート用CSVに変換
 *
 * ⚠️ 重要：MFの仕訳インポートCSVの列名・順序・税区分表記は製品/版で異なる。
 * 実装確定前に「MF管理画面からDLした仕訳インポートテンプレCSV」に合わせて
 * MF_HEADER と buildMfRow_() を調整すること（確認事項#13-A）。
 * 下記は代表的なMFクラウド会計フォーマットを想定した暫定値。
 */
const MF_HEADER = [
  '取引No', '取引日', '借方勘定科目', '借方補助科目', '借方税区分', '借方金額',
  '貸方勘定科目', '貸方補助科目', '貸方税区分', '貸方金額', '摘要', '仕訳メモ',
];

/**
 * 「承認済」かつ「MF連携日」が空の行を集め、MF用CSV文字列を生成。
 * 生成後、対象行を「仕訳済」に更新し MF連携日 を記録する。
 * @param {boolean} markPosted trueで台帳を仕訳済に更新（既定true）
 * @return {Object} { csv, count }
 */
function exportMfCsv_(markPosted) {
  if (markPosted === undefined) markPosted = true;
  const vendors = loadVendors_();
  const patterns = loadPatterns_();
  const sh = ledgerSheet_();
  const values = sh.getDataRange().getValues();
  const headers = values[0];
  const col = {};
  headers.forEach(function (h, i) { col[h] = i; });

  const lines = [MF_HEADER.join(',')];
  let txNo = 1, count = 0;
  const updates = []; // {rowIndex}

  for (let i = 1; i < values.length; i++) {
    const row = values[i];
    if (String(row[col['ステータス']]) !== CONFIG.STATUS.APPROVED) continue;
    if (row[col['MF連携日']]) continue;

    const ext = rowToExt_(row, col);
    const vendor = findVendorByName_(vendors, ext._normVendor) ||
                   findVendorByName_(vendors, ext.vendor_name);
    const journal = buildJournal_(ext, vendor, patterns);
    if (!journal.length) continue;

    // 借方行・貸方行をMFの「複合仕訳」形式（同一取引Noで複数行）に展開
    journal.forEach(function (j) {
      const isDr = j.side === '借方';
      lines.push(buildMfRow_(txNo, ext, j, isDr));
    });
    txNo++;
    count++;
    updates.push(i + 1); // シート行番号（1始まり、ヘッダ込み）
  }

  if (markPosted) {
    const today = Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy-MM-dd');
    updates.forEach(function (r) {
      sh.getRange(r, col['ステータス'] + 1).setValue(CONFIG.STATUS.POSTED);
      sh.getRange(r, col['MF連携日'] + 1).setValue(today);
    });
  }
  return { csv: lines.join('\n'), count: count };
}

/** 台帳1行 → JournalBuilderが使うext相当オブジェクトに復元。 */
function rowToExt_(row, col) {
  return {
    vendor_name: row[col['業者名(生)']],
    _normVendor: row[col['業者名(正規化)']],
    invoice_number: row[col['請求書番号']],
    invoice_date: row[col['請求日']],
    subtotal: toAmount_(row[col['小計(税抜)']]),
    tax_10: toAmount_(row[col['消費税10%']]),
    tax_8: toAmount_(row[col['消費税8%']]),
    tax_total: toAmount_(row[col['消費税合計']]),
    total: toAmount_(row[col['合計(税込)']]),
    withholding_tax: toAmount_(row[col['源泉税']]) || 0,
    description: row[col['摘要']],
    _drAccount: row[col['借方勘定科目']],
    _invoiceId: row[col['請求ID']],
  };
}

/** MF1行を生成（借方行 or 貸方行）。 */
function buildMfRow_(txNo, ext, j, isDr) {
  const摘要 = csvQuote_([ext._normVendor || ext.vendor_name, ext.description, ext.invoice_number ? '#' + ext.invoice_number : '']
    .filter(String).join(' '));
  const cells = [
    txNo, ext.invoice_date,
    isDr ? csvQuote_(j.account) : '', isDr ? csvQuote_(j.sub) : '', isDr ? csvQuote_(j.tax) : '', isDr ? j.amount : '',
    !isDr ? csvQuote_(j.account) : '', !isDr ? csvQuote_(j.sub) : '', !isDr ? csvQuote_(j.tax) : '', !isDr ? j.amount : '',
    摘要, csvQuote_(ext._invoiceId),
  ];
  return cells.join(',');
}

function csvQuote_(s) {
  s = String(s == null ? '' : s);
  return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}

/**
 * 手動実行用：当月承認済みをCSV化し、Driveに保存して通知。
 * メニューやボタンから呼ぶ想定。
 */
function generateMfExportFile() {
  const out = exportMfCsv_(true);
  if (out.count === 0) { Logger.log('対象（承認済・未連携）なし'); return; }
  const root = DriveApp.getFolderById(getProp_(CONFIG.PROP.DRIVE_ROOT_FOLDER_ID, true));
  const name = 'MF仕訳_' + Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyyMMdd_HHmm') + '.csv';
  // BOM付きUTF-8（MFの文字化け回避）
  const blob = Utilities.newBlob('﻿' + out.csv, 'text/csv', name);
  const file = root.createFile(blob);
  logRow_('', '仕訳', true, 'MF用CSV出力 ' + out.count + '件: ' + file.getUrl());
  Logger.log('✅ ' + out.count + '件を出力: ' + file.getUrl());
}
