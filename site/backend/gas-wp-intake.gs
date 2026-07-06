// ============================================================
//  CARMEL WPフォーム取り込み（Google Apps Script）
//
//  目的：WordPress側の審査フォーム（shinsa.html）からの申込を、
//        既存のGoogleフォロー用スプレッドシートと「同じファイル」に転写する。
//        ・Googleフォーム由来の本番フロー（v5.5）は一切触りません。
//        ・別タブ（既定：「WP申込」）に書き込むため衝突しません。
//        ・「申込ソース」列で Google / WP を区別できます。
//        ・項目が増減しても、列名ベースで自動的に列を拡張・追加します。
//
//  これは「別のGASプロジェクト」として新規デプロイして使います
//  （本番プロジェクトに手を入れないための構成）。
//  スプレッドシートID・DriveフォルダID・Asana設定は本番と同じ値を
//  スクリプトプロパティに入れれば、同じ台帳／同じフォルダに集約されます。
//
//  ■ shinsa.html からは、window.SHINSA_ENDPOINT にこのGASの /exec URL を設定。
//    フォームは JSON（source/meta/fields/files/hp）を POST します。
// ============================================================

// ===== 設定（スクリプトプロパティ推奨。無ければ下の既定値） =====
function getWpConfig_() {
  var p = PropertiesService.getScriptProperties().getProperties();
  return {
    // 既存Googleフローと「同じ」スプレッド／フォルダを指定すれば台帳が集約される
    CARMEL_SHEET_ID:  p['CARMEL_SHEET_ID']  || '',                 // 転写先スプレッドシートID（本番と同じ）
    WP_SHEET_TAB:     p['WP_SHEET_TAB']      || 'WP申込',          // 書き込むタブ名（別タブ＝安全）
    DRIVE_FOLDER_ID:  p['DRIVE_FOLDER_ID']  || '',                 // 添付保存先（本番と同じでOK）

    // 任意：Slack通知（#ローン審査 用 Incoming Webhook）
    SLACK_WEBHOOK_URL: p['SLACK_WEBHOOK_URL'] || '',

    // 任意：AsanaにもWP申込をタスク化したい場合（本番と同じトークン/プロジェクトでOK）
    ASANA_TOKEN:       p['ASANA_TOKEN']       || '',
    ASANA_PROJECT_ID:  p['ASANA_PROJECT_ID']  || '',               // 例：カーメルFC 1213728225406661
    ASANA_SECTION_ID:  p['ASANA_SECTION_ID']  || '',

    // 配信のオン/オフ（慣れるまで全部オン、不要になったら false）
    SEND_ASANA: (p['WP_SEND_ASANA'] || 'false') === 'true',
    SEND_SLACK: (p['WP_SEND_SLACK'] || 'false') === 'true',

    NOTIFY_EMAIL: p['NOTIFY_EMAIL'] || 'info@aisjaltd.com',
    TZ: 'Asia/Tokyo',

    // このソース行に入れる表示名（区別用）
    SOURCE_LABEL: 'WPフォーム'
  };
}

// 主要項目の列名を、既存Google台帳の呼び方に寄せるためのマップ（統合時に揃いやすくする）
var WP_META_COLMAP = {
  name:  'お名前（フルネーム）',
  kana:  'フリガナ',
  phone: '電話番号',
  email: 'メールアドレス',
  car:   'ご希望の車種'
};

