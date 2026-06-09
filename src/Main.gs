const PROLINE_WEBHOOK_URL = 'https://autosns.jp/webhook/YOUR_PROLINE_WEBHOOK_TOKEN';

function doPost(e) {
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

function doGet(e) {
  try {
    const params = e.parameter;
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
