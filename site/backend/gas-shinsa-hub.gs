/**
 * =====================================================================
 *  かんたんWEB審査 中継ハブ（Google Apps Script）
 *
 *  1回の申込を、以下4か所へ自動配信します：
 *    ① Google スプレッドシート（申込台帳に1行追加）
 *    ② Slack（#ローン審査 へ新規申込を通知）
 *    ③ Asana（カーメルFC ボードにタスク作成）
 *    ④ WordPress（既存の carmel_shinsa_submit へ転送・任意）
 *  添付画像（免許証など）は Google Drive に保存し、各所にリンクを添付。
 *
 *  ■ 使い方
 *   1. script.google.com で新規プロジェクトを作成し、このコードを貼り付け
 *   2. 下の CONFIG を自分の値に書き換え
 *   3. 「デプロイ」→「新しいデプロイ」→ 種類「ウェブアプリ」
 *        実行するユーザー：自分
 *        アクセスできるユーザー：全員
 *      → 発行された /exec のURLを shinsa.html の window.SHINSA_ENDPOINT に貼る
 *   4. 各連携をオン/オフしたい時は CONFIG の SEND_TO を true/false で切替
 *
 *  ※ 手順の詳細は同フォルダの「連携セットアップ手順.md」を参照。
 * =====================================================================
 */

/* ============================ CONFIG ============================ */
var CONFIG = {
  /* どこに配信するか（慣れるまで全部true、不要になったら個別にfalse） */
  SEND_TO: { SHEET: true, SLACK: true, ASANA: true, WORDPRESS: false },

  /* ① スプレッドシート（申込台帳）*/
  SHEET_ID:   'ここにスプレッドシートのIDを貼る',   // URLの /d/ と /edit の間の文字列
  SHEET_NAME: '審査申込',                          // タブ名（なければ自動作成）

  /* 添付ファイル保存先（Google Drive のフォルダID）*/
  DRIVE_FOLDER_ID: 'ここにDriveフォルダのIDを貼る', // フォルダURL末尾の文字列

  /* ② Slack（Incoming Webhook。#ローン審査 用に発行したURL）*/
  SLACK_WEBHOOK_URL: 'https://hooks.slack.com/services/XXX/YYY/ZZZ',

  /* ③ Asana */
  ASANA_TOKEN:         'ここにAsana個人アクセストークンを貼る',
  ASANA_PROJECT_GID:   '1213728225406661', // カーメルFC ボード
  ASANA_WORKSPACE_GID: '1200230966586916', // ワークスペース

  /* ④ WordPress（任意。使う場合のみ）*/
  WORDPRESS_ENDPOINT: 'https://carmelonline.jp/wp-admin/admin-ajax.php',
  WORDPRESS_SECRET:   '',   // WP側で照合する共有シークレット（任意）

  /* スパム対策・管理 */
  ADMIN_EMAIL: 'info@aisjaltd.com', // エラー時の通知先
  TIMEZONE:    'Asia/Tokyo'
};
/* ========================== /CONFIG ============================= */


/** フォームからのPOSTを受ける入口 */
function doPost(e) {
  try {
    var payload = JSON.parse((e && e.postData && e.postData.contents) || '{}');

    /* ハニーポット（bot対策）：hp に値があれば無視して成功を返す */
    if (payload.hp) { return json_({ success: true }); }

    var record = buildRecord_(payload);

    /* 添付ファイルをDriveへ保存 → リンク配列 */
    record.fileLinks = saveFiles_(payload.files || [], record);

    var results = {};
    if (CONFIG.SEND_TO.SHEET)     { results.sheet     = safe_(function(){ return appendToSheet_(record); }); }
    if (CONFIG.SEND_TO.SLACK)     { results.slack     = safe_(function(){ return notifySlack_(record); }); }
    if (CONFIG.SEND_TO.ASANA)     { results.asana     = safe_(function(){ return createAsanaTask_(record); }); }
    if (CONFIG.SEND_TO.WORDPRESS) { results.wordpress = safe_(function(){ return forwardWordPress_(record, payload); }); }

    return json_({ success: true, results: results });
  } catch (err) {
    notifyError_('doPost失敗: ' + err);
    return json_({ success: false, message: '送信処理でエラーが発生しました。' });
  }
}

/** 動作確認用（ブラウザで /exec を開くとOK表示）*/
function doGet() {
  return ContentService.createTextOutput('かんたんWEB審査 中継ハブ：稼働中')
    .setMimeType(ContentService.MimeType.TEXT);
}

/* ---------------- データ整形 ---------------- */
function buildRecord_(payload) {
  var m = payload.meta || {};
  var fields = payload.fields || [];
  var now = new Date();
  var stamp = Utilities.formatDate(now, CONFIG.TIMEZONE, 'yyyy-MM-dd HH:mm:ss');

  /* セクションごとに整形したテキスト（Slack/Asana本文・シート用）*/
  var lines = [], curSec = '';
  fields.forEach(function(f) {
    if (f.section && f.section !== curSec) { curSec = f.section; lines.push('■ ' + curSec); }
    lines.push('　' + (f.label || '') + '：' + (f.value || ''));
  });
  var body = lines.join('\n');

  return {
    stamp: stamp,
    name:  m.name || '（氏名未取得）',
    kana:  m.kana || '',
    phone: m.phone || '',
    email: m.email || '',
    car:   m.car || '',
    body:  body,
    fields: fields,
    fileLinks: []
  };
}

