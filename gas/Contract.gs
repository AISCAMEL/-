/**
 * Contract.gs — 売買代行委託契約書（PDF）を自動生成
 *
 * Google ドキュメントで契約書を作成し、PDF化してドライブに保存する。
 * 顧客固有データ（氏名・車両・金額等）は差し込み、
 * 未確定の欄は ________________ 空欄として手書き記入に対応。
 */

/**
 * 売買代行委託契約書（PDF）を生成し、ドライブに保存。URLを返す。
 * @param {string} orderId 受付番号
 * @param {Object} data   顧客・注文データ
 * @return {string} PDF の URL
 */
function makeContractPdf_(orderId, data) {
  var cfg = getConfig();
  var BL = "________________";
  var today = new Date().toLocaleDateString("ja-JP");

  var doc = DocumentApp.create("売買代行委託契約書_" + orderId);
  var body = doc.getBody();

  // ----- タイトル -----
  body.appendParagraph("売買代行委託契約書")
    .setHeading(DocumentApp.ParagraphHeading.HEADING1)
    .setAlignment(DocumentApp.HorizontalAlignment.CENTER);

  body.appendParagraph("");

  body.appendParagraph(
    (data.name || BL) + "（以下「甲」という）と合同会社アイズ（以下「乙」という）は、" +
    "以下のとおり売買代行委託契約（以下「本契約」という）を締結する。"
  );

  // ----- 第1条 契約当事者 -----
  body.appendParagraph("第1条（契約当事者）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph("甲（委託者）");
  body.appendTable([
    ["氏名", data.name || BL],
    ["住所", data.address || "〒" + BL + "　" + BL],
    ["電話番号", data.phone || BL],
    ["メールアドレス", data.email || ""]
  ]);
  body.appendParagraph("乙（受託者）");
  body.appendTable([
    ["商号", "合同会社アイズ"],
    ["代表者", "代表社員　吉田一平"],
    ["所在地", "〒979-0204 福島県いわき市四倉町細谷字大町1番"],
    ["古物商許可", "福島県公安委員会 第25121A010859号"],
    ["メール", "info@aisjaltd.com"]
  ]);

  // ----- 第2条 委託業務の内容 -----
  body.appendParagraph("第2条（委託業務の内容）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph(
    "甲は乙に対し、以下の業務を委託し、乙はこれを受託する。"
  );
  body.appendParagraph(
    "（1）オートオークション（AA）における中古車両の落札代行業務"
  );
  body.appendParagraph(
    "（2）落札車両の名義変更、登録手続きの補助"
  );
  body.appendParagraph(
    "（3）落札車両の陸送手配"
  );
  body.appendParagraph("");
  body.appendParagraph("希望車両情報");
  body.appendTable([
    ["車名・モデル", data.car || BL],
    ["予算上限（落札価格）", data.bid ? yen_(data.bid) : BL],
    ["想定総額", data.total ? yen_(data.total) : BL],
    ["希望グレード・仕様", data.grade || BL],
    ["希望年式", data.year || BL],
    ["走行距離上限", data.mileage || BL],
    ["その他条件", data.note || BL]
  ]);

  // ----- 第3条 代行手数料 -----
  body.appendParagraph("第3条（代行手数料）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph(
    "甲は乙に対し、以下の代行手数料を支払うものとする。"
  );
  body.appendTable([
    ["ご利用プラン", data.plan || BL],
    ["代行手数料（税込）", data.fee ? yen_(data.fee) : BL],
    ["オプション費用", data.optionsLabel || BL]
  ]);
  body.appendParagraph(
    "上記手数料にはオークション会場での検査手配費用を含む。陸送費用、名義変更費用等の実費は別途甲の負担とする。"
  );

  // ----- 第4条 車両の検査と品質 -----
  body.appendParagraph("第4条（車両の検査と品質）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph(
    "1. 乙は、落札前にオークション会場の出品票および検査評価を確認し、甲に報告するものとする。"
  );
  body.appendParagraph(
    "2. 乙は、甲の指示なく甲の予算上限を超える入札を行わないものとする。"
  );
  body.appendParagraph(
    "3. 落札車両の品質は、オークション会場の検査評価に準じるものとし、" +
    "乙は出品票記載事項を超える品質保証を行わない。"
  );

  // ----- 第5条 支払条件 -----
  body.appendParagraph("第5条（支払条件）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph(
    "1. 甲は、乙からの落札報告後 3営業日以内に、車両代金および代行手数料の全額を乙の指定口座に振り込むものとする。振込手数料は甲の負担とする。"
  );
  body.appendParagraph(
    "2. 甲が期限内に支払いを完了しない場合、乙は年14.6%の遅延損害金を請求できるものとする。"
  );
  body.appendParagraph(
    "3. ローン利用の場合は、別途オートローン契約の条件に準じるものとする。"
  );

  // ----- 第6条 クレーム期間 -----
  body.appendParagraph("第6条（クレーム期間）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph(
    "1. 甲は、車両の納車後 " + (data.claimDays || BL) + " 日以内に限り、" +
    "出品票記載内容と著しく異なる瑕疵についてクレームを申し立てることができる。"
  );
  body.appendParagraph(
    "2. 前項のクレームは、乙がオークション会場に対してクレーム申立てを行い、" +
    "会場の裁定に従うものとする。乙は会場裁定を超える責任を負わない。"
  );

  // ----- 第7条 契約の解除・違約金 -----
  body.appendParagraph("第7条（契約の解除・違約金）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph(
    "1. 甲は、乙が車両を落札する前に限り、書面による通知をもって本契約を解除できる。" +
    "この場合、甲は乙に対し事務手数料として金10,000円（税込）を支払うものとする。"
  );
  body.appendParagraph(
    "2. 乙が車両を落札した後に甲が契約を解除する場合、甲は代行手数料全額および" +
    "乙に生じた損害（オークション会場へのキャンセル料等）を違約金として支払うものとする。"
  );
  body.appendParagraph(
    "3. 甲または乙が本契約の条項に違反した場合、相手方は催告のうえ本契約を解除できる。"
  );

  // ----- 第8条 反社会的勢力の排除 -----
  body.appendParagraph("第8条（反社会的勢力の排除）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph(
    "甲および乙は、自らが暴力団、暴力団関係企業、総会屋その他の反社会的勢力に該当しないことを表明し、" +
    "将来にわたっても該当しないことを確約する。甲または乙が本条に違反した場合、" +
    "相手方は何らの催告を要せず本契約を直ちに解除できる。"
  );

  // ----- 第9条 合意管轄 -----
  body.appendParagraph("第9条（合意管轄）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph(
    "本契約に関する一切の紛争については、福島地方裁判所いわき支部を第一審の専属的合意管轄裁判所とする。"
  );

  // ----- 第10条 その他 -----
  body.appendParagraph("第10条（その他）")
    .setHeading(DocumentApp.ParagraphHeading.HEADING2);
  body.appendParagraph(
    "1. 本契約に定めのない事項については、甲乙誠意をもって協議し解決する。"
  );
  body.appendParagraph(
    "2. 本契約は2通作成し、甲乙各1通を保有する。"
  );

  // ----- 署名欄 -----
  body.appendParagraph("");
  body.appendParagraph("本契約の締結を証するため、甲乙記名押印のうえ各1通を保有する。");
  body.appendParagraph("");
  body.appendParagraph("契約日：" + (data.contractDate || today));
  body.appendParagraph("受付番号：" + orderId);
  body.appendParagraph("");
  body.appendParagraph("甲（委託者）");
  body.appendParagraph("住所：" + (data.address || BL));
  body.appendParagraph("氏名：" + (data.name || BL) + "　　　　㊞");
  body.appendParagraph("");
  body.appendParagraph("乙（受託者）");
  body.appendParagraph("〒979-0204 福島県いわき市四倉町細谷字大町1番");
  body.appendParagraph("合同会社アイズ");
  body.appendParagraph("代表社員　吉田一平　　　　㊞");
  body.appendParagraph("");
  body.appendParagraph(
    "古物商許可証　福島県公安委員会　第25121A010859号"
  ).setFontSize(9);

  doc.saveAndClose();

  // Google ドキュメント → PDF → ドライブ保存
  var file = DriveApp.getFileById(doc.getId());
  var pdf = file.getAs("application/pdf");
  var pdfFile;
  if (cfg.SELL_SHEET_PDF_FOLDER_ID) {
    pdfFile = DriveApp.getFolderById(cfg.SELL_SHEET_PDF_FOLDER_ID).createFile(pdf).setName("契約書_" + orderId + ".pdf");
  } else {
    pdfFile = DriveApp.createFile(pdf).setName("契約書_" + orderId + ".pdf");
  }
  file.setTrashed(true); // 中間のドキュメントは破棄
  return pdfFile.getUrl();
}
