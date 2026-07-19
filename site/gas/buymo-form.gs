// ============================================================
//  BUYMO お問い合わせフォーム受信スクリプト v2
//  Google Apps Script (スタンドアロン) に貼り付けて使用
// ============================================================

// ▼ 設定 ――――――――――――――――――――――――――――――――――――――――
var SHEET_NAME        = '問い合わせ';        // スプレッドシートのシート名
var NOTIFY_EMAIL      = 'info@aisjaltd.com'; // 通知先メールアドレス
var DRIVE_FOLDER_NAME = 'BUYMO査定写真';     // Drive の保存先フォルダ名（なければ自動作成）
// ▲ 設定 ――――――――――――――――――――――――――――――――――――――――

/* ---- Drive フォルダ取得（なければ作成） ---- */
function getOrCreateFolder(parentId, name) {
  var parent = parentId ? DriveApp.getFolderById(parentId) : DriveApp.getRootFolder();
  var it = parent.getFoldersByName(name);
  return it.hasNext() ? it.next() : parent.createFolder(name);
}

/* ---- 写真を Drive に保存し URL 配列を返す ---- */
function savePhotosToDrive(photos, label) {
  if (!photos || photos.length === 0) return [];
  var root   = getOrCreateFolder(null, DRIVE_FOLDER_NAME);
  var today  = Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyyMMdd');
  var day    = getOrCreateFolder(root.getId(), today);
  var sub    = getOrCreateFolder(day.getId(), label || 'noname');
  var urls   = [];
  photos.forEach(function (p, i) {
    try {
      var blob = Utilities.newBlob(Utilities.base64Decode(p.data), p.type || 'image/jpeg', (i + 1) + '_' + (p.name || 'photo.jpg'));
      var file = sub.createFile(blob);
      file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      urls.push(file.getUrl());
    } catch (e) {
      urls.push('（保存失敗: ' + e.message + '）');
    }
  });
  return urls;
}

/* ---- メイン受信処理 ---- */
function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);
    var ts   = Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy-MM-dd HH:mm:ss');

    // ── 写真保存 ──────────────────────────────────────────
    var photoUrls = [];
    if (data.photos && data.photos.length > 0) {
      var label = (data.name || 'noname').replace(/[\/\\:\*\?\"\<\>\|]/g, '_');
      photoUrls = savePhotosToDrive(data.photos, label);
    }

    // ── スプレッドシートに記録 ──────────────────────────────
    var ss    = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getSheetByName(SHEET_NAME);

    if (!sheet) {
      sheet = ss.insertSheet(SHEET_NAME);
      sheet.appendRow(['受信日時', '氏名', 'メール', '電話', 'ジャンル', '流入元', '写真枚数', '写真リンク', 'メッセージ']);
      sheet.getRange(1, 1, 1, 9).setFontWeight('bold').setBackground('#0A6B3C').setFontColor('#ffffff');
      sheet.setFrozenRows(1);
    }

    sheet.appendRow([
      ts,
      data.name    || '',
      data.email   || '',
      data.phone   || '',
      data.genre   || '',
      data.source  || '',
      photoUrls.length,
      photoUrls.join('\n'),
      data.message || ''
    ]);

    // ── メール通知 ─────────────────────────────────────────
    var subject = '【BUYMO】新しいお問い合わせ：' + (data.name || '（氏名未入力）');
    var photoSection = photoUrls.length > 0
      ? '写真：' + photoUrls.length + '枚\n' + photoUrls.map(function (u, i) { return '  写真' + (i + 1) + ': ' + u; }).join('\n')
      : '写真：なし';

    var body = [
      '■ BUYMOに新しいお問い合わせが届きました',
      '',
      '受信日時　：' + ts,
      '氏名　　　：' + (data.name    || '—'),
      'メール　　：' + (data.email   || '—'),
      '電話番号　：' + (data.phone   || '—'),
      'ジャンル　：' + (data.genre   || '—'),
      '流入元　　：' + (data.source  || '—'),
      photoSection,
      '',
      '─── メッセージ ───',
      data.message || '（内容なし）',
      '',
      'スプレッドシート：',
      SpreadsheetApp.getActiveSpreadsheet().getUrl()
    ].join('\n');

    MailApp.sendEmail({ to: NOTIFY_EMAIL, subject: subject, body: body });

    return ContentService
      .createTextOutput(JSON.stringify({ status: 'ok', photos: photoUrls.length }))
      .setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService
      .createTextOutput(JSON.stringify({ status: 'error', message: err.message }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

// テスト用（エディタから実行して動作確認）
function testPost() {
  var fakeEvent = {
    postData: {
      contents: JSON.stringify({
        type: 'buymo',
        name: 'テスト 太郎',
        email: 'test@example.com',
        phone: '090-0000-0000',
        genre: '廃車・不動車',
        source: 'テスト実行',
        message: 'これはテスト送信です。',
        photos: []
      })
    }
  };
  Logger.log(doPost(fakeEvent).getContent());
}
