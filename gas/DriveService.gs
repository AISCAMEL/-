/**
 * DriveService.gs — 証憑（添付）をDriveへ業者別・年月別に保存
 * フォルダ規則: 請求書/{業者名}/{YYYY}/{YYYYMM}/
 * ファイル名: {YYYYMMDD}_{業者名}_{合計}_{請求ID}.pdf
 */

/**
 * 添付Blob群を保存し、代表ファイルのURLを返す。
 * 添付が無い場合は本文をテキストファイルとして保存する。
 */
function saveEvidence_(payload, rec) {
  const root = DriveApp.getFolderById(getProp_(CONFIG.PROP.DRIVE_ROOT_FOLDER_ID, true));
  const vendorName = rec['業者名(正規化)'] || rec['業者名(生)'] || '_未分類';
  const ym = ymParts_(rec['請求日'] || rec['受信日']);
  const folder = getOrCreateChild_(getOrCreateChild_(getOrCreateChild_(root, safeName_(vendorName)), ym.y), ym.ym);

  const base = ym.ymd + '_' + safeName_(vendorName) + '_' + (rec['合計(税込)'] || 0) + '_' + rec['請求ID'];
  let url = '';

  if (payload.files && payload.files.length) {
    payload.files.forEach(function (f, idx) {
      const ext = extByType_(f.getContentType()) || '';
      const name = base + (payload.files.length > 1 ? '_' + (idx + 1) : '') + ext;
      const file = folder.createFile(f.copyBlob().setName(name));
      file.setDescription('原本: ' + f.getName() + ' / メールID: ' + rec['メールID']);
      if (!url) url = file.getUrl();
    });
  } else {
    const file = folder.createFile(base + '_本文.txt', payload.bodyText || '(本文なし)', 'text/plain');
    url = file.getUrl();
  }
  return url;
}

function getOrCreateChild_(parent, name) {
  const it = parent.getFoldersByName(name);
  return it.hasNext() ? it.next() : parent.createFolder(name);
}

function extByType_(ct) {
  if (/pdf/i.test(ct)) return '.pdf';
  if (/png/i.test(ct)) return '.png';
  if (/jpe?g/i.test(ct)) return '.jpg';
  if (/gif/i.test(ct)) return '.gif';
  if (/webp/i.test(ct)) return '.webp';
  return '';
}