/* ---------------- ① スプレッドシート ---------------- */
function appendToSheet_(r) {
  var ss = SpreadsheetApp.openById(CONFIG.SHEET_ID);
  var sh = ss.getSheetByName(CONFIG.SHEET_NAME) || ss.insertSheet(CONFIG.SHEET_NAME);
  if (sh.getLastRow() === 0) {
    sh.appendRow(['受付日時','氏名','フリガナ','電話','メール','希望車','内容','添付ファイル']);
  }
  var links = r.fileLinks.map(function(f){ return f.label + '： ' + f.url; }).join('\n');
  sh.appendRow([r.stamp, r.name, r.kana, r.phone, r.email, r.car, r.body, links]);
  return 'ok';
}

/* ---------------- ② Slack ---------------- */
function notifySlack_(r) {
  var files = r.fileLinks.length
    ? r.fileLinks.map(function(f){ return '• <' + f.url + '|' + f.label + '>'; }).join('\n')
    : '（添付なし）';
  var text =
    '*新規WEB審査申込* :memo:\n' +
    '*氏名：* ' + r.name + '（' + r.kana + '）\n' +
    '*電話：* ' + r.phone + '　*メール：* ' + r.email + '\n' +
    '*希望車：* ' + (r.car || '未定') + '\n' +
    '*受付：* ' + r.stamp + '\n' +
    '——————————————\n' +
    r.body + '\n' +
    '——————————————\n' +
    '*添付書類：*\n' + files;

  var res = UrlFetchApp.fetch(CONFIG.SLACK_WEBHOOK_URL, {
    method: 'post', contentType: 'application/json',
    payload: JSON.stringify({ text: text }), muteHttpExceptions: true
  });
  return res.getResponseCode() === 200 ? 'ok' : ('slack:' + res.getResponseCode());
}

/* ---------------- ③ Asana ---------------- */
function createAsanaTask_(r) {
  var notes =
    '受付日時：' + r.stamp + '\n' +
    '電話：' + r.phone + '　メール：' + r.email + '\n' +
    '希望車：' + (r.car || '未定') + '\n\n' +
    r.body + '\n\n' +
    '【添付書類】\n' +
    (r.fileLinks.length ? r.fileLinks.map(function(f){ return '・' + f.label + '： ' + f.url; }).join('\n') : '（添付なし）');

  var res = UrlFetchApp.fetch('https://app.asana.com/api/1.0/tasks', {
    method: 'post', contentType: 'application/json',
    headers: { Authorization: 'Bearer ' + CONFIG.ASANA_TOKEN },
    payload: JSON.stringify({ data: {
      name: '【審査申込】' + r.name + '　' + (r.car || '') + '（' + r.stamp + '）',
      notes: notes,
      projects: [CONFIG.ASANA_PROJECT_GID],
      workspace: CONFIG.ASANA_WORKSPACE_GID
    }}),
    muteHttpExceptions: true
  });
  var code = res.getResponseCode();
  return (code === 200 || code === 201) ? 'ok' : ('asana:' + code + ' ' + res.getContentText());
}

/* ---------------- ④ WordPress（任意） ---------------- */
function forwardWordPress_(r, payload) {
  var fd = {
    action: 'carmel_shinsa_submit',
    secret: CONFIG.WORDPRESS_SECRET,
    name: r.name, kana: r.kana, phone: r.phone, email: r.email,
    body: r.body,
    files: JSON.stringify(r.fileLinks)
  };
  var res = UrlFetchApp.fetch(CONFIG.WORDPRESS_ENDPOINT, {
    method: 'post', payload: fd, muteHttpExceptions: true
  });
  var code = res.getResponseCode();
  return (code >= 200 && code < 300) ? 'ok' : ('wp:' + code);
}

/* ---------------- 添付ファイル → Drive ---------------- */
function saveFiles_(files, r) {
  if (!files.length) return [];
  var parent = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
  var folderName = r.stamp.replace(/[:\/]/g,'-') + '_' + (r.name || '申込者');
  var folder = parent.createFolder(folderName);
  var links = [];
  files.forEach(function(f, i) {
    try {
      var bytes = Utilities.base64Decode(f.dataBase64);
      var blob = Utilities.newBlob(bytes, f.mime || 'application/octet-stream', f.name || ('file'+i));
      var file = folder.createFile(blob);
      file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      links.push({ label: f.label || ('書類'+(i+1)), url: file.getUrl() });
    } catch (err) {
      links.push({ label: (f.label||'書類') + '（保存失敗）', url: '' });
    }
  });
  return links;
}

/* ---------------- ユーティリティ ---------------- */
function json_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
function safe_(fn) { try { return fn(); } catch (e) { notifyError_(String(e)); return 'error: ' + e; } }
function notifyError_(msg) {
  try { if (CONFIG.ADMIN_EMAIL) MailApp.sendEmail(CONFIG.ADMIN_EMAIL, '【審査フォーム】配信エラー', msg); } catch (e) {}
}

/* ---------------- 動作テスト（エディタから実行）----------------
   Asana/Slack/シート/Drive の設定が正しいか、ダミーデータで確認できます。 */
function TEST_run() {
  var demo = {
    meta: { name:'テスト太郎', kana:'テストタロウ', phone:'09012345678', email:'test@example.com', car:'トヨタ アクア' },
    fields: [
      { section:'STEP1 ご希望のお車', label:'ご希望車種', value:'アクア' },
      { section:'STEP3 ご本人情報', label:'お名前', value:'テスト太郎' }
    ],
    files: []
  };
  var record = buildRecord_(demo);
  record.fileLinks = [];
  if (CONFIG.SEND_TO.SHEET) Logger.log('sheet: ' + safe_(function(){ return appendToSheet_(record); }));
  if (CONFIG.SEND_TO.SLACK) Logger.log('slack: ' + safe_(function(){ return notifySlack_(record); }));
  if (CONFIG.SEND_TO.ASANA) Logger.log('asana: ' + safe_(function(){ return createAsanaTask_(record); }));
}