// ============================================================
// 入口
// ============================================================
function doPost(e) {
  try {
    var cfg = getWpConfig_();
    var raw = (e && e.postData && e.postData.contents) || '{}';
    var payload = JSON.parse(raw);

    // ハニーポット（bot）：hpに値があれば無視して成功扱い
    if (payload.hp) return jsonOut_({ success: true, skipped: 'honeypot' });

    var rec = buildWpRecord_(cfg, payload);

    // 添付（base64）→ Drive保存 → リンク
    // ※画像保存が失敗しても、申込データは必ずスプレッドシートに残すため try で囲む
    try {
      rec.fileLinks = saveWpFiles_(cfg, payload.files || [], rec);
    } catch (fe) {
      rec.fileLinks = [{ label: '画像保存エラー', url: String(fe) }];
      notifyWpError_('ファイル保存でエラー（申込は記録します）: ' + fe);
    }

    // ① 同じスプレッドシートの別タブへ、列名ベースで転写（自動列拡張）
    writeWpRowDynamic_(cfg, rec);

    // ② 任意：Slack通知
    if (cfg.SEND_SLACK && cfg.SLACK_WEBHOOK_URL) safeRun_(cfg, function(){ notifyWpSlack_(cfg, rec); });

    // ③ 任意：Asanaタスク
    if (cfg.SEND_ASANA && cfg.ASANA_TOKEN && cfg.ASANA_PROJECT_ID) safeRun_(cfg, function(){ createWpAsana_(cfg, rec); });

    return jsonOut_({ success: true, uid: rec.uid });
  } catch (err) {
    notifyWpError_('doPost失敗: ' + err);
    return jsonOut_({ success: false, message: '送信処理でエラーが発生しました。' });
  }
}

function doGet() {
  return ContentService.createTextOutput('CARMEL WP取り込み：稼働中')
    .setMimeType(ContentService.MimeType.TEXT);
}

// ============================================================
// レコード整形
// ============================================================
function buildWpRecord_(cfg, payload) {
  var m = payload.meta || {};
  var fields = payload.fields || [];
  var now = new Date();
  var stamp = Utilities.formatDate(now, cfg.TZ, 'yyyy/MM/dd HH:mm:ss');
  var uid = 'WP-' + now.getTime() + '-' + Math.random().toString(36).slice(2, 7).toUpperCase();

  // 表示・通知用の本文
  var lines = [], sec = '';
  fields.forEach(function(f) {
    if (f.section && f.section !== sec) { sec = f.section; lines.push('■ ' + sec); }
    lines.push('　' + (f.label || '') + '：' + (f.value || ''));
  });

  return {
    uid: uid,
    stamp: stamp,
    source: cfg.SOURCE_LABEL,
    name:  m.name  || '',
    kana:  m.kana  || '',
    phone: m.phone || '',
    email: m.email || '',
    car:   m.car   || '',
    fields: fields,
    body: lines.join('\n'),
    fileLinks: []
  };
}

// ============================================================
// ① スプレッドシート転写（列名ベース・自動拡張）
//    - 既存の列順は絶対に変えない（新規列は右端に追加するだけ）
//    - なので既存Googleタブの書き込みと同居しても崩れない
// ============================================================
function writeWpRowDynamic_(cfg, rec) {
  if (!cfg.CARMEL_SHEET_ID) throw new Error('CARMEL_SHEET_ID 未設定');
  var ss = SpreadsheetApp.openById(cfg.CARMEL_SHEET_ID);
  var sheet = ss.getSheetByName(cfg.WP_SHEET_TAB) || ss.insertSheet(cfg.WP_SHEET_TAB);

  // 書き込むデータを「列名 → 値」の順序付きで用意
  var dataObj = {};
  dataObj['申込ソース'] = rec.source;   // ← Google/WPの区別
  dataObj['UID']        = rec.uid;
  dataObj['受付日時']   = rec.stamp;
  dataObj[WP_META_COLMAP.name]  = rec.name;
  dataObj[WP_META_COLMAP.kana]  = rec.kana;
  dataObj[WP_META_COLMAP.phone] = rec.phone;
  dataObj[WP_META_COLMAP.email] = rec.email;
  dataObj[WP_META_COLMAP.car]   = rec.car;

  // フォームの各項目（セクション名でユニーク化して列に）
  rec.fields.forEach(function(f) {
    var col = (f.section ? f.section + ' / ' : '') + (f.label || '');
    if (!col) return;
    // 同名列が複数来た場合は改行で連結
    dataObj[col] = dataObj[col] ? (dataObj[col] + ' / ' + f.value) : f.value;
  });

  // 添付リンク（書類ラベルごとに列）
  rec.fileLinks.forEach(function(fl) {
    var col = '書類：' + fl.label;
    dataObj[col] = dataObj[col] ? (dataObj[col] + '\n' + fl.url) : fl.url;
  });

  appendByHeader_(sheet, dataObj);
}

