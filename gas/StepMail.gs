/**
 * StepMail.gs — BUYMO ステップメール（GASだけで完結する自動フォローメール）
 *
 * 何をする？
 *   査定/問い合わせフォーム（type:"buymo"）が届くと、メールアドレスを
 *   「BUYMOステップメール」シートに登録し、Day0/1/3/7 の4通を自動送信します。
 *   各メールには「会員専用ページ」への誘導CTAを入れています。
 *
 * セットアップ（初回のみ）
 *   1) stepCfg_() の SITE_URL / MEMBER_URL / TEL を自社のものに。
 *   2) GASエディタ右の「トリガー」→ runStepMails を
 *      「時間主導型 → 日タイマー（例：毎日 朝9時台）」で追加。
 *      （こまめに送るなら「時タイマー（1時間ごと）」でもOK）
 *   3) 初回実行時に Gmail 送信の権限承認が出るので許可。
 *      ※送信は実行ユーザーの Gmail から行われます（1日の送信上限あり）。
 *
 * 解除（配信停止）
 *   各メール末尾のリンク（?action=unsub&t=…）で、その人を「停止」にします。
 */

// ===== 設定（ここを編集） =====
function stepCfg_() {
  return {
    BRAND: "BUYMO",
    FROM_NAME: "BUYMO 査定サポート",
    SITE_URL: "",                    // 例: "https://buymo.example.jp"（末尾スラッシュ無し）。空ならリンクは相対表記
    MEMBER_URL: "/member.html",      // 会員マイページ（査定状況の確認）
    FORM_URL: "/buymo-contact.html", // 再査定/問い合わせ
    TEL: "0120-123-456",
    SHEET: "BUYMOステップメール",
    // WP/Zapier等から POST {type:"stepmail", token, email,...} で配信を発動する際の合言葉。
    // 空ならトークン無しでも受け付け（公開エンドポイントなので本番では必ず設定）。
    TRIGGER_TOKEN: ""
  };
}

// ===== 配信ステップ（経過日数・件名・本文ビルダー） =====
function stepSequence_() {
  return [
    {
      afterDays: 0,
      subject: "【BUYMO】査定のお申し込みありがとうございます",
      build: function (lead, u) {
        var greet = lead.name ? lead.name + " 様" : "お客様";
        var lines = [
          greet,
          "",
          "この度はBUYMOへ査定をお申し込みいただきありがとうございます。",
          "担当者より最短即日でご連絡いたします。",
          "",
          "▼ 会員専用ページでは、査定状況や買取の進捗をいつでもご確認いただけます。",
          "　" + u.member,
          "",
          "お急ぎの方はお電話でも承ります：" + u.tel,
          ""
        ];
        return mailBody_(lead, u, "査定のお申し込みを受け付けました", lines,
          "会員専用ページを見る", u.member);
      }
    },
    {
      afterDays: 1,
      subject: "【BUYMO】買取の流れと、よくあるご質問",
      build: function (lead, u) {
        var lines = [
          (lead.name ? lead.name + " 様" : "お客様"),
          "",
          "BUYMOの買取は、お申込み→無料出張査定→ご契約→最短即日入金の4ステップ。",
          "事故車・不動車・廃車もOK、査定料・手続き代行はすべて無料です。",
          "",
          "・名義変更などの面倒な手続きは無料で代行",
          "・他社で断られた車もまずはご相談ください",
          "",
          "▼ 会員専用ページから、査定状況の確認や追加のご相談ができます。",
          "　" + u.member,
          ""
        ];
        return mailBody_(lead, u, "買取の流れ・よくあるご質問", lines,
          "会員ページで状況を確認", u.member);
      }
    },
    {
      afterDays: 3,
      subject: "【BUYMO】今が売りどき？査定は無料です",
      build: function (lead, u) {
        var lines = [
          (lead.name ? lead.name + " 様" : "お客様"),
          "",
          "車は年式・走行距離が進むほど価値が下がりやすいもの。",
          "「まだ迷っている」方も、無料査定で“今の価値”だけ確認しておくのがおすすめです。",
          "",
          "BUYMOは独自の販売ルートで、相場より高い査定をめざします。",
          "",
          "▼ 会員専用ページから、そのまま査定のご相談へ進めます。",
          "　" + u.member,
          ""
        ];
        return mailBody_(lead, u, "今が売りどき？無料査定のご案内", lines,
          "会員ページから相談する", u.member);
      }
    },
    {
      afterDays: 7,
      subject: "【BUYMO】まだ間に合います／無料査定のご案内",
      build: function (lead, u) {
        var lines = [
          (lead.name ? lead.name + " 様" : "お客様"),
          "",
          "その後、お車のご売却はお決まりでしょうか。",
          "BUYMOなら査定・出張・手続きすべて無料。まだ間に合います。",
          "",
          "ご不明点はお電話でもお気軽に：" + u.tel,
          "",
          "▼ 会員専用ページ（査定状況・お手続き）",
          "　" + u.member,
          ""
        ];
        return mailBody_(lead, u, "まだ間に合います／無料査定のご案内", lines,
          "会員ページへ", u.member);
      }
    }
  ];
}

