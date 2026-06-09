function setupAllSheets() {
  const ss = SpreadsheetApp.openById(getConfig().SPREADSHEET.ID);

  setupSheet(ss, 'ローン案件管理', [
    '申込番号', '受付日時', 'ステータス', '契約者種別',
    '顧客名', '生年月日', '電話番号', 'LINE_ID',
    '郵便番号', '住所', '雇用形態', '勤続年数',
    '年収', '希望借入額', '希望返済期間', '月額支払い可能額', '他社借入総額',
    '他社借入件数', '直近6ヶ月審査数', '信用情報', '債務整理歴',
    '自己破産歴', '滞納履歴', '購入予定車両',
    '頭金有無', '頭金額', '貯金額', '住居状況', '保証人有無', 'AIスコア',
    '見込みランク', 'スコア算出日', '書類本人確認', '書類収入証明',
    '書類車検証', '書類法人登記', '書類決算書',
    '信販1_社名', '信販1_結果', '信販1_承認額', '信販1_金利',
    '信販2_社名', '信販2_結果', '信販2_承認額', '信販2_金利',
    '信販3_社名', '信販3_結果', '信販3_承認額', '信販3_金利',
    '信販4_社名', '信販4_結果', '信販4_承認額', '信販4_金利',
    '信販5_社名', '信販5_結果', '信販5_承認額', '信販5_金利',
    '信販6_社名', '信販6_結果', '信販6_承認額', '信販6_金利',
    '信販7_社名', '信販7_結果', '信販7_承認額', '信販7_金利',
    '信販8_社名', '信販8_結果', '信販8_承認額', '信販8_金利',
    '担当加盟店', 'アサイン日時', '商談ステータス', '成約日',
    '成約金額', '本部メモ', '登録日時'
  ]);

  setupSheet(ss, '買取案件管理', [
    '買取番号', '受付日時', 'ステータス', '顧客名',
    '電話番号', 'LINE_ID', '車種', '年式',
    '走行距離', '車検残', 'グレード', '色',
    '修復歴', 'オプション', '希望売却額', 'AI査定スコア',
    '担当加盟店', 'アサイン日時', '査定額', '成約日',
    '本部メモ', '登録日時'
  ]);

  setupSheet(ss, 'リース案件管理', [
    'リース番号', '受付日時', 'ステータス', '顧客名',
    '電話番号', 'LINE_ID', '契約開始日', '契約終了日',
    '月額リース料', '支払日', '車両名', 'GPS有無',
    '担当加盟店', '次回支払日', '延滞フラグ', '延滞日数',
    '延滞利息累計', '本部メモ', '登録日時'
  ]);

  setupSheet(ss, '顧客マスタ', [
    '顧客ID', 'LINE_ID', '顧客名', '電話番号',
    'メールアドレス', '生年月日', '郵便番号', '住所',
    '契約者種別', '関連申込番号', 'アプリワン会員ID',
    '初回接触日', '最終接触日', '登録日時'
  ]);

  setupSheet(ss, '加盟店マスタ', [
    '加盟店ID', '加盟店名', 'エリア', '担当者名',
    '電話番号', 'LINE_WORKS_ID', '加盟店LINE_ID',
    '対応信販リスト', '現在案件数', 'ステータス', '登録日時'
  ]);

  setupSheet(ss, '信販会社マスタ', [
    '信販ID', '信販会社名', '担当者名', '連絡先TEL', '連絡先FAX',
    '審査TAT', '最低金利', '最高金利', '最大融資額', '対応判定',
    '打診優先順位', '個人対応', '法人対応', '個人事業主対応',
    'GPS対応', '外国人対応', '備考', 'ステータス', '登録日時'
  ]);

  setupSheet(ss, '返済管理', [
    '返済ID', '申込番号', '顧客名', 'LINE_ID',
    '信販会社名', '契約金額', '金利', '返済回数',
    '月々支払額', '契約開始日', '完済予定日',
    '次回支払日', '支払済回数', '残高',
    'GPS有無', '延滞フラグ', '延滞日数', '延滞利息累計',
    '担当加盟店', '登録日時'
  ]);

  setupSheet(ss, 'アフターサポート管理', [
    'サポートID', '顧客ID', '顧客名', 'LINE_ID',
    '申込番号', '車検満了日', '保険満了日',
    '完済予定日', '乗換え検討日',
    '車検通知済', '保険通知済', '乗換え通知済', '完済前通知済',
    '担当加盟店', '登録日時'
  ]);

  setupSheet(ss, '会話ログ', [
    'ログID', '日時', 'LINE_ID', '顧客名',
    '方向', 'メッセージ内容', '対応種別',
    '関連申込番号', '登録日時'
  ]);

  setupSheet(ss, '週次レポート', [
    'レポート週', '新規問い合わせ数', '申込数', 'AI審査通過数',
    '信販審査通過数', 'アサイン数', '成約数', '成約率',
    '延滞件数', 'AI自動対応率', '本部作業時間', '登録日時'
  ]);

  Logger.log('全シートのヘッダー設定完了');
}

function setupSheet(ss, sheetName, headers) {
  const sheet = ss.getSheetByName(sheetName);
  if (!sheet) {
    Logger.log('シートが見つかりません：' + sheetName);
    return;
  }
  sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  sheet.getRange(1, 1, 1, headers.length)
    .setBackground('#1a73e8')
    .setFontColor('#ffffff')
    .setFontWeight('bold');
  sheet.setFrozenRows(1);
  Logger.log(sheetName + ' 設定完了（' + headers.length + '列）');
}

function checkMasterData() {
  const ss = SpreadsheetApp.openById(getConfig().SPREADSHEET.ID);

  const creditorSheet = ss.getSheetByName('信販会社マスタ');
  const creditorData = creditorSheet.getDataRange().getValues();
  Logger.log('信販会社マスタ：' + (creditorData.length - 1) + '社登録済み');

  const franchiseeSheet = ss.getSheetByName('加盟店マスタ');
  const franchiseeData = franchiseeSheet.getDataRange().getValues();
  Logger.log('加盟店マスタ：' + (franchiseeData.length - 1) + '店舗登録済み');

  for (var i = 1; i < creditorData.length; i++) {
    Logger.log('信販：' + creditorData[i][0] + ' | ' + creditorData[i][1] + ' | 金利' + creditorData[i][6] + '〜' + creditorData[i][7] + '%');
  }
  for (var j = 1; j < franchiseeData.length; j++) {
    Logger.log('加盟店：' + franchiseeData[j][0] + ' | ' + franchiseeData[j][1] + ' | 対応信販：' + franchiseeData[j][7]);
  }
}