// ヘッダー（1行目）を辞書に使い、無い列は右端に追加してから値を配置して追記
function appendByHeader_(sheet, dataObj) {
  var lastCol = sheet.getLastColumn();
  var header = lastCol > 0 ? sheet.getRange(1, 1, 1, lastCol).getValues()[0] : [];

  // ヘッダーが空 → dataObjのキーで新規作成
  if (header.length === 0 || String(header[0]).trim() === '') {
    var keys = Object.keys(dataObj);
    sheet.getRange(1, 1, 1, keys.length).setValues([keys])
      .setBackground('#1a2e5a').setFontColor('#ffffff').setFontWeight('bold');
    header = keys;
  }

  // 列名→index
  var idx = {};
  header.forEach(function(h, i) { if (h !== '' && !(h in idx)) idx[h] = i; });

  // 未知の列を右端に追加
  var add = [];
  Object.keys(dataObj).forEach(function(k) { if (!(k in idx)) add.push(k); });
  if (add.length) {
    sheet.getRange(1, header.length + 1, 1, add.length).setValues([add])
      .setBackground('#1a2e5a').setFontColor('#ffffff').setFontWeight('bold');
    add.forEach(function(k, i) { idx[k] = header.length + i; });
    header = header.concat(add);
  }

  // 行を組み立てて追記
  var row = [];
  for (var c = 0; c < header.length; c++) row.push('');
  Object.keys(dataObj).forEach(function(k) { row[idx[k]] = dataObj[k]; });
  sheet.appendRow(row);
}

// ============================================================
// 添付ファイル → Drive
// ============================================================
function saveWpFiles_(cfg, files, rec) {
  if (!files.length || !cfg.DRIVE_FOLDER_ID) return [];
  var main = DriveApp.getFolderById(cfg.DRIVE_FOLDER_ID);
  var userFolder = getOrCreateFolder_(main, rec.name || '名前未設定');            // ① ユーザー名（カテゴリー・同名は再利用）
  var dateLabel  = rec.stamp.replace(/[\/:]/g, '-');                              // 例：2026-07-06 14-30-00
  var subFolder  = userFolder.createFolder(dateLabel + '　' + rec.uid);          // ② 申込日時ごと
  var links = [];
  files.forEach(function(f, i) {
    try {
      var bytes = Utilities.base64Decode(f.dataBase64);
      var blob = Utilities.newBlob(bytes, f.mime || 'application/octet-stream', sanitize_(f.name || ('file' + i)));
      var file = subFolder.createFile(blob);
      file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      links.push({ label: f.label || ('書類' + (i + 1)), url: file.getUrl() });
    } catch (err) {
      links.push({ label: (f.label || '書類') + '（保存失敗）', url: '' });
    }
  });
  return links;
}

// 同名フォルダがあれば再利用、なければ作成（ユーザー名フォルダをカテゴリーとして使う）
function getOrCreateFolder_(parent, name) {
  name = sanitize_(name);
  var it = parent.getFoldersByName(name);
  return it.hasNext() ? it.next() : parent.createFolder(name);
}

// ============================================================
// 任意：Slack通知
// ============================================================
function notifyWpSlack_(cfg, rec) {
  var files = rec.fileLinks.length
    ? rec.fileLinks.map(function(f){ return '• <' + f.url + '|' + f.label + '>'; }).join('\n')
    : '（添付なし）';
  var text =
    '*新規WEB審査申込（WPフォーム）* :memo:\n' +
    '*氏名：* ' + rec.name + '（' + rec.kana + '）\n' +
    '*電話：* ' + rec.phone + '　*メール：* ' + rec.email + '\n' +
    '*希望車：* ' + (rec.car || '未定') + '\n' +
    '*受付：* ' + rec.stamp + '\n' +
    '——————————————\n' + rec.body + '\n' +
    '——————————————\n*添付書類：*\n' + files;
  UrlFetchApp.fetch(cfg.SLACK_WEBHOOK_URL, {
    method: 'post', contentType: 'application/json',
    payload: JSON.stringify({ text: text }), muteHttpExceptions: true
  });
}

