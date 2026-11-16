function notifyApproved(lineId, caseId, creditorName, amount, rate, monthly) {
  const message = '審査が通過しました！\n\nおめでとうございます。\n\n' +
    '━━━━━━━━━━━━\n' +
    '承認信販：' + creditorName + '\n' +
    '承認額：' + parseInt(amount).toLocaleString() + '円\n' +
    '金利：' + rate + '%\n' +
    '月々返済：' + parseInt(monthly).toLocaleString() + '円\n' +
    '━━━━━━━━━━━━\n\n' +
    '次のステップ\n' +
    '1. Web商談を予約する\n' +
    '2. 担当スタッフと条件確認\n' +
    '3. ご契約・お車のご納車\n\n' +
    'Web商談のご予約はリッチメニューから\nお選びください。';

  pushToLine(lineId, message);
  Logger.log('審査通過通知送信：' + lineId);
}

function notifyContracted(lineId, caseId) {
  const message = 'この度はカーメルをご利用いただき\nありがとうございます。\n\n' +
    'ご成約おめでとうございます。\n\n' +
    '担当スタッフより\n今後の手続きについてご連絡いたします。\n\n' +
    '引き続きどうぞよろしく\nお願いいたします。\n\n' +
    'お知り合いでお車のことで\nお困りの方がいらっしゃいましたら\nカーメルをご紹介いただけますと幸いです。\n\n' +
    '紹介特典もございますので\nぜひキャンペーンメニューをご覧ください。';

  pushToLine(lineId, message);
  Logger.log('成約後通知送信：' + lineId);
}

function notifyMissingDocs(lineId, caseId) {
  const message = 'カーメルからのご連絡です。\n\n' +
    '審査に必要な書類がまだ\n届いておりません。\n\n' +
    '━━━━━━━━━━━━\n' +
    '必要書類\n' +
    '・運転免許証（表面・裏面）\n' +
    '・収入証明書\n' +
    '（源泉徴収票または給与明細）\n' +
    '━━━━━━━━━━━━\n\n' +
    'ご提出をお待ちしております。\n' +
    'ご不明な点はお気軽にご相談ください。';

  pushToLine(lineId, message);
  Logger.log('書類未提出通知送信：' + lineId);
}

function notifyPaymentDue(lineId, caseId, daysUntilPay, amount) {
  const message = 'カーメルからのご連絡です。\n\n' +
    '次回のお支払い期日まで\n' +
    '残り' + daysUntilPay + '日となりました。\n\n' +
    '━━━━━━━━━━━━\n' +
    'お支払い金額：' + parseInt(amount).toLocaleString() + '円\n' +
    '━━━━━━━━━━━━\n\n' +
    'ご不明な点はお気軽にご相談ください。';

  pushToLine(lineId, message);
  Logger.log('支払い期日通知送信：' + lineId);
}

function notifyShaken(lineId, caseId, daysLeft) {
  const message = 'カーメルからのご連絡です。\n\n' +
    '車検の満了日まで\n' +
    '残り' + daysLeft + '日となりました。\n\n' +
    '車検のご準備はお済みですか？\n\n' +
    '乗り換えをご検討の方は\nお気軽にご相談ください。\n\n' +
    '24時間いつでも対応しております。';

  pushToLine(lineId, message);
  Logger.log('車検通知送信：' + lineId);
}

function notifyInsurance(lineId, caseId, daysLeft) {
  const message = 'カーメルからのご連絡です。\n\n' +
    '任意保険の満了日まで\n' +
    '残り' + daysLeft + '日となりました。\n\n' +
    '保険の更新はお済みですか？\n\n' +
    'ご不明な点はお気軽にご相談ください。';

  pushToLine(lineId, message);
  Logger.log('保険通知送信：' + lineId);
}

function notifyCompletion(lineId, caseId, daysLeft) {
  const message = 'カーメルからのご連絡です。\n\n' +
    'ローンの完済予定日まで\n' +
    '残り' + daysLeft + '日となりました。\n\n' +
    '完済おめでとうございます！\n\n' +
    '次のお車のご購入や\n乗り換えのご相談も\nお気軽にどうぞ。';

  pushToLine(lineId, message);
  Logger.log('完済通知送信：' + lineId);
}

function notifySwitchover(lineId, caseId) {
  const message = 'カーメルからのご連絡です。\n\n' +
    'ご契約から1年が経ちました。\n\n' +
    'そろそろ乗り換えを\nご検討ではありませんか？\n\n' +
    '━━━━━━━━━━━━\n' +
    '2026年中古車相場が高騰中です。\n' +
    '今なら高値買取の可能性大！\n' +
    '━━━━━━━━━━━━\n\n' +
    'お気軽にご相談ください。\n' +
    '24時間いつでも対応しております。';

  pushToLine(lineId, message);
  Logger.log('乗換え通知送信：' + lineId);
}

function pushToLine(lineId, message) {
  try {
    const config = getConfig();
    const options = {
      method: 'post',
      contentType: 'application/json',
      headers: { 'Authorization': 'Bearer ' + config.LINE.CHANNEL_TOKEN },
      payload: JSON.stringify({
        to: lineId,
        messages: [{ type: 'text', text: message }]
      }),
      muteHttpExceptions: true
    };
    const response = UrlFetchApp.fetch('https://api.line.me/v2/bot/message/push', options);
    Logger.log('LINE通知：' + response.getResponseCode());
    return response.getResponseCode();
  } catch(err) {
    Logger.log('pushToLineエラー：' + err.toString());
    return null;
  }
}

function testLineNotify() {
  Logger.log('LineNotify テスト開始');
  const config = getConfig();
  Logger.log('Channel Token設定：' + (config.LINE.CHANNEL_TOKEN ? 'あり' : 'なし'));
  Logger.log('LineNotify テスト完了');
}
