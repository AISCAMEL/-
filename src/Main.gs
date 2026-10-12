const PROLINE_WEBHOOK_URL = 'https://autosns.jp/webhook/YOUR_PROLINE_WEBHOOK_TOKEN';

function doPost(e) {
  // WordPress連携API（承認/否決・加盟店アサイン）。action付きのときだけ振り分け、
  // LINE webhook（action無し）には一切影響しない。
  if (e && e.parameter && e.parameter.action) {
    return handleApiPost(e);
  }
  try {
    const body = JSON.parse(e.postData.contents);
    const events = body.events || [];
    events.forEach(function(event) {
      try {
        if (event.type === 'follow') handleFollow(event);
        else if (event.type === 'message') handleTextMessage(event);
        else if (event.type === 'unfollow') handleUnfollow(event);
      } catch(err) { Logger.log(err.toString()); }
    });
  } catch(err) { Logger.log(err.toString()); }
  forwardToProLine(e.postData.contents);
  return ContentService.createTextOutput('OK')
    .setMimeType(ContentService.MimeType.TEXT);
}

function forwardToProLine(rawBody) {
  try {
    UrlFetchApp.fetch(PROLINE_WEBHOOK_URL, {
      method: 'post',
      contentType: 'application/json',
      payload: rawBody,
      muteHttpExceptions: true
    });
  } catch(err) { Logger.log(err.toString()); }
}

function handleFollow(event) {
  registerNewCustomer(event.source.userId);
}

function handleUnfollow(event) {
  Logger.log('ブロック：' + event.source.userId);
}

function handleTextMessage(event) {
  const lineId = event.source.userId;
  const message = event.message.text;
  const replyToken = event.replyToken;
  saveChatLog(lineId, message, '受信');

  // 契約済みのお客様はマイページへ案内（新規向けAI相談と分ける）。
  // ただし「オペレーター」等、人へ繋ぎたい意図があれば通常処理へ流す。
  const humanKeywords = ['オペレーター', '担当', 'クレーム', '苦情', '電話'];
  const wantsHuman = humanKeywords.some(function(k) { return message.includes(k); });
  if (!wantsHuman && isContractedCustomer(lineId)) {
    const myReply = contractedCustomerReply();
    replyToLine(replyToken, myReply);
    saveChatLog(lineId, myReply, '送信');
    Logger.log('契約者→マイページ案内：' + lineId);
    return;
  }

  const prolineKeywords = [
    'ローンを相談したい',
    '気になる車がある',
    '車を売りたい',
    'その他・相談したい',
    '申込フォームへ進む',
    '不安なことを相談する',
    '無料査定へ進む',
    'まず相談したい',
    '相談してみる',
    '申込んでみる',
    'ローンを相談する',
    '直接相談する',
    '在庫一覧を見る',
    '今すぐ申込む',
    '書類について相談したい',
    '審査について聞く',
    '費用について聞く',
    '手続きについて聞く'
  ];

  const isProlineKeyword = prolineKeywords.some(function(keyword) {
    return message.includes(keyword);
  });

  if (isProlineKeyword) {
    Logger.log('ProLineキーワード：' + message);
    return;
  }

  try {
    const aiReply = callOpenRouter(message, lineId);
    replyToLine(replyToken, aiReply);
    saveChatLog(lineId, aiReply, '送信');
  } catch(err) {
    Logger.log(err.toString());
    replyToLine(replyToken, 'ご質問ありがとうございます。担当者より改めてご連絡いたします。');
  }
}

function registerNewCustomer(lineId) {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.CUSTOMER);
    const data = sheet.getDataRange().getValues();
    for (var i = 1; i < data.length; i++) {
      if (data[i][1] === lineId) return;
    }
    const now = new Date();
    sheet.appendRow([
      'CUS-' + Utilities.formatDate(now, 'Asia/Tokyo', 'yyyyMMddHHmmss'),
      lineId, '', '', '', '', '', '', '', '', '', now, now, now
    ]);
  } catch(err) { Logger.log(err.toString()); }
}

function saveChatLog(lineId, message, direction) {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.CHAT_LOG);
    const now = new Date();
    sheet.appendRow([
      'LOG-' + Utilities.formatDate(now, 'Asia/Tokyo', 'yyyyMMddHHmmss'),
      now, lineId, '', direction, message, 'AI自動', '', now
    ]);
  } catch(err) { Logger.log(err.toString()); }
}

function replyToLine(replyToken, message) {
  try {
    const config = getConfig();
    const response = UrlFetchApp.fetch('https://api.line.me/v2/bot/message/reply', {
      method: 'post',
      contentType: 'application/json',
      headers: { 'Authorization': 'Bearer ' + config.LINE.CHANNEL_TOKEN },
      payload: JSON.stringify({
        replyToken: replyToken,
        messages: [{ type: 'text', text: message }]
      }),
      muteHttpExceptions: true
    });
    Logger.log('LINE返信：' + response.getResponseCode());
  } catch(err) { Logger.log(err.toString()); }
}

// 契約済みのお客様か判定（返済管理シートにLINE_IDがあれば契約者）
function isContractedCustomer(lineId) {
  try {
    const config = getConfig();
    const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
    const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.REPAYMENT);
    if (!sheet) return false;
    const data = sheet.getDataRange().getValues();
    if (data.length < 2) return false;
    const idIdx = data[0].indexOf('LINE_ID');
    if (idIdx === -1) return false;
    const target = String(lineId);
    for (var i = 1; i < data.length; i++) {
      if (data[i][idIdx] && String(data[i][idIdx]) === target) return true;
    }
    return false;
  } catch(err) {
    Logger.log('isContractedCustomerエラー：' + err.toString());
    return false; // 判定できない時は通常処理（AI）に流す
  }
}

// 契約者向けの案内文（マイページへ誘導）
function contractedCustomerReply() {
  const url = getConfig().LINKS.MYPAGE;
  return 'いつもご利用ありがとうございます。\n'
    + 'ご契約内容・お支払い状況の確認や各種お手続きは、マイページからご確認いただけます。\n\n'
    + '▼ マイページ\n' + url + '\n\n'
    + '担当者に直接ご相談の場合は「オペレーター」とご返信ください。';
}

function doGet(e) {
  try {
    const params = e.parameter || {};

    // WordPress連携API（案件一覧取得）。action付きのときだけ振り分ける。
    if (params.action) {
      return handleApiGet(e);
    }

    const uid = params.uid || '';
    const snsname = params.snsname || '';
    const event = params.event || '';

    Logger.log('doGet受信：uid=' + uid + ' event=' + event);

    if (uid && event === 'follow') {
      registerNewCustomer(uid);
      Logger.log('友だち追加：' + uid);
    }

    return ContentService.createTextOutput('OK')
      .setMimeType(ContentService.MimeType.TEXT);

  } catch(err) {
    Logger.log('doGetエラー：' + err.toString());
    return ContentService.createTextOutput('OK')
      .setMimeType(ContentService.MimeType.TEXT);
  }
}