// ============================================================
// 任意：Asanaタスク（WP申込と分かる名前で）
// ============================================================
function createWpAsana_(cfg, rec) {
  var notes =
    '【申込ソース】WPフォーム\n' +
    '【受付日時】' + rec.stamp + '\n' +
    '【電話】' + rec.phone + '　【メール】' + rec.email + '\n' +
    '【希望車】' + (rec.car || '未定') + '\n\n' + rec.body + '\n\n【添付書類】\n' +
    (rec.fileLinks.length ? rec.fileLinks.map(function(f){ return '・' + f.label + '： ' + f.url; }).join('\n') : '（添付なし）');
  var payload = { data: {
    name: '【WP新規】' + (rec.name || '名前未設定') + '　様　' + (rec.car || ''),
    notes: notes,
    projects: [cfg.ASANA_PROJECT_ID]
  }};
  if (cfg.ASANA_SECTION_ID) payload.data.memberships = [{ project: cfg.ASANA_PROJECT_ID, section: cfg.ASANA_SECTION_ID }];
  UrlFetchApp.fetch('https://app.asana.com/api/1.0/tasks', {
    method: 'post',
    headers: { 'Authorization': 'Bearer ' + cfg.ASANA_TOKEN, 'Content-Type': 'application/json' },
    payload: JSON.stringify(payload), muteHttpExceptions: true
  });
}

// ============================================================
// ユーティリティ
// ============================================================
function jsonOut_(o) {
  return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON);
}
function sanitize_(n) { return String(n).replace(/[\\/:*?"<>|]/g, '_'); }
function safeRun_(cfg, fn) { try { return fn(); } catch (e) { notifyWpError_(String(e)); } }
function notifyWpError_(msg) {
  try { var cfg = getWpConfig_(); if (cfg.NOTIFY_EMAIL) MailApp.sendEmail(cfg.NOTIFY_EMAIL, '【WP審査フォーム】取り込みエラー', msg); } catch (e) {}
}

// ============================================================
// テスト：ダミーのWP申込を1件、スプレッドシートへ流す
// ============================================================
function TEST_wpIntake() {
  var demo = {
    source: 'shinsa.html',
    meta: { name: 'テスト花子', kana: 'テストハナコ', phone: '09011112222', email: 'wptest@example.com', car: 'ホンダ N-BOX' },
    fields: [
      { section: 'STEP1 ご希望のお車', label: 'ご希望車種', value: 'N-BOX' },
      { section: 'STEP3 ご本人情報', label: 'お名前', value: 'テスト花子' },
      { section: 'STEP9 お支払いのご希望', label: '希望支払い回数', value: '60回(5年)' }
    ],
    files: []
  };
  var cfg = getWpConfig_();
  var rec = buildWpRecord_(cfg, demo);
  rec.fileLinks = [];
  writeWpRowDynamic_(cfg, rec);
  Logger.log('WP申込タブに1行追記しました：' + cfg.WP_SHEET_TAB);
}

// ドライブ保存だけを単体テスト（承認確認・フォルダ生成の確認用）
function TEST_driveSave() {
  var cfg = getWpConfig_();
  Logger.log('DRIVE_FOLDER_ID = ' + cfg.DRIVE_FOLDER_ID);
  var rec = buildWpRecord_(cfg, { meta: { name: 'ドライブ確認太郎' }, fields: [] });
  var files = [{ label: '運転免許証（表）', name: 't.png', mime: 'image/png',
    dataBase64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' }];
  var links = saveWpFiles_(cfg, files, rec);
  Logger.log('結果: ' + JSON.stringify(links));
}

// ============================================================
//  （任意）1つのタブに統合したい場合のヒント
//  ・上の WP_SHEET_TAB を「申込データ」にすると同じタブに書き込みます。
//    その場合、既存Google側の writeToSheet_ でも「申込ソース」を入れると
//    完全に区別できます。本番コードに次の1行方針を足すだけ：
//      row.unshift('Googleフォーム');  // 先頭に申込ソースを付与
//    （ただし列順が変わるため、統合は移行が固まってからを推奨）
// ============================================================
