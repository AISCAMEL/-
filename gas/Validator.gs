/**
 * Validator.gs — 機械検証（AIの自己申告に頼らずコードで検算）
 * §3 の V1〜V8 を実装。1つでも引っかかれば「要確認」へ。
 */
function validateExtraction_(d) {
  const errs = [];
  const sub = d.subtotal, tax = d.tax_total, tot = d.total;

  // V1: 合計の検算（±1円は端数として許容）
  if (sub != null && tax != null && tot != null && Math.abs(sub + tax - tot) > 1) {
    errs.push('V1:小計+税≠合計');
  }
  // V2: 税率の妥当性（10% か 8% に概ね一致するか）
  if (sub != null && tax != null && sub > 0) {
    const r10 = Math.abs(tax - Math.round(sub * 0.10));
    const r8 = Math.abs(tax - Math.round(sub * 0.08));
    if (Math.min(r10, r8) > 2 && tax !== 0) errs.push('V2:税率が不審');
  }
  // V4: 推測検知（totalがあるのに紙面で見つけていない）
  if (tot != null && Array.isArray(d.fields_found) && d.fields_found.indexOf('total') < 0) {
    errs.push('V4:合計は推測の可能性');
  }
  // V5: 日付の整合
  if (d.invoice_date && d.due_date && d.invoice_date > d.due_date) {
    errs.push('V5:請求日>支払期限');
  }
  // V6: 必須欠落
  if (tot == null) errs.push('V6:合計欠落');
  if (!d.vendor_name) errs.push('V6:業者名欠落');
  if (!d.invoice_date) errs.push('V6:請求日欠落');
  // V8: インボイス番号形式（あれば）
  if (d.vendor_registration_no && !/^T\d{13}$/.test(String(d.vendor_registration_no).replace(/\s/g, ''))) {
    errs.push('V8:登録番号形式');
  }
  // V3: 明細合計の一致（明細がある場合）
  if (Array.isArray(d.line_items) && d.line_items.length && sub != null) {
    const sum = d.line_items.reduce(function (a, x) { return a + (toAmount_(x.amount) || 0); }, 0);
    if (Math.abs(sum - sub) > 1 && Math.abs(sum - (tot || 0)) > 1) errs.push('V3:明細合計不一致');
  }
  return errs; // 空配列なら検証パス
}

/**
 * V7: 二重請求の疑い（同業者×同合計×請求書番号 or 近接請求日）。
 * 既存台帳行（オブジェクト配列）と照合し、疑いがあればメッセージを返す。
 */
function detectDuplicate_(d, existingRows) {
  for (let i = 0; i < existingRows.length; i++) {
    const r = existingRows[i];
    const sameVendor = r['業者名(正規化)'] && d._normVendor && r['業者名(正規化)'] === d._normVendor;
    if (!sameVendor) continue;
    if (d.invoice_number && r['請求書番号'] && String(r['請求書番号']) === String(d.invoice_number)) {
      return '重複候補:' + r['請求ID'] + '(請求書番号一致)';
    }
    if (d.total != null && toAmount_(r['合計(税込)']) === d.total) {
      return '重複候補:' + r['請求ID'] + '(同額)';
    }
  }
  return '';
}
