// ============================================================
//  BUYMO お問い合わせフォーム受信スクリプト
//  Google Apps Script (スタンドアロン) に貼り付けて使用
// ============================================================

// ▼ 設定 ――――――――――――――――――――――――――――――――――――――――
var SHEET_NAME   = '問い合わせ';        // スプレッドシートのシート名
var NOTIFY_EMAIL = 'info@aisjaltd.com'; // 通知先メールアドレス
// ▲ 設定 ――――――――――――――――――――――――――――――――――――――――

function doPost(e) {
  try {
    var data   = JSON.parse(e.postData.contents);
    var ts     = Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy-MM-dd HH:mm:ss');

    // ── スプレッドシートに記録 ───────────────────────────────
    var ss     = SpreadsheetApp.getActiveSpreadsheet();
    var sheet  = ss.getSheetByName(SHEET_NAME);

    if (!sheet) {
      sheet = ss.insertSheet(SHEET_NAME);
      sheet.appendRow(['受信日時', '氏名', 'メール', '電話', 'ジャンル', '流入元', 'メッセージ']);
      sheet.getRange(1, 1, 1, 7).setFontWeight('bold').setBackground('#1C3030').setFontColor('#ffffff');
      sheet.setFrozenRows(1);
    }

    sheet.appendRow([
      ts,
      data.name    || '',
      data.email   || '',
      data.phone   || '',
      data.genre   || '',
      data.source  || '',
      data.message || ''
    ]);

    // ── メール通知 ─────────────────────────────────────────
    var subject = '【BUYMO】新しいお問い合わせ：' + (data.name || '（氏名未入力）');
    var body = [
      '■ BUYMOに新しいお問い合わせが届きました',
      '',
      '受信日時　：' + ts,
      '氏名　　　：' + (data.name    || '—'),
      'メール　　：' + (data.email   || '—'),
      '電話番号　：' + (data.phone   || '—'),
      'ジャンル　：' + (data.genre   || '—'),
      '流入元　　：' + (data.source  || '—'),
      '',
      '─── メッセージ ───',
      data.message || '（内容なし）',
      '',
      'スプレッドシート：',
      SpreadsheetApp.getActiveSpreadsheet().getUrl()
    ].join('\n');

    MailApp.sendEmail({ to: NOTIFY_EMAIL, subject: subject, body: body });

    return ContentService
      .createTextOutput(JSON.stringify({ status: 'ok' }))
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
        message: 'これはテスト送信です。'
      })
    }
  };
  Logger.log(doPost(fakeEvent).getContent());
}
