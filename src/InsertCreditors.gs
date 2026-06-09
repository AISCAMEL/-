function insertCreditorData() {
  const config = getConfig();
  const ss = SpreadsheetApp.openById(config.SPREADSHEET.ID);
  const sheet = ss.getSheetByName(config.SPREADSHEET.SHEETS.CREDITOR);

  if (!sheet) {
    Logger.log('信販会社マスタシートが見つかりません');
    return;
  }

  const now = Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy/MM/dd HH:mm:ss');

  const creditors = [
    ['C001','オリコ',                 '','','','即日',    3.9,  9.9,  500,'A',    1,'○','○','○','×','○','主力',                        '稼働中',now],
    ['C002','ジャックス',             '','','','即日',    4.9, 12.9,  300,'A〜B', 2,'○','○','○','×','○','主力',                        '稼働中',now],
    ['C003','プレミアグループ',       '','','','即日',    4.9, 12.9,  300,'B',    3,'○','×','×','×','○','サブ',                        '稼働中',now],
    ['C004','アプラス',               '','','','1時間',   1.9,  7.9,  500,'A〜B', 2,'○','×','×','×','○','SBI新生銀行G・84回',          '稼働中',now],
    ['C005','USSサポート',            '','','','即日',    7.9, 14.6,  200,'C',    1,'○','×','×','○','○','MCCS(GPS)',                   '稼働中',now],
    ['C006','セイブサポート',         '','','','1時間',   5.0, 18.0,  500,'C',    2,'○','×','○','×','×','独自審査・60回',              '稼働中',now],
    ['C007','セイブサポートイースト', '','','','1時間',   5.0, 18.0,  500,'C',    3,'○','×','○','×','×','東日本・同条件',              '稼働中',now],
    ['C008','カーアシスト（SBI）',    '','','','即日',    9.8, 18.0,  500,'C〜D', 4,'○','×','×','×','○','保証人不要・84回',            '稼働中',now],
    ['C009','フジクレジット',         '','','','2時間',   5.0, 18.0,  300,'C〜D', 5,'○','×','○','×','×','最短2時間・60回',            '稼働中',now],
    ['C010','カラフルライン',         '','','','即日',   14.6, 20.0,  300,'D',    1,'○','×','×','○','○','GPS・エンジン制御',          '稼働中',now],
    ['C011','ドウテックソリューション','','','','即日',  14.6, 20.0,  300,'D',    2,'○','×','×','○','○','GPS・60回・リース',          '稼働中',now],
    ['C012','エバーレンディング東北', '','','','即日',    7.3, 18.0,  300,'C〜D', 6,'○','×','×','×','×','東北・84回',                '稼働中',now],
    ['C013','信用回復系',             '','','','翌営業日',14.6, 20.0,  100,'D',    3,'○','×','×','×','○','',                          '稼働中',now],
    ['C014','自社リース',             '','','','即日',    0.0, 20.0,  100,'D',    4,'○','×','×','○','○','信用情報照会なし・即日・GPS', '稼働中',now],
  ];

  const lastRow = sheet.getLastRow();
  if (lastRow > 1) {
    sheet.getRange(2, 1, lastRow - 1, 19).clearContent();
  }

  sheet.getRange(2, 1, creditors.length, 19).setValues(creditors);

  sheet.getRange(2, 11, creditors.length, 1).setNumberFormat('0');
  sheet.getRange(2, 7, creditors.length, 3).setNumberFormat('0.0');
  sheet.getRange(2, 12, creditors.length, 5).setHorizontalAlignment('center');
  sheet.getRange(2, 7, creditors.length, 5).setHorizontalAlignment('center');

  creditors.forEach(function(row, i) {
    const rowNum = i + 2;
    const judgment = row[9];
    let color = '#ffffff';
    if (judgment === 'A')    color = '#e8f5e9';
    if (judgment === 'A〜B') color = '#f1f8e9';
    if (judgment === 'B')    color = '#fff9e6';
    if (judgment === 'C')    color = '#fff3e0';
    if (judgment === 'C〜D') color = '#fce4ec';
    if (judgment === 'D')    color = '#ffebee';
    sheet.getRange(rowNum, 1, 1, 19).setBackground(color);
  });

  Logger.log('信販会社マスタ 14社の登録完了');
  Logger.log('登録件数：' + creditors.length + '社');
}
