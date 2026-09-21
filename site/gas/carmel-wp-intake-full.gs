// ============================================================
//  CARMEL WPフォーム取り込み（Google Apps Script）Brevo対応版
//  ＋ 連帯保証人 申請 受信処理（type='guarantor'）統合版
// ============================================================

function getWpConfig_() {
  var p = PropertiesService.getScriptProperties().getProperties();
  return {
    CARMEL_SHEET_ID:  p['CARMEL_SHEET_ID']  || '',
    WP_SHEET_TAB:     p['WP_SHEET_TAB']      || 'WP申込',
    DRIVE_FOLDER_ID:  p['DRIVE_FOLDER_ID']  || '',
    SLACK_WEBHOOK_URL: p['SLACK_WEBHOOK_URL'] || '',
    ASANA_TOKEN:       p['ASANA_TOKEN']       || '',
    ASANA_PROJECT_ID:  p['ASANA_PROJECT_ID']  || '',
    ASANA_SECTION_ID:  p['ASANA_SECTION_ID']  || '',
    SEND_THANKS: (p['WP_SEND_THANKS'] || 'false') === 'true',
    MYPAGE_URL:  p['MYPAGE_URL'] || '',
    MAIL_FROM:      p['MAIL_FROM'] || '',
    MAIL_FROM_NAME: p['MAIL_FROM_NAME'] || 'カーメル',
    BREVO_API_KEY: p['BREVO_API_KEY'] || '',
    BREVO_SENDER:  p['BREVO_SENDER']  || '',
    SEND_ASANA: (p['WP_SEND_ASANA'] || 'false') === 'true',
    SEND_SLACK: (p['WP_SEND_SLACK'] || 'false') === 'true',
    NOTIFY_EMAIL: p['NOTIFY_EMAIL'] || 'info@aisjaltd.com',
    TZ: 'Asia/Tokyo',
    SOURCE_LABEL: 'WPフォーム'
  };
}

var WP_META_COLMAP = {
  name:  'お名前（フルネーム）', kana: 'フリガナ', phone: '電話番号',
  email: 'メールアドレス', car: 'ご希望の車種'
};