// ===== 申込時に登録（WebApp.gs の handleBuymoLead_ から呼ばれる） =====
function enrollStepMail_(d) {
  try {
    var email = String((d && d.email) || "").trim();
    if (!email || email.indexOf("@") < 0) return;
    var cfg = stepCfg_();
    var ss = openBook_();
    var sh = ss.getSheetByName(cfg.SHEET) || ensureSheet_(ss, cfg.SHEET, [
      "登録日時", "リードID", "お名前", "メール", "ジャンル", "現在ステップ", "次回送信予定", "状態", "解除トークン"
    ]);
    if (isEnrolled_(sh, email)) return; // 配信中の重複は登録しない
    sh.appendRow([new Date(), (d.id || ""), (d.name || ""), email, (d.genre || d.source || ""), 0, new Date(), "配信中", Utilities.getUuid()]);
  } catch (err) {
    Logger.log("enrollStepMail_: " + err);
  }
}

function isEnrolled_(sh, email) {
  var v = sh.getDataRange().getValues();
  for (var r = 1; r < v.length; r++) {
    if (String(v[r][3]).toLowerCase() === email.toLowerCase() && v[r][7] === "配信中") return true;
  }
  return false;
}

// ===== トリガーで定期実行：送信時刻が来たステップを送る =====
function runStepMails() {
  var cfg = stepCfg_();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET);
  if (!sh) return;
  var seq = stepSequence_();
  var v = sh.getDataRange().getValues();
  var now = new Date();
  var sent = 0;

  for (var r = 1; r < v.length; r++) {
    if (v[r][7] !== "配信中") continue;
    var step = Number(v[r][5]) || 0;
    if (step >= seq.length) { sh.getRange(r + 1, 8).setValue("完了"); continue; }
    var nextAt = v[r][6] ? new Date(v[r][6]) : now;
    if (nextAt > now) continue;

    var lead = { name: v[r][2], email: v[r][3], genre: v[r][4], token: v[r][8] };
    try {
      sendStepMail_(seq[step], lead);
      sent++;
    } catch (e) {
      Logger.log("sendStepMail_ err: " + e);
      continue;
    }

    var nstep = step + 1;
    sh.getRange(r + 1, 6).setValue(nstep);
    if (nstep >= seq.length) {
      sh.getRange(r + 1, 7).setValue("");
      sh.getRange(r + 1, 8).setValue("完了");
    } else {
      var na = new Date(now.getTime());
      na.setDate(na.getDate() + (seq[nstep].afterDays - seq[step].afterDays));
      sh.getRange(r + 1, 7).setValue(na);
    }
  }
  Logger.log("runStepMails: sent=" + sent);
  return sent;
}

