/* ==========================================================================
 * AUC-AGENT 書類エンジン（クライアント側・入力→完成書類プレビュー→印刷/PDF）
 *  - AucForms.DOCS       : 書類定義の配列
 *  - AucForms.FIELDS     : 入力項目の定義（グループ別）
 *  - AucForms.render(id,data) : 指定書類の完成HTML（A4）を返す
 * 依存なし（外部ライブラリ不使用）。
 * ======================================================================== */
(function (global) {
  "use strict";

  var CO = {
    name: "合同会社アイズ",
    rep: "代表社員　吉田 一平",
    brand: "AUC-AGENT",
    addr: "福島県いわき市四倉町細谷字大町1番",
    tel: "050-1722-3365",
    mail: "info@aisjaltd.com",
    kobutsu: "福島県公安委員会許可　第25121A010859号",
    invoice: "T9380003004349"
  };

  var CSS = "\
    *{box-sizing:border-box}html,body{margin:0}\
    body{font-family:'Noto Sans CJK JP','Hiragino Kaku Gothic ProN','IPAGothic',sans-serif;color:#141414;background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}\
    .sheet{width:100%;max-width:210mm;margin:0 auto;background:#fff;padding:14mm 15mm 20mm;position:relative}\
    .doc-head{display:flex;justify-content:space-between;align-items:flex-start;font-size:11px;color:#444;margin-bottom:4px}\
    .doc-brand{font-weight:800;color:#0e1b33;font-size:12px}\
    h1.title{text-align:center;font-size:25px;font-weight:900;letter-spacing:.45em;margin:12px 0 4px;padding-left:.45em}\
    .subttl{text-align:center;font-size:12px;color:#555;margin:0 0 16px}\
    .lead{font-size:12.5px;line-height:1.9;margin:12px 0}\
    table.form{width:100%;border-collapse:collapse;margin:8px 0}\
    .form th,.form td{border:1px solid #2a2a2a;padding:8px 10px;font-size:12.5px;vertical-align:middle;line-height:1.7}\
    .form th{background:#f2f3f6;font-weight:700;width:32%;text-align:left;white-space:nowrap}\
    .sec{margin-top:18px;font-size:13px;font-weight:800;color:#0e1b33;border-left:4px solid #c9a14a;padding-left:10px}\
    .checks{font-size:12.5px;line-height:2.2;border:1px solid #2a2a2a;padding:9px 12px}\
    .checks span{display:inline-block;margin-right:20px;white-space:nowrap}\
    .box{display:inline-block;width:14px;height:14px;border:1.5px solid #333;margin-right:5px;vertical-align:-2px;border-radius:2px}\
    .box.on{background:#0e1b33;position:relative}\
    .box.on:after{content:'✓';color:#fff;font-size:11px;position:absolute;left:1px;top:-3px}\
    .seal{display:inline-block;width:52px;height:52px;border:1px dashed #bbb;border-radius:50%;color:#bbb;font-size:10px;text-align:center;line-height:52px;vertical-align:middle}\
    .daterow{font-size:13px;margin:14px 0 4px;letter-spacing:.04em}\
    .clause{font-size:12px;line-height:1.95;margin:6px 0}\
    .clause b{color:#0e1b33}\
    .note{font-size:11px;color:#666;line-height:1.8;margin-top:14px;border-top:1px dashed #ccc;padding-top:10px}\
    .foot{margin-top:22px;font-size:10px;color:#666;border-top:1px solid #ddd;padding-top:6px;display:flex;justify-content:space-between}\
    .u{border-bottom:1px solid #333;display:inline-block;min-width:70px;text-align:center;padding:0 4px}\
    .amt{font-size:15px;font-weight:800}\
    @media print{@page{size:A4;margin:15mm 14mm}.sheet{max-width:none;padding:0}}\
  ";

  /* ---------- helpers ---------- */
  function e(s) {
    return String(s == null ? "" : s).replace(/[&<>\"]/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c];
    });
  }
  // 値があれば表示、無ければ下線（空欄印刷に対応）
  function v(val, minw) {
    val = (val == null ? "" : String(val)).trim();
    if (val) return e(val);
    return '<span class="u" style="min-width:' + (minw || 80) + 'px">&nbsp;</span>';
  }
  function box(on, label) {
    return '<span><i class="box' + (on ? " on" : "") + '"></i>' + label + "</span>";
  }
  function sealCell(txt) {
    return '<td style="position:relative">&nbsp;<span style="position:absolute;right:14px;top:50%;transform:translateY(-50%)"><span class="seal">' + (txt || "印") + "</span></span></td>";
  }
  function head() {
    return '<div class="doc-head"><div class="doc-brand">' + CO.brand + "（" + CO.name + "）</div><div>" + CO.addr + "／TEL " + CO.tel + "</div></div>";
  }
  function foot() {
    return '<div class="foot"><span>' + CO.name + "　古物商許可 " + CO.kobutsu + "</span><span>登録番号 " + CO.invoice + "</span></div>";
  }
  function page(title, sub, body) {
    return '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' +
      e(title) + "｜" + CO.brand + "</title><style>" + CSS + '</style></head><body><div class="sheet">' +
      head() + '<h1 class="title">' + e(title) + "</h1>" +
      (sub ? '<p class="subttl">' + e(sub) + "</p>" : "") +
      body + foot() + "</div></body></html>";
  }

  // 受任者（当社）ブロック
  function recvRows() {
    return '<tr><th>受任者（代理人）住所</th><td>' + CO.addr + "</td></tr>" +
      "<tr><th>受任者（代理人）氏名</th><td>" + CO.name + "　" + CO.rep + "</td></tr>";
  }
  // 車両ブロック（共通）
  function vehicleRows(d, opts) {
    opts = opts || {};
    var regLabel = opts.kei ? "車両番号" : "自動車登録番号<br>又は車台番号";
    var rows =
      "<tr><th>" + regLabel + "</th><td>" + v(d.v_regno || d.v_chassis, 160) + "</td></tr>" +
      "<tr><th>車名・型式</th><td>" + v((d.v_name ? d.v_name + (d.v_grade ? " " + d.v_grade : "") : "") + (d.v_model ? "／" + d.v_model : ""), 160) + "</td></tr>";
    if (opts.detail) {
      rows =
        "<tr><th>車名・グレード</th><td>" + v(d.v_name ? d.v_name + (d.v_grade ? " " + d.v_grade : "") : "", 160) + "</td></tr>" +
        "<tr><th>型式</th><td>" + v(d.v_model, 120) + "</td><th style=\"width:16%\">車台番号</th><td>" + v(d.v_chassis, 120) + "</td></tr>" +
        "<tr><th>登録番号</th><td>" + v(d.v_regno, 120) + "</td><th>初度登録</th><td>" + v(d.v_first, 100) + "</td></tr>" +
        "<tr><th>走行距離</th><td>" + v(d.v_km ? d.v_km + " km" : "", 90) + "</td><th>車検満了日</th><td>" + v(d.v_shaken, 100) + "</td></tr>";
    }
    return rows;
  }
  function jpDate(d) {
    return d && d.d_date ? e(d.d_date) : '令和　<span class="u">　</span>　年　<span class="u">　</span>　月　<span class="u">　</span>　日';
  }

  /* ============================ 書類定義 ============================ */
  var DOCS = [
    /* 1. 委任状 */
    {
      id: "inin", name: "委任状（普通車）", cat: "名義変更", groups: ["owner", "vehicle", "tetsuzuki"],
      render: function (d) {
        return page("委任状", "普通自動車用（移転登録・抹消登録 等）",
          '<p class="lead">私は、下記の者を代理人と定め、下記自動車について下記事項に関する一切の権限を委任します。</p>' +
          '<div class="sec">代理人（受任者）</div><table class="form">' + recvRows() + "</table>" +
          '<div class="sec">対象自動車</div><table class="form">' + vehicleRows(d) + "</table>" +
          '<div class="sec">委任事項（該当に✓）</div><div class="checks">' +
          box(d.t_iten !== false, "移転登録（名義変更）") + box(d.t_henko, "変更登録") +
          box(d.t_ichiji, "一時抹消登録") + box(d.t_eikyu, "永久抹消登録") +
          box(d.t_kinyu, "自動車検査証の記入申請") +
          '<span><i class="box"></i>その他（' + v(d.t_other, 140) + "）</span></div>" +
          '<p class="daterow">' + jpDate(d) + "</p>" +
          '<div class="sec">委任者（ご本人）</div><table class="form">' +
          "<tr><th>住所（印鑑証明書のとおり）</th><td>" + v(d.o_addr, 200) + "</td></tr>" +
          "<tr><th>氏名</th>" + (d.o_name ? '<td style="position:relative">' + e(d.o_name) + '<span style="position:absolute;right:14px;top:50%;transform:translateY(-50%)"><span class="seal">実印</span></span></td>' : sealCell("実印")) + "</tr></table>" +
          '<p class="note">※ 委任者欄はご本人が自署し、<b>実印</b>を押印してください（印鑑登録証明書と同一の印）。住所・氏名は印鑑登録証明書の記載どおりにご記入ください。</p>'
        );
      }
    },
    /* 2. 譲渡証明書 */
    {
      id: "joto", name: "譲渡証明書（普通車）", cat: "名義変更", groups: ["owner", "vehicle", "deal"],
      render: function (d) {
        return page("譲渡証明書", "普通自動車用",
          '<table class="form"><tr><th>車台番号</th><td>' + v(d.v_chassis, 160) + '</td><th style="width:16%">型式</th><td>' + v(d.v_model, 100) + "</td></tr></table>" +
          '<p class="lead">上記の自動車を下記のとおり譲渡したことを相違なく証明します。</p>' +
          '<div class="sec">譲受人（当社）</div><table class="form"><tr><th>住所</th><td>' + CO.addr + "</td></tr><tr><th>氏名</th><td>" + CO.name + "　" + CO.rep + "</td></tr></table>" +
          '<div class="sec">譲渡人（お客様）</div><table class="form">' +
          "<tr><th>譲渡年月日</th><td>" + jpDate(d) + "</td></tr>" +
          "<tr><th>住所（印鑑証明書のとおり）</th><td>" + v(d.o_addr, 200) + "</td></tr>" +
          "<tr><th>氏名</th>" + (d.o_name ? '<td style="position:relative">' + e(d.o_name) + '<span style="position:absolute;right:14px;top:50%;transform:translateY(-50%)"><span class="seal">実印</span></span></td>' : sealCell("実印")) + "</tr></table>" +
          '<p class="note">※ 譲渡人欄はご本人が自署し、<b>実印</b>を押印してください（印鑑登録証明書と同一の印）。記載事項は車検証・印鑑登録証明書と一致させてください。</p>'
        );
      }
    },
    /* 3. 申請依頼書（軽） */
    {
      id: "keiirai", name: "申請依頼書（軽自動車）", cat: "名義変更", groups: ["owner", "vehicle"],
      render: function (d) {
        return page("申請依頼書", "軽自動車用（軽自動車検査協会 手続）",
          '<p class="lead">下記の者に、下記自動車の手続に関する一切の権限を依頼します。</p>' +
          '<div class="sec">受任者（当社）</div><table class="form">' + recvRows() + "</table>" +
          '<div class="sec">対象自動車</div><table class="form">' + vehicleRows(d, { kei: true }) + "</table>" +
          '<div class="sec">手続内容（該当に✓）</div><div class="checks">' +
          box(d.t_iten !== false, "名義変更（検査証記入申請）") + box(d.t_shozoku, "使用者・所有者の変更") +
          box(d.t_jusho, "住所変更") + box(d.t_ichiji, "一時使用中止（抹消）") +
          '<span><i class="box"></i>その他（' + v(d.t_other, 140) + "）</span></div>" +
          '<p class="daterow">' + jpDate(d) + "</p>" +
          '<div class="sec">依頼者（ご本人）</div><table class="form">' +
          "<tr><th>住所</th><td>" + v(d.o_addr, 200) + "</td></tr>" +
          "<tr><th>氏名</th>" + (d.o_name ? '<td style="position:relative">' + e(d.o_name) + '<span style="position:absolute;right:14px;top:50%;transform:translateY(-50%)"><span class="seal">認印</span></span></td>' : sealCell("認印")) + "</tr></table>" +
          '<p class="note">※ 軽自動車は実印・印鑑登録証明書は不要ですが、<b>認印</b>の押印が必要です。住所・氏名は車検証の記載どおりにご記入ください。</p>'
        );
      }
    },
    /* 4. 自動車買取契約書 */
    {
      id: "baitori", name: "自動車売買（買取）契約書", cat: "契約", groups: ["owner", "vehicle", "deal"],
      render: function (d) {
        var price = d.d_price ? '<span class="amt">￥' + e(Number(String(d.d_price).replace(/[^\d]/g, "")).toLocaleString()) + "</span>" : '金 <span class="u" style="min-width:120px">&nbsp;</span> 円';
        return page("自動車売買契約書", "買取（甲＝売主・お客様／乙＝買主・当社）",
          '<table class="form">' + vehicleRows(d, { detail: true }) + "</table>" +
          '<p class="clause"><b>第1条（売買）</b> 甲は乙に対し、上記自動車（以下「本車両」）を売り渡し、乙はこれを買い受けた。</p>' +
          '<p class="clause"><b>第2条（売買代金）</b> 本車両の売買代金は ' + price + " とする。</p>" +
          '<p class="clause"><b>第3条（支払）</b> 乙は、名義変更に必要な書類の受領及び本車両の引渡し確認後、甲の指定口座に代金を振り込む方法により支払う。</p>' +
          '<p class="clause"><b>第4条（引渡し・移転登録）</b> 甲は本車両及び必要書類を乙に引き渡す。乙は引渡し後すみやかに移転登録（名義変更）を行う。</p>' +
          '<p class="clause"><b>第5条（保証・告知）</b> 甲は、本車両に申告外の重大な瑕疵（修復歴・冠水・メーター交換等）がないことを表明する。契約後に申告外の重大な瑕疵が判明した場合、両者協議のうえ代金の調整又は契約の解除を行うことができる。</p>' +
          '<p class="clause"><b>第6条（協議）</b> 本契約に定めのない事項は、法令及び信義則に従い両者協議のうえ解決する。</p>' +
          '<p class="daterow" style="margin-top:16px">契約年月日　' + jpDate(d) + "</p>" +
          '<table class="form"><tr><th>甲（売主）住所</th><td>' + v(d.o_addr, 200) + "</td></tr>" +
          "<tr><th>甲（売主）氏名</th>" + (d.o_name ? '<td style="position:relative">' + e(d.o_name) + '<span style="position:absolute;right:14px;top:50%;transform:translateY(-50%)"><span class="seal">印</span></span></td>' : sealCell("印")) + "</tr>" +
          "<tr><th>乙（買主）</th><td>" + CO.addr + "　" + CO.name + "　" + CO.rep + "</td></tr></table>" +
          '<p class="note">※ 本書は一般的な買取契約の雛形です。実運用前に専門家（行政書士等）のご確認をおすすめします。</p>'
        );
      }
    },
    /* 5. 車両お預り証 */
    {
      id: "azukari", name: "車両お預り証", cat: "契約", groups: ["owner", "vehicle", "deal"],
      render: function (d) {
        return page("車両お預り証", "出品代行・手続きのためのお預り",
          '<p class="lead">下記のお客様より、下記自動車及び関係書類をお預りしましたことを証します。</p>' +
          '<table class="form"><tr><th>お客様氏名</th><td>' + v(d.o_name, 160) + '</td><th style="width:16%">お預り日</th><td>' + jpDate(d) + "</td></tr>" +
          "<tr><th>ご連絡先</th><td>" + v(d.o_tel, 120) + '</td><th>担当</th><td>' + v(d.staff, 80) + "</td></tr></table>" +
          '<div class="sec">お預りした自動車</div><table class="form">' + vehicleRows(d, { detail: true }) + "</table>" +
          '<div class="sec">お預りした書類・物品（該当に✓）</div><div class="checks">' +
          box(d.a_shaken, "車検証") + box(d.a_jibai, "自賠責保険証") + box(d.a_inkan, "印鑑証明書") +
          box(d.a_recycle, "リサイクル券") + box(d.a_key, "鍵（　本）") + box(d.a_other, "その他") + "</div>" +
          '<p class="note">※ 本お預り証は、本車両の売買・所有権の移転を証するものではありません。手続き完了後、精算のうえご返却又はご入金いたします。大切に保管してください。</p>' +
          '<p class="daterow" style="margin-top:14px">' + CO.brand + "（" + CO.name + "）　担当者　" + v(d.staff, 100) + '　<span class="seal" style="width:44px;height:44px;line-height:44px">印</span></p>'
        );
      }
    },
    /* 6. 振込先指定書 */
    {
      id: "furikomi", name: "振込先指定書", cat: "契約", groups: ["owner", "bank"],
      render: function (d) {
        return page("振込先指定書", "売却代金のお振込先口座",
          '<p class="lead">売却代金の振込先として、下記口座を指定します。</p>' +
          '<table class="form"><tr><th>ご氏名（口座名義と同一）</th><td>' + v(d.o_name, 160) + "</td></tr>" +
          "<tr><th>金融機関名</th><td>" + v(d.b_bank, 160) + '　銀行・信金・信組・農協</td></tr>' +
          "<tr><th>支店名</th><td>" + v(d.b_branch, 140) + "　支店</td></tr>" +
          "<tr><th>預金種別</th><td>" + (d.b_type ? e(d.b_type) : box(true, "普通") + box(false, "当座")) + "</td></tr>" +
          "<tr><th>口座番号</th><td>" + v(d.b_no, 140) + "</td></tr>" +
          "<tr><th>口座名義（カナ）</th><td>" + v(d.b_holder || d.o_kana, 180) + "</td></tr></table>" +
          '<p class="daterow" style="margin-top:16px">' + jpDate(d) + "　ご氏名　" + v(d.o_name, 140) + '　<span class="seal" style="width:44px;height:44px;line-height:44px">印</span></p>' +
          '<p class="note">※ 口座名義はカナで正確にご記入ください。名義相違・記入誤りは振込不能の原因となります。原則ご本人名義の口座へお振込みします。</p>'
        );
      }
    },
    /* 7. 個人情報取扱い同意書 */
    {
      id: "doui", name: "個人情報取扱い同意書", cat: "契約", groups: ["owner"],
      render: function (d) {
        return page("個人情報の取扱いに関する同意書", "",
          '<p class="lead">' + CO.name + "（以下「当社」）が取得する個人情報の取扱いについて、下記に同意します。</p>" +
          '<p class="clause"><b>1. 利用目的</b>　当社は、取得した個人情報を、①自動車の売買・出品代行・名義変更等の手続き、②本人確認、③連絡・アフターサービス、④法令に基づく対応、の目的で利用します。</p>' +
          '<p class="clause"><b>2. 第三者提供</b>　当社は、手続きに必要な範囲で、運輸支局・軽自動車検査協会・オークション会場・提携陸送/金融機関等に個人情報を提供する場合があります。</p>' +
          '<p class="clause"><b>3. 管理</b>　当社は、個人情報を適切に管理し、法令に定める場合を除き、本人の同意なく目的外に利用しません。</p>' +
          '<p class="clause"><b>4. 開示等の請求</b>　ご本人は、当社の保有する自己の個人情報の開示・訂正・利用停止を請求できます。窓口：' + CO.tel + "／" + CO.mail + "</p>" +
          '<p class="daterow" style="margin-top:18px">' + jpDate(d) + "</p>" +
          '<table class="form"><tr><th>ご住所</th><td>' + v(d.o_addr, 200) + "</td></tr>" +
          "<tr><th>ご氏名</th>" + (d.o_name ? '<td style="position:relative">' + e(d.o_name) + '<span style="position:absolute;right:14px;top:50%;transform:translateY(-50%)"><span class="seal">印</span></span></td>' : sealCell("印")) + "</tr></table>" +
          '<p class="note">※ 本同意書は一般的な雛形です。プライバシーポリシー全文はWebサイトのプライバシーポリシーをご確認ください。</p>'
        );
      }
    },
    /* 8. 車両状態申告書 */
    {
      id: "shinkoku", name: "車両状態申告書", cat: "査定", groups: ["owner", "vehicle", "misc"],
      render: function (d) {
        var rep = d.m_repair || "";
        return page("車両状態申告書", "出品前の状態申告（正確な申告が減額・返品リスクの回避につながります）",
          '<table class="form">' + vehicleRows(d, { detail: true }) +
          '<tr><th>ボディカラー</th><td colspan="3">' + v(d.v_color, 120) + "</td></tr></table>" +
          '<div class="sec">修復歴（骨格部位の修正・交換）</div><div class="checks">' +
          box(rep === "なし", "なし") + '<span><i class="box' + (rep === "あり" ? " on" : "") + '"></i>あり（箇所：' + v(d.m_repair_where, 200) + "）</span>" + box(rep === "不明", "不明") + "</div>" +
          '<div class="sec">主要装備（該当に✓）</div><div class="checks">' +
          box(0, "純正ナビ") + box(0, "社外ナビ") + box(0, "バックカメラ") + box(0, "ETC") + box(0, "ドラレコ") +
          box(0, "サンルーフ") + box(0, "レザー") + box(0, "4WD") + box(0, "両側電動スライド") +
          '<span><i class="box"></i>その他（' + v(d.m_equip, 120) + "）</span></div>" +
          '<div class="sec">内装・臭い</div><div class="checks">' +
          "<span>喫煙：" + box(0, "なし") + box(0, "あり") + "</span><span>ペット：" + box(0, "なし") + box(0, "あり") + "</span><span>臭い・汚れ：" + box(0, "なし") + box(0, "あり") + "</span></div>" +
          '<div class="sec">外装キズ・へこみ・その他不具合（具体的に）</div>' +
          '<table class="form"><tr><td style="height:60px;vertical-align:top">' + e(d.m_note || "") + "</td></tr></table>" +
          '<p class="daterow" style="margin-top:14px">申告日　' + jpDate(d) + "　氏名　" + v(d.o_name, 140) + '　<span class="seal" style="width:42px;height:42px;line-height:42px">印</span></p>' +
          '<p class="note">※ 申告内容と異なる重大な瑕疵が後日判明した場合、減額・返品・違約金の対象となることがあります。不明な項目は「不明」とご記入ください。</p>'
        );
      }
    },
    /* 9. 売却（出品代行）申込書 */
    {
      id: "moushikomi", name: "売却（出品代行）申込書", cat: "査定", groups: ["owner", "vehicle", "deal"],
      render: function (d) {
        return page("売却（出品代行）申込書", "",
          '<p class="lead">下記のとおり、自動車の出品代行（売却）を申し込みます。</p>' +
          '<div class="sec">お申込者</div><table class="form">' +
          "<tr><th>氏名</th><td>" + v(d.o_name, 140) + '</td><th style="width:16%">電話</th><td>' + v(d.o_tel, 100) + "</td></tr>" +
          "<tr><th>住所</th><td colspan=\"3\">" + v(d.o_addr, 220) + "</td></tr></table>" +
          '<div class="sec">出品車両</div><table class="form">' + vehicleRows(d, { detail: true }) +
          '<tr><th>ボディカラー</th><td colspan="3">' + v(d.v_color, 120) + "</td></tr></table>" +
          '<div class="sec">ご希望</div><table class="form">' +
          "<tr><th>希望売却額（目安）</th><td>" + (d.d_price ? "￥" + e(Number(String(d.d_price).replace(/[^\d]/g, "")).toLocaleString()) : v("", 120)) + "</td></tr>" +
          "<tr><th>希望出品時期</th><td>" + v(d.d_handover, 120) + "</td></tr>" +
          "<tr><th>クリーニング</th><td>" + box(0, "希望する") + box(0, "希望しない") + "</td></tr></table>" +
          '<p class="daterow" style="margin-top:16px">申込日　' + jpDate(d) + "　氏名　" + v(d.o_name, 140) + '　<span class="seal" style="width:42px;height:42px;line-height:42px">印</span></p>' +
          '<p class="note">※ 手数料・想定手取りは査定マニュアル及び出品手取りシミュレーションをご確認ください。正式なご契約は別途出品代行契約書によります。</p>'
        );
      }
    },
    /* 10. 必要書類チェックリスト（データ不要） */
    {
      id: "checklist", name: "必要書類チェックリスト", cat: "査定", groups: [],
      render: function () {
        function row(t, memo) { return '<tr><td style="text-align:center;width:8%"><i class="box"></i></td><td style="width:44%">' + t + "</td><td>" + memo + "</td></tr>"; }
        return page("必要書類チェックリスト", "売却（出品代行）・名義変更に必要な書類",
          '<div class="sec">普通自動車</div><table class="form"><tr><th style="width:8%">✓</th><th style="width:44%">書類</th><th>備考</th></tr>' +
          row("自動車検査証（車検証）", "原本") + row("印鑑登録証明書", "発行3ヶ月以内・1通") + row("実印", "各書類に押印") +
          row("委任状（当社書式）", "実印を押印") + row("譲渡証明書（当社書式）", "実印を押印") +
          row("自賠責保険証明書", "原本") + row("自動車税納税証明書", "ある場合") +
          row("リサイクル券", "預託済の場合・写し可") + row("車両状態申告書（当社書式）", "ご記入・署名") + "</table>" +
          '<div class="sec">軽自動車</div><table class="form"><tr><th style="width:8%">✓</th><th style="width:44%">書類</th><th>備考</th></tr>' +
          row("自動車検査証（車検証）", "原本") + row("認印", "実印・印鑑証明は不要") +
          row("申請依頼書（当社書式）", "認印を押印") + row("自賠責保険証明書", "原本") +
          row("リサイクル券", "預託済の場合・写し可") + row("車両状態申告書（当社書式）", "ご記入・署名") + "</table>" +
          '<p class="note">※ ローン残債がある場合は所有者（ローン会社等）の情報・完済予定をご連絡ください。所有者・使用者が異なる場合や住所変更がある場合は追加書類（住民票・戸籍附票等）が必要になることがあります。</p>'
        );
      }
    }
  ];

  /* ============================ 入力項目定義 ============================ */
  var FIELDS = {
    owner: {
      label: "お客様情報", items: [
        { k: "o_name", l: "氏名", ph: "山田 太郎" },
        { k: "o_kana", l: "フリガナ", ph: "ヤマダ タロウ" },
        { k: "o_zip", l: "郵便番号", ph: "970-0000" },
        { k: "o_addr", l: "住所", ph: "福島県いわき市…", wide: true },
        { k: "o_tel", l: "電話番号", ph: "090-0000-0000" }
      ]
    },
    vehicle: {
      label: "車両情報", items: [
        { k: "v_name", l: "車名", ph: "トヨタ アルファード" },
        { k: "v_grade", l: "グレード", ph: "2.5S" },
        { k: "v_model", l: "型式", ph: "DBA-AGH30W" },
        { k: "v_chassis", l: "車台番号", ph: "AGH30-0000000" },
        { k: "v_regno", l: "登録番号／車両番号", ph: "いわき 300 あ 12-34" },
        { k: "v_first", l: "初度登録", ph: "令和3年4月" },
        { k: "v_km", l: "走行距離(km)", ph: "45000" },
        { k: "v_shaken", l: "車検満了日", ph: "令和7年4月" },
        { k: "v_color", l: "ボディカラー", ph: "パール" }
      ]
    },
    deal: {
      label: "取引・金額", items: [
        { k: "d_date", l: "契約／譲渡年月日", ph: "令和7年7月21日" },
        { k: "d_price", l: "金額（円）", ph: "1200000" },
        { k: "d_handover", l: "引渡し／希望時期", ph: "令和7年8月" }
      ]
    },
    bank: {
      label: "振込先口座", items: [
        { k: "b_bank", l: "金融機関名", ph: "東邦銀行" },
        { k: "b_branch", l: "支店名", ph: "いわき" },
        { k: "b_type", l: "預金種別", ph: "普通" },
        { k: "b_no", l: "口座番号", ph: "1234567" },
        { k: "b_holder", l: "口座名義(カナ)", ph: "ヤマダ タロウ" }
      ]
    },
    misc: {
      label: "状態（申告書用）", items: [
        { k: "m_repair", l: "修復歴", type: "select", opts: ["", "なし", "あり", "不明"] },
        { k: "m_repair_where", l: "修復歴の箇所", ph: "リアフロア 等" },
        { k: "m_equip", l: "装備メモ", ph: "純正ナビ・ETC 等" },
        { k: "m_note", l: "外装キズ等メモ", ph: "左後ドアに小キズ 等", wide: true }
      ]
    },
    tetsuzuki: {
      label: "委任事項", items: [
        { k: "t_iten", l: "移転登録(名義変更)", type: "check", def: true },
        { k: "t_henko", l: "変更登録", type: "check" },
        { k: "t_ichiji", l: "一時抹消登録", type: "check" },
        { k: "t_eikyu", l: "永久抹消登録", type: "check" },
        { k: "t_kinyu", l: "検査証の記入申請", type: "check" }
      ]
    }
  };

  global.AucForms = { CO: CO, DOCS: DOCS, FIELDS: FIELDS, render: function (id, data) {
    var doc = DOCS.filter(function (x) { return x.id === id; })[0] || DOCS[0];
    return doc.render(data || {});
  } };
})(window);