function doPost(e) {
  try {
    var cfg = getWpConfig_();
    var raw = (e && e.postData && e.postData.contents) || '{}';
    var payload = JSON.parse(raw);
    if (payload.hp) return jsonOut_({ success: true, skipped: 'honeypot' });
    if (payload.type === 'guarantor') return handleGuarantor_(cfg, payload); // ★保証人フォーム
    var rec = buildWpRecord_(cfg, payload);
    try {
      rec.fileLinks = saveWpFiles_(cfg, payload.files || [], rec);
    } catch (fe) {
      rec.fileLinks = [{ label: '画像保存エラー', url: String(fe) }];
      notifyWpError_('ファイル保存でエラー（申込は記録します）: ' + fe);
    }
    writeWpRowDynamic_(cfg, rec);
    if (cfg.SEND_SLACK && cfg.SLACK_WEBHOOK_URL) safeRun_(cfg, function(){ notifyWpSlack_(cfg, rec); });
    if (cfg.SEND_ASANA && cfg.ASANA_TOKEN && cfg.ASANA_PROJECT_ID) safeRun_(cfg, function(){ createWpAsana_(cfg, rec); });
    if (cfg.SEND_THANKS) safeRun_(cfg, function(){ sendApplicantThankYou_(cfg, rec); });
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

function buildWpRecord_(cfg, payload) {
  var m = payload.meta || {};
  var fields = payload.fields || [];
  var now = new Date();
  var stamp = Utilities.formatDate(now, cfg.TZ, 'yyyy/MM/dd HH:mm:ss');
  var uid = 'WP-' + now.getTime() + '-' + Math.random().toString(36).slice(2, 7).toUpperCase();
  var lines = [], sec = '';
  fields.forEach(function(f) {
    if (f.section && f.section !== sec) { sec = f.section; lines.push('■ ' + sec); }
    lines.push('　' + (f.label || '') + '：' + (f.value || ''));
  });
  return {
    uid: uid, stamp: stamp, source: cfg.SOURCE_LABEL,
    name:  m.name  || '', kana:  m.kana  || '', phone: m.phone || '',
    email: m.email || '', car:   m.car   || '',
    fields: fields, body: lines.join('\n'), fileLinks: []
  };
}

function writeWpRowDynamic_(cfg, rec) {
  if (!cfg.CARMEL_SHEET_ID) throw new Error('CARMEL_SHEET_ID 未設定');
  var ss = SpreadsheetApp.openById(cfg.CARMEL_SHEET_ID);
  var sheet = ss.getSheetByName(cfg.WP_SHEET_TAB) || ss.insertSheet(cfg.WP_SHEET_TAB);
  var dataObj = {};
  dataObj['申込ソース'] = rec.source;
  dataObj['UID']        = rec.uid;
  dataObj['受付日時']   = rec.stamp;
  dataObj[WP_META_COLMAP.name]  = rec.name;
  dataObj[WP_META_COLMAP.kana]  = rec.kana;
  dataObj[WP_META_COLMAP.phone] = rec.phone;
  dataObj[WP_META_COLMAP.email] = rec.email;
  dataObj[WP_META_COLMAP.car]   = rec.car;
  rec.fields.forEach(function(f) {
    var col = (f.section ? f.section + ' / ' : '') + (f.label || '');
    if (!col) return;
    dataObj[col] = dataObj[col] ? (dataObj[col] + ' / ' + f.value) : f.value;
  });
  rec.fileLinks.forEach(function(fl) {
    var col = '書類：' + fl.label;
    dataObj[col] = dataObj[col] ? (dataObj[col] + '\n' + fl.url) : fl.url;
  });
  appendByHeader_(sheet, dataObj);
}

function appendByHeader_(sheet, dataObj) {
  var lastCol = sheet.getLastColumn();
  var header = lastCol > 0 ? sheet.getRange(1, 1, 1, lastCol).getValues()[0] : [];
  if (header.length === 0 || String(header[0]).trim() === '') {
    var keys = Object.keys(dataObj);
    sheet.getRange(1, 1, 1, keys.length).setValues([keys])
      .setBackground('#1a2e5a').setFontColor('#ffffff').setFontWeight('bold');
    header = keys;
  }
  var idx = {};
  header.forEach(function(h, i) { if (h !== '' && !(h in idx)) idx[h] = i; });
  var add = [];
  Object.keys(dataObj).forEach(function(k) { if (!(k in idx)) add.push(k); });
  if (add.length) {
    sheet.getRange(1, header.length + 1, 1, add.length).setValues([add])
      .setBackground('#1a2e5a').setFontColor('#ffffff').setFontWeight('bold');
    add.forEach(function(k, i) { idx[k] = header.length + i; });
    header = header.concat(add);
  }
  var row = [];
  for (var c = 0; c < header.length; c++) row.push('');
  Object.keys(dataObj).forEach(function(k) { row[idx[k]] = dataObj[k]; });
  sheet.appendRow(row);
}

function saveWpFiles_(cfg, files, rec) {
  if (!files.length || !cfg.DRIVE_FOLDER_ID) return [];
  var main = DriveApp.getFolderById(cfg.DRIVE_FOLDER_ID);
  var userFolder = getOrCreateFolder_(main, rec.name || '名前未設定');
  var dateLabel  = rec.stamp.replace(/[\/:]/g, '-');
  var subFolder  = userFolder.createFolder(dateLabel + '　' + rec.uid);
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

function getOrCreateFolder_(parent, name) {
  name = sanitize_(name);
  var it = parent.getFoldersByName(name);
  return it.hasNext() ? it.next() : parent.createFolder(name);
}

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

function createWpAsana_(cfg, rec) {
  var notes =
    '【申込ソース】WPフォーム\n' +
    '【受付日時】' + rec.stamp + '\n' +
    '【電話】' + rec.phone + '　【メール】' + rec.email + '\n' +
    '【希望車】' + (rec.car || '未定') + '\n\n' + rec.body + '\n\n【添付書類】\n' +
    (rec.fileLinks.length ? rec.fileLinks.map(function(f){ return '・' + f.label + '： ' + f.url; }).join('\n') : '（添付なし）');
  var headers = { 'Authorization': 'Bearer ' + cfg.ASANA_TOKEN, 'Content-Type': 'application/json' };
  var res = UrlFetchApp.fetch('https://app.asana.com/api/1.0/tasks', {
    method: 'post', headers: headers,
    payload: JSON.stringify({ data: {
      name: '【WP新規】' + (rec.name || '名前未設定') + '　様　' + (rec.car || ''),
      notes: notes, projects: [cfg.ASANA_PROJECT_ID]
    }}),
    muteHttpExceptions: true
  });
  var code = res.getResponseCode();
  if (cfg.ASANA_SECTION_ID && (code === 200 || code === 201)) {
    var gid = JSON.parse(res.getContentText()).data.gid;
    UrlFetchApp.fetch('https://app.asana.com/api/1.0/sections/' + cfg.ASANA_SECTION_ID + '/addTask', {
      method: 'post', headers: headers,
      payload: JSON.stringify({ data: { task: gid } }),
      muteHttpExceptions: true
    });
  }
}

// ===== 申込者への自動返信メール（Brevo経由。未設定ならGmail） =====
function sendApplicantThankYou_(cfg, rec) {
  if (!rec.email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(rec.email)) return;
  var name = rec.name || 'お客様';
  var ready = !!cfg.MYPAGE_URL;
  var mp = ready
    ? '審査の結果・進捗・今後のお手続きは、すべて「マイページ」からご確認いただけます。\n\n　▼ マイページ\n　' + cfg.MYPAGE_URL + '\n'
    : '審査の結果・進捗は、追ってご案内いたします。\n※マイページは現在準備中です。準備が整いしだい、ログイン情報をお送りいたします。\n';
  var contact = ready
    ? 'ご質問・ご相談は、マイページの「お問い合わせ」よりご連絡ください。\n担当者が順次対応いたします。\n'
    : 'ご不明な点・お急ぎのご相談は、下記までお気軽にご連絡ください。\n　・LINE： https://lin.ee/y4QcSnq\n　・お電話： 050-1793-5554（受付 10:00〜18:00）\n';
  var foot = ready
    ? '※本メールは送信専用です。お問い合わせはマイページからお願いいたします。'
    : '※本メールは送信専用です。お問い合わせは上記のLINE・お電話をご利用ください。';
  var body =
    name + ' 様\n\nこの度は、カーメルの「かんたんWEB審査」にお申し込みいただき、\n誠にありがとうございます。\n' +
    'お申し込みを、確かに受け付けいたしました。\n\n' +
    '━━━━━━━━━━━━━━━━━━━━━━\n  ■ 審査結果・進捗のご確認\n━━━━━━━━━━━━━━━━━━━━━━\n\n' + mp + '\n' +
    '━━━━━━━━━━━━━━━━━━━━━━\n  ■ ご不明な点があるときは\n━━━━━━━━━━━━━━━━━━━━━━\n\n' + contact + '\n' +
    '今後ともカーメルをどうぞよろしくお願いいたします。\n\n' +
    '────────────────────\n' + foot + '\n\nカーメル（CARMEL）\nhttps://carmelonline.jp/\n────────────────────';
  var subject = '【カーメル】WEB審査のお申し込みを受け付けました';
  if (cfg.BREVO_API_KEY && cfg.BREVO_SENDER) { sendViaBrevo_(cfg, rec.email, subject, body); return; }
  var options = { name: cfg.MAIL_FROM_NAME || 'カーメル' };
  if (cfg.MAIL_FROM) options.from = cfg.MAIL_FROM;
  GmailApp.sendEmail(rec.email, subject, body, options);
}

// ===== Brevo（外部配信）HTTP API 経由の送信 =====
function sendViaBrevo_(cfg, to, subject, textBody) {
  if (!cfg.BREVO_API_KEY) throw new Error('BREVO_API_KEY 未設定');
  if (!cfg.BREVO_SENDER)  throw new Error('BREVO_SENDER 未設定');
  var payload = {
    sender: { name: cfg.MAIL_FROM_NAME || 'カーメル', email: cfg.BREVO_SENDER },
    to: [{ email: to }], subject: subject, textContent: textBody
  };
  var res = UrlFetchApp.fetch('https://api.brevo.com/v3/smtp/email', {
    method: 'post', contentType: 'application/json',
    headers: { 'api-key': cfg.BREVO_API_KEY, 'accept': 'application/json' },
    payload: JSON.stringify(payload), muteHttpExceptions: true
  });
  var code = res.getResponseCode();
  if (code < 200 || code >= 300) throw new Error('Brevo送信失敗 code=' + code + ' body=' + res.getContentText());
  return true;
}

function jsonOut_(o) { return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON); }
function sanitize_(n) { return String(n).replace(/[\\/:*?"<>|]/g, '_'); }
function safeRun_(cfg, fn) { try { return fn(); } catch (e) { notifyWpError_(String(e)); } }
function notifyWpError_(msg) {
  try { var cfg = getWpConfig_(); if (cfg.NOTIFY_EMAIL) MailApp.sendEmail(cfg.NOTIFY_EMAIL, '【WP審査フォーム】取り込みエラー', msg); } catch (e) {}
}

// ===== テスト用 =====
function TEST_brevo() {
  var cfg = getWpConfig_();
  var to = cfg.NOTIFY_EMAIL || 'info@aisjaltd.com';
  Logger.log('BREVO_SENDER = ' + cfg.BREVO_SENDER + ' / APIキー設定 = ' + (cfg.BREVO_API_KEY ? 'あり' : 'なし'));
  try {
    sendViaBrevo_(cfg, to, '【Brevoテスト】カーメル審査 返信メール',
      'これはBrevo経由の送信テストです。届いていれば、返信メールは今後Brevoで確実に届きます。');
    Logger.log('Brevo送信OK：' + to + ' の受信トレイ（＋迷惑メール）を確認してください');
  } catch (e) { Logger.log('Brevo送信エラー：' + e); }
}

// ============================================================
//  連帯保証人 申請の受信処理（保証人フォーム type='guarantor'）
// ============================================================
function handleGuarantor_(cfg, payload) {
  try {
    var m = payload.meta || {};
    var rec = buildWpRecord_(cfg, payload);
    rec.source = '保証人フォーム';
    rec.applicant      = m.applicant || '';
    rec.caseId         = m.caseId || '';
    rec.applicantEmail = m.applicantEmail || '';
    try { rec.fileLinks = saveWpFiles_(cfg, payload.files || [], rec); }
    catch (fe) { rec.fileLinks = [{ label: '画像保存エラー', url: String(fe) }]; notifyWpError_('保証人ファイル保存エラー: ' + fe); }
    writeGuarantorRow_(cfg, rec);
    if (cfg.SEND_SLACK && cfg.SLACK_WEBHOOK_URL) safeRun_(cfg, function(){ notifyGuarantorSlack_(cfg, rec); });
    if (cfg.SEND_ASANA && cfg.ASANA_TOKEN && cfg.ASANA_PROJECT_ID) safeRun_(cfg, function(){ createGuarantorAsana_(cfg, rec); });
    safeRun_(cfg, function(){ notifyGuarantorAdmin_(cfg, rec); });
    safeRun_(cfg, function(){ sendGuarantorReceipt_(cfg, rec); });
    if (rec.applicantEmail) safeRun_(cfg, function(){ sendApplicantGuarantorDone_(cfg, rec); });
    return jsonOut_({ success: true, uid: rec.uid });
  } catch (err) {
    notifyWpError_('保証人 doPost失敗: ' + err);
    return jsonOut_({ success: false, message: '送信処理でエラーが発生しました。' });
  }
}

function writeGuarantorRow_(cfg, rec) {
  if (!cfg.CARMEL_SHEET_ID) throw new Error('CARMEL_SHEET_ID 未設定');
  var ss = SpreadsheetApp.openById(cfg.CARMEL_SHEET_ID);
  var sheet = ss.getSheetByName('保証人申請') || ss.insertSheet('保証人申請');
  var d = {};
  d['申込ソース']=rec.source; d['UID']=rec.uid; d['受付日時']=rec.stamp;
  d['お申込者名']=rec.applicant; d['案件番号']=rec.caseId; d['申込者メール']=rec.applicantEmail;
  d['保証人 お名前']=rec.name; d['保証人 フリガナ']=rec.kana; d['保証人 電話']=rec.phone; d['保証人 メール']=rec.email;
  rec.fields.forEach(function(f){ var col=(f.section?f.section+' / ':'')+(f.label||''); if(!col)return; d[col]=d[col]?(d[col]+' / '+f.value):f.value; });
  rec.fileLinks.forEach(function(fl){ var col='書類：'+fl.label; d[col]=d[col]?(d[col]+'\n'+fl.url):fl.url; });
  appendByHeader_(sheet, d);
}

function notifyGuarantorSlack_(cfg, rec) {
  var files = rec.fileLinks.length ? rec.fileLinks.map(function(f){ return '• <' + f.url + '|' + f.label + '>'; }).join('\n') : '（添付なし）';
  var text = '*新規 連帯保証人 申請* :bust_in_silhouette:\n' +
    '*お申込者：* ' + (rec.applicant || '—') + (rec.caseId ? '（案件 ' + rec.caseId + '）' : '') + '\n' +
    '*保証人：* ' + rec.name + '（' + rec.kana + '）\n' +
    '*電話：* ' + rec.phone + '　*メール：* ' + rec.email + '\n' +
    '*受付：* ' + rec.stamp + '\n——————————————\n' + rec.body + '\n——————————————\n*添付書類：*\n' + files;
  UrlFetchApp.fetch(cfg.SLACK_WEBHOOK_URL, { method:'post', contentType:'application/json', payload: JSON.stringify({ text: text }), muteHttpExceptions:true });
}

function createGuarantorAsana_(cfg, rec) {
  var notes = '【区分】連帯保証人 申請\n【お申込者】' + (rec.applicant || '—') + (rec.caseId ? '（案件 ' + rec.caseId + '）' : '') + '\n' +
    '【申込者メール】' + (rec.applicantEmail || '—') + '\n【受付日時】' + rec.stamp + '\n' +
    '【保証人 電話】' + rec.phone + '　【保証人 メール】' + rec.email + '\n\n' + rec.body + '\n\n【添付書類】\n' +
    (rec.fileLinks.length ? rec.fileLinks.map(function(f){ return '・' + f.label + '： ' + f.url; }).join('\n') : '（添付なし）');
  var headers = { 'Authorization': 'Bearer ' + cfg.ASANA_TOKEN, 'Content-Type': 'application/json' };
  var res = UrlFetchApp.fetch('https://app.asana.com/api/1.0/tasks', { method:'post', headers:headers,
    payload: JSON.stringify({ data: { name: '【保証人】' + (rec.name || '名前未設定') + ' 様 → お申込者 ' + (rec.applicant || '—'), notes: notes, projects: [cfg.ASANA_PROJECT_ID] }}), muteHttpExceptions:true });
  var code = res.getResponseCode();
  if (cfg.ASANA_SECTION_ID && (code === 200 || code === 201)) {
    var gid = JSON.parse(res.getContentText()).data.gid;
    UrlFetchApp.fetch('https://app.asana.com/api/1.0/sections/' + cfg.ASANA_SECTION_ID + '/addTask', { method:'post', headers:headers, payload: JSON.stringify({ data: { task: gid } }), muteHttpExceptions:true });
  }
}

function notifyGuarantorAdmin_(cfg, rec) {
  if (!cfg.NOTIFY_EMAIL) return;
  var body = '連帯保証人の申請がありました。\n\nお申込者：' + (rec.applicant || '—') + (rec.caseId ? '（案件 ' + rec.caseId + '）' : '') + '\n申込者メール：' + (rec.applicantEmail || '—') + '\n\n【保証人】\n氏名：' + rec.name + '（' + rec.kana + '）\n電話：' + rec.phone + '\nメール：' + rec.email + '\n\n' + rec.body + '\n\n【添付書類】\n' + (rec.fileLinks.length ? rec.fileLinks.map(function(f){ return '・' + f.label + '： ' + f.url; }).join('\n') : '（なし）') + '\n\n受付：' + rec.stamp;
  MailApp.sendEmail(cfg.NOTIFY_EMAIL, '【カーメル】連帯保証人 申請：' + rec.name + ' 様（お申込者 ' + (rec.applicant || '—') + '）', body);
}

function sendGuarantorReceipt_(cfg, rec) {
  var subject = '【カーメル】連帯保証人 申請を受け付けました';
  var body = (rec.name || 'ご担当者') + ' 様\n\nこの度は、連帯保証人としてのご申請をいただき、誠にありがとうございます。\nお申込者（' + (rec.applicant || '') + ' 様）の自動車ローンにともなう保証人申請を、確かに受け付けいたしました。\n\n内容を確認のうえ、必要に応じて担当者よりご連絡させていただきます。\n追加書類が必要な場合も、あらためてご案内いたします。\n\nご不明な点は、LINEまたはお電話でお気軽にお問い合わせください。\n　・LINE： https://lin.ee/y4QcSnq\n　・お電話： 050-1793-5554（受付 10:00〜18:00）\n\n────────────────────\n※本メールは送信専用です。\nカーメル（CARMEL）\nhttps://carmelonline.jp/\n────────────────────';
  sendGuarantorMail_(cfg, rec.email, subject, body);
}

function sendApplicantGuarantorDone_(cfg, rec) {
  var subject = '【カーメル】保証人様のご申請が完了しました';
  var ready = !!cfg.MYPAGE_URL;
  var mp = ready
    ? '今後の審査結果・進捗・お手続きは、マイページからご確認ください。\n\n　▼ マイページ\n　' + cfg.MYPAGE_URL + '\n'
    : '今後の審査結果・進捗は、追ってご案内いたします。\n※マイページは現在準備中です。準備が整いしだいご案内いたします。\n';
  var contact = ready
    ? 'ご質問・ご相談は、マイページの「お問い合わせ」よりご連絡ください。\n'
    : 'ご不明な点・お急ぎのご相談は、下記までお気軽にご連絡ください。\n　・LINE： https://lin.ee/y4QcSnq\n　・お電話： 050-1793-5554（受付 10:00〜18:00）\n';
  var foot = ready
    ? '※本メールは送信専用です。お問い合わせはマイページからお願いいたします。'
    : '※本メールは送信専用です。お問い合わせは上記のLINE・お電話をご利用ください。';
  var body = (rec.applicant || 'お客様') + ' 様\n\n' +
    'いつもお世話になっております。カーメルです。\n' +
    'あなた様のお申し込みにともなう「連帯保証人」のご申請が完了いたしました。\n\n' +
    '　保証人：' + (rec.name || '') + ' 様\n' + (rec.caseId ? '　案件番号：' + rec.caseId + '\n' : '') + '　受付日時：' + rec.stamp + '\n\n' +
    '━━━━━━━━━━━━━━━━━━━━━━\n  ■ 審査結果・進捗のご確認\n━━━━━━━━━━━━━━━━━━━━━━\n\n' + mp + '\n' +
    '━━━━━━━━━━━━━━━━━━━━━━\n  ■ ご不明な点があるときは\n━━━━━━━━━━━━━━━━━━━━━━\n\n' + contact + '\n' +
    '────────────────────\n' + foot + '\nカーメル（CARMEL）\nhttps://carmelonline.jp/\n────────────────────';
  sendGuarantorMail_(cfg, rec.applicantEmail, subject, body);
}

function sendGuarantorMail_(cfg, to, subject, body) {
  if (!to || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(to)) return;
  if (cfg.BREVO_API_KEY && cfg.BREVO_SENDER) { sendViaBrevo_(cfg, to, subject, body); return; }
  var options = { name: cfg.MAIL_FROM_NAME || 'カーメル' };
  if (cfg.MAIL_FROM) options.from = cfg.MAIL_FROM;
  GmailApp.sendEmail(to, subject, body, options);
}