// ===== 1通送信 =====
function sendStepMail_(stepObj, lead) {
  var cfg = stepCfg_();
  var u = mailUrls_(lead);
  var b = stepObj.build(lead, u);
  GmailApp.sendEmail(lead.email, stepObj.subject, b.text, {
    name: cfg.FROM_NAME,
    htmlBody: b.html
  });
}

// ===== 配信停止（doGet の action=unsub から呼ばれる） =====
function unsubscribeByToken_(token) {
  token = String(token || "").trim();
  if (!token) return "リンクが正しくありません。";
  var cfg = stepCfg_();
  var ss = openBook_();
  var sh = ss.getSheetByName(cfg.SHEET);
  if (!sh) return "対象が見つかりませんでした。";
  var v = sh.getDataRange().getValues();
  for (var r = 1; r < v.length; r++) {
    if (String(v[r][8]) === token) {
      sh.getRange(r + 1, 8).setValue("停止");
      return "配信を停止しました。ご利用ありがとうございました。";
    }
  }
  return "すでに停止済み、または対象が見つかりませんでした。";
}

// ===== URL・本文ヘルパー =====
function absUrl_(p) {
  var cfg = stepCfg_();
  if (/^https?:\/\//.test(p)) return p;
  return (cfg.SITE_URL || "") + p;
}
function webAppUrl_() {
  try { return ScriptApp.getService().getUrl() || ""; } catch (e) { return ""; }
}
function mailUrls_(lead) {
  var cfg = stepCfg_();
  var member = absUrl_(cfg.MEMBER_URL);
  if (lead.email) member += (member.indexOf("?") < 0 ? "?" : "&") + "email=" + encodeURIComponent(lead.email);
  var unsub = webAppUrl_();
  if (unsub) unsub += (unsub.indexOf("?") < 0 ? "?" : "&") + "action=unsub&t=" + encodeURIComponent(lead.token || "");
  return { member: member, form: absUrl_(cfg.FORM_URL), tel: cfg.TEL, unsub: unsub, brand: cfg.BRAND };
}

// プレーン＋HTML本文を作る（CTAは会員ページへ）
function mailBody_(lead, u, heading, lines, ctaLabel, ctaUrl) {
  var text = lines.join("\n") +
    "\n----------------------------------------\n" +
    u.brand + "（合同会社アイズ）\n" +
    "TEL " + u.tel + "／受付 平日8:00〜17:00\n" +
    (u.unsub ? "配信停止： " + u.unsub + "\n" : "");

  var esc = function (s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); };
  var body = lines.map(function (l) { return l === "" ? "<br>" : esc(l); }).join("<br>");
  var html =
    '<div style="font-family:\'Noto Sans JP\',sans-serif;max-width:560px;margin:0 auto;color:#333;">' +
      '<div style="background:#FF6B35;color:#fff;padding:16px 20px;border-radius:12px 12px 0 0;font-weight:700;font-size:18px;">🐮 ' + esc(u.brand) + '｜' + esc(heading) + '</div>' +
      '<div style="border:1px solid #eee;border-top:none;border-radius:0 0 12px 12px;padding:22px 20px;line-height:1.8;">' +
        '<p style="margin:0 0 18px;">' + body + '</p>' +
        '<p style="text-align:center;margin:24px 0;"><a href="' + ctaUrl + '" style="display:inline-block;background:#FF6B35;color:#fff;text-decoration:none;font-weight:700;padding:14px 28px;border-radius:30px;">' + esc(ctaLabel) + ' ›</a></p>' +
        '<p style="font-size:13px;color:#888;border-top:1px solid #eee;padding-top:14px;margin-top:20px;">' +
          esc(u.brand) + '（合同会社アイズ）／TEL ' + esc(u.tel) + '（平日8:00〜17:00）' +
          (u.unsub ? '<br><a href="' + u.unsub + '" style="color:#aaa;">配信を停止する</a>' : '') +
        '</p>' +
      '</div>' +
    '</div>';
  return { text: text, html: html };
}
