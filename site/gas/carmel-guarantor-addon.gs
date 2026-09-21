/* ============================================================
   連帯保証人 申請の受信処理（審査GAS「CARMEL WPフォーム取り込み」への追加分）
   導入手順：
   1) doPost の honeypot チェック直後に次の1行を追加：
        if (payload.type === 'guarantor') return handleGuarantor_(cfg, payload);
   2) 既存コードの末尾に、このファイルの全関数を貼り付け
   3) 「新バージョン」で再デプロイ（URLは変わらない）
   ・記録先タブ：保証人申請（自動生成）／通知・メールは審査と同じ設定を使用
   ============================================================ */
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
  var body = (rec.applicant || 'お客様') + ' 様\n\nいつもお世話になっております。カーメルです。\n\nあなた様のお申し込みにともなう「連帯保証人」のご申請が完了いたしました。\n\n　保証人：' + (rec.name || '') + ' 様\n' + (rec.caseId ? '　案件番号：' + rec.caseId + '\n' : '') + '　受付日時：' + rec.stamp + '\n\n引き続き、審査を進めてまいります。\n結果や次のお手続きについては、担当者より順次ご案内いたします。\n\nご不明な点は、LINEまたはお電話でお気軽にお問い合わせください。\n　・LINE： https://lin.ee/y4QcSnq\n　・お電話： 050-1793-5554（受付 10:00〜18:00）\n\n────────────────────\n※本メールは送信専用です。\nカーメル（CARMEL）\nhttps://carmelonline.jp/\n────────────────────';
  sendGuarantorMail_(cfg, rec.applicantEmail, subject, body);
}
function sendGuarantorMail_(cfg, to, subject, body) {
  if (!to || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(to)) return;
  if (cfg.BREVO_API_KEY && cfg.BREVO_SENDER) { sendViaBrevo_(cfg, to, subject, body); return; }
  var options = { name: cfg.MAIL_FROM_NAME || 'カーメル' };
  if (cfg.MAIL_FROM) options.from = cfg.MAIL_FROM;
  GmailApp.sendEmail(to, subject, body, options);
}
