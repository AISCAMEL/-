/**
 * APPREX 連携 GAS（1本で完結・正式版）
 * ------------------------------------------------------------------
 * WordPress（テーマ apprex）から届く Webhook を種類別に処理します。
 *
 *  event = "inquiry" / "order"      → スプレッドシート記録 + Asana タスク + Slack 通知
 *  event = "post_published"         → Slack 通知 + SNS 投稿（LINE / Facebook / Zapier 経由でX・IG）
 *
 * WordPress から届くペイロード（envelope）:
 *  { event, token, site, time, data }
 *   - inquiry data : id, type, type_label, name, company, email, phone, message, meeting_at, admin_url
 *   - order   data : id, type(=estimate), name, company, email, message, service, plan,
 *                    monthly, initial, annual, source, admin_url
 *   - post    data : id, title, url, excerpt, image, ai
 *
 * セットアップ手順:
 *  1) スプレッドシートを作成し、その ID を SHEET_ID に貼る（URL の /d/●●●/edit の●●●）
 *     ※ 見出し行はスクリプトが自動作成します（タブが無ければ自動生成）
 *  2) 拡張機能 > Apps Script に本コードを貼り付け、下の CONFIG を設定
 *  3) エディタで testConfig() を一度実行 → 設定（シート接続・Slack）を事前確認
 *  4) デプロイ > 新しいデプロイ > 種類「ウェブアプリ」/ アクセス「全員」
 *  5) 発行された …/exec URL をブラウザで開く → "APPREX GAS: OK" が出れば疎通OK
 *  6) その …/exec URL を WordPress（設定 > APPREX 連携 > GAS Webhook URL）に貼る
 *  7) SHARED_TOKEN を WP の「GAS 共有トークン」と同じ値にする（空は不可）
 * ------------------------------------------------------------------
 */

const CONFIG = {
  SHARED_TOKEN : 'ここにWPと同じ合言葉',                 // 必須・空不可（なりすまし防止）

  // --- お問い合わせ/発注 用 ---
  SHEET_ID     : 'スプレッドシートのID',                  // 受付台帳
  SHEET_NAME   : '受付',
  SLACK_WEBHOOK: '',                                      // Slack Incoming Webhook（任意）
  ASANA_TOKEN  : '',                                      // Asana 個人アクセストークン（任意）
  ASANA_PROJECT: '',                                      // Asana プロジェクトGID（任意）

  // --- 記事公開→SNS 用 ---
  SLACK_POST_ENABLED: true,                               // 記事公開を GAS からも Slack 通知するか
                                                          //   ※ WordPress 側（apprex_slack_webhook）でも
                                                          //     記事を Slack 投稿している場合は false（二重防止）
  LINE_TOKEN   : '',                                      // LINE Messaging API チャネルアクセストークン（任意）
  FB_PAGE_ID   : '',                                      // Facebookページ ID（任意）
  FB_PAGE_TOKEN: '',                                      // Facebookページ アクセストークン（任意）
  ZAPIER_HOOK  : ''                                       // X / Instagram は Zapier 経由が簡単（任意）
};

/** 受付台帳の見出し（列の並び）。 */
const HEADERS = [
  '受付日時', '種別', 'お名前', '会社名', 'メール', '電話',
  'サービス', 'プラン', '月額(円)', '初期費用(円)', '年間目安(円)',
  '内容・ご要望', 'ご希望日時', '流入元', '管理画面URL', 'サイト'
];

/* ============================================================
 * エントリポイント
 * ========================================================== */

/** 疎通確認用（ブラウザで …/exec を開いたとき）。 */
function doGet() {
  return out_({ ok: true, message: 'APPREX GAS: OK' });
}

/** WordPress からの Webhook 受信。 */
function doPost(e) {
  try {
    // 1) ボディ取得（エディタ実行や空POSTでも落とさない）
    if (!e || !e.postData || !e.postData.contents) {
      return out_({ ok: false, error: 'no_body' }, 400);
    }
    let body;
    try {
      body = JSON.parse(e.postData.contents);
    } catch (parseErr) {
      return out_({ ok: false, error: 'invalid_json' }, 400);
    }

    // 2) 認証（共有トークン）。設定側が空、または不一致は拒否。
    if (!CONFIG.SHARED_TOKEN || String(CONFIG.SHARED_TOKEN).indexOf('ここに') === 0) {
      return out_({ ok: false, error: 'server_token_unset' }, 500);
    }
    if (!safeEqual_(body.token, CONFIG.SHARED_TOKEN)) {
      return out_({ ok: false, error: 'forbidden' }, 403);
    }

    const d = body.data || {};

    // 3) イベント振り分け
    switch (body.event) {
      case 'inquiry':
      case 'order':
        logToSheet_(body, d);   // どの種別でも必ずスプレッドシートに1行記録
        notifyTeam_(body, d);   // Asana タスク + Slack 通知
        break;
      case 'post_published':
        handlePost_(d);
        break;
      default:
        return out_({ ok: false, error: 'unknown_event', event: body.event || null }, 422);
    }
    return out_({ ok: true });
  } catch (err) {
    Logger.log('doPost error: ' + err + '\n' + (err && err.stack ? err.stack : ''));
    return out_({ ok: false, error: String(err) }, 500);
  }
}

/* ============================================================
 * 1) お問い合わせ / 発注 → スプレッドシート（1行追記・見出し自動作成）
 * ========================================================== */
function logToSheet_(body, d) {
  if (!CONFIG.SHEET_ID || String(CONFIG.SHEET_ID).indexOf('スプレッド') === 0) {
    Logger.log('logToSheet_ skipped: SHEET_ID 未設定');
    return;
  }

  const ss = SpreadsheetApp.openById(CONFIG.SHEET_ID);
  let sh = ss.getSheetByName(CONFIG.SHEET_NAME);
  if (!sh) { sh = ss.insertSheet(CONFIG.SHEET_NAME); }

  // シートが空のときだけ見出し行を作成（既存データには触れない）
  if (sh.getLastRow() === 0) {
    sh.appendRow(HEADERS);
    sh.getRange(1, 1, 1, HEADERS.length).setFontWeight('bold').setBackground('#eff6ff');
    sh.setFrozenRows(1);
  }

  // 見出し名 => 値（位置ではなく「列名」で配置するので列ズレが起きない）
  const record = {
    '受付日時'    : body.time || formatNow_(),
    '種別'        : kindOf_(body, d),
    'お名前'      : d.name || '',
    '会社名'      : d.company || '',
    'メール'      : d.email || '',
    '電話'        : d.phone || '',
    'サービス'    : d.service || '',
    'プラン'      : d.plan || '',
    '月額(円)'    : num_(d.monthly),
    '初期費用(円)': num_(d.initial),
    '年間目安(円)': num_(d.annual),
    '内容・ご要望': d.message || '',
    'ご希望日時'  : d.meeting_at || '',
    '流入元'      : d.source || body.site || '',
    '管理画面URL' : d.admin_url || '',
    'サイト'      : body.site || ''
  };

  // 実際の見出し行を読み、見出し名 → 列インデックス を作る
  const lastCol   = Math.max(sh.getLastColumn(), HEADERS.length);
  const headerRow = sh.getRange(1, 1, 1, lastCol).getValues()[0];
  const colOf = {};
  headerRow.forEach(function (h, i) {
    const name = String(h).trim();
    if (name) { colOf[name] = i; }
  });

  // 見出しに合わせて1行分の配列を組み立てる
  const row = new Array(lastCol).fill('');
  Object.keys(record).forEach(function (name) {
    let idx = (name in colOf) ? colOf[name] : HEADERS.indexOf(name);
    if (idx >= 0 && idx < row.length) { row[idx] = record[name]; }
  });

  sh.appendRow(row);
}

/* ============================================================
 * 2) 社内通知（Asana タスク + Slack）
 * ========================================================== */
function notifyTeam_(body, d) {
  const kind   = kindOf_(body, d);
  const amount = buildAmount_(d);

  // Asana タスク
  if (CONFIG.ASANA_TOKEN && CONFIG.ASANA_PROJECT) {
    const notes = `メール: ${d.email || ''}\n電話: ${d.phone || ''}\n`
                + (d.service ? `サービス: ${d.service} / ${d.plan || ''}\n` : '')
                + (amount ? `金額: ${amount}\n` : '')
                + (d.meeting_at ? `ご希望日時: ${d.meeting_at}\n` : '')
                + `内容: ${d.message || ''}\n管理: ${d.admin_url || ''}`;
    fetch_('https://app.asana.com/api/1.0/tasks', {
      method: 'post',
      headers: { Authorization: 'Bearer ' + CONFIG.ASANA_TOKEN },
      contentType: 'application/json',
      payload: JSON.stringify({ data: {
        name: `【${kind}】${d.name || ''}（${d.company || ''}）`,
        notes: notes, projects: [CONFIG.ASANA_PROJECT]
      }})
    }, 'Asana');
  }

  // Slack 通知
  postSlack_(`:bell: 新規【${kind}】\n`
    + `氏名: ${d.name || ''}（${d.company || ''}）\n`
    + `メール: ${d.email || ''} / 電話: ${d.phone || ''}\n`
    + (d.service ? `プラン: ${d.service} / ${d.plan || ''}\n` : '')
    + (amount ? `金額: ${amount}\n` : '')
    + (d.meeting_at ? `ご希望日時: ${d.meeting_at}\n` : '')
    + `内容: ${d.message || ''}\n▶ ${d.admin_url || ''}`);
}

/* ============================================================
 * 3) 記事公開 → Slack + SNS
 * ========================================================== */
function handlePost_(d) {
  const title = d.title || '';
  const url   = d.url || '';
  const text  = `${title}\n${url}`;

  // Slack（WP側でも投稿している場合は CONFIG.SLACK_POST_ENABLED=false で二重防止）
  if (CONFIG.SLACK_POST_ENABLED) {
    postSlack_(`:newspaper: 新着記事を公開しました\n*${title}*\n${url}`);
  }

  // LINE 公式（ブロードキャスト）
  if (CONFIG.LINE_TOKEN) {
    fetch_('https://api.line.me/v2/bot/message/broadcast', {
      method: 'post',
      headers: { Authorization: 'Bearer ' + CONFIG.LINE_TOKEN },
      contentType: 'application/json',
      payload: JSON.stringify({ messages: [{ type: 'text', text: `🆕 新着記事\n${text}` }] })
    }, 'LINE');
  }

  // Facebook ページ投稿
  if (CONFIG.FB_PAGE_ID && CONFIG.FB_PAGE_TOKEN) {
    fetch_(`https://graph.facebook.com/${CONFIG.FB_PAGE_ID}/feed`, {
      method: 'post',
      payload: { message: `🆕 ${title}`, link: url, access_token: CONFIG.FB_PAGE_TOKEN }
    }, 'Facebook');
  }

  // X / Instagram は Zapier 経由（API認証が複雑なため）
  if (CONFIG.ZAPIER_HOOK) {
    fetch_(CONFIG.ZAPIER_HOOK, {
      method: 'post',
      contentType: 'application/json',
      payload: JSON.stringify({ title: title, url: url, image: d.image || '', excerpt: d.excerpt || '' })
    }, 'Zapier');
  }
}

/* ============================================================
 * 共通ヘルパー
 * ========================================================== */

/** 種別ラベルを決定（order は type_label が無いので補完）。 */
function kindOf_(body, d) {
  return d.type_label || (body.event === 'order' ? '見積もり・発注' : (d.type || 'お問い合わせ'));
}

/** 文字列・記号混じりでも数値だけ取り出す（空なら空欄のまま）。 */
function num_(v) {
  if (v === undefined || v === null || v === '') { return ''; }
  const n = Number(String(v).replace(/[^0-9.-]/g, ''));
  return isNaN(n) ? '' : n;
}

/** 月額・初期費用の表示文字列を組み立てる。 */
function buildAmount_(d) {
  const parts = [];
  const m = num_(d.monthly);
  const i = num_(d.initial);
  if (m !== '') { parts.push('月額' + Number(m).toLocaleString() + '円'); }
  if (i !== '') { parts.push('初期' + Number(i).toLocaleString() + '円'); }
  return parts.join(' / ');
}

/** Slack 送信（未設定ならスキップ）。 */
function postSlack_(text) {
  if (!CONFIG.SLACK_WEBHOOK) { return; }
  fetch_(CONFIG.SLACK_WEBHOOK, {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify({ text: text })
  }, 'Slack');
}

/**
 * UrlFetch ラッパー。失敗（非2xx・例外）を Logger に記録し、握りつぶさず見えるようにする。
 * @return {number} HTTPステータス（例外時は 0）。
 */
function fetch_(url, options, label) {
  options = options || {};
  options.muteHttpExceptions = true;
  try {
    const res  = UrlFetchApp.fetch(url, options);
    const code = res.getResponseCode();
    if (code < 200 || code >= 300) {
      Logger.log(`[${label || 'fetch'}] HTTP ${code}: ${res.getContentText().slice(0, 500)}`);
    }
    return code;
  } catch (err) {
    Logger.log(`[${label || 'fetch'}] 例外: ${err}`);
    return 0;
  }
}

/** スクリプトのタイムゾーンで現在時刻を整形（body.time が無い場合の保険）。 */
function formatNow_() {
  return Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd HH:mm:ss');
}

/** タイミング攻撃を避けた文字列一致（長さも比較）。 */
function safeEqual_(a, b) {
  a = String(a == null ? '' : a);
  b = String(b == null ? '' : b);
  if (a.length !== b.length) { return false; }
  let diff = 0;
  for (let i = 0; i < a.length; i++) {
    diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }
  return diff === 0;
}

/** JSON レスポンス（GAS の Web アプリは実際のHTTPコードは固定だが、ボディで状態を返す）。 */
function out_(obj, status) {
  if (status) { obj.status = status; }
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

/* ============================================================
 * セットアップ確認（エディタから手動実行）
 * ========================================================== */

/**
 * デプロイ前の事前チェック。エディタで実行し、ログ（表示 > ログ）で結果を確認。
 *  - 共有トークンが設定済みか
 *  - スプレッドシートに接続でき、見出しを作れるか
 *  - Slack に1通テスト送信できるか（設定時のみ）
 */
function testConfig() {
  const report = [];

  report.push(
    (CONFIG.SHARED_TOKEN && String(CONFIG.SHARED_TOKEN).indexOf('ここに') !== 0)
      ? '✅ SHARED_TOKEN: 設定済み'
      : '❌ SHARED_TOKEN: 未設定（WPと同じ合言葉を入れてください）'
  );

  try {
    if (!CONFIG.SHEET_ID || String(CONFIG.SHEET_ID).indexOf('スプレッド') === 0) {
      report.push('❌ SHEET_ID: 未設定');
    } else {
      const ss = SpreadsheetApp.openById(CONFIG.SHEET_ID);
      let sh = ss.getSheetByName(CONFIG.SHEET_NAME) || ss.insertSheet(CONFIG.SHEET_NAME);
      if (sh.getLastRow() === 0) {
        sh.appendRow(HEADERS);
        sh.getRange(1, 1, 1, HEADERS.length).setFontWeight('bold').setBackground('#eff6ff');
        sh.setFrozenRows(1);
      }
      report.push(`✅ スプレッドシート接続OK（タブ「${CONFIG.SHEET_NAME}」）`);
    }
  } catch (err) {
    report.push('❌ スプレッドシート接続NG: ' + err);
  }

  if (CONFIG.SLACK_WEBHOOK) {
    const code = fetch_(CONFIG.SLACK_WEBHOOK, {
      method: 'post', contentType: 'application/json',
      payload: JSON.stringify({ text: ':white_check_mark: APPREX GAS 設定テスト（testConfig）' })
    }, 'Slack');
    report.push((code >= 200 && code < 300) ? '✅ Slack 送信OK' : '❌ Slack 送信NG（HTTP ' + code + '）');
  } else {
    report.push('— Slack: 未設定（任意）');
  }

  if (CONFIG.ASANA_TOKEN && CONFIG.ASANA_PROJECT) {
    report.push('— Asana: トークン/プロジェクト設定あり（実タスクは送信時に作成）');
  } else {
    report.push('— Asana: 未設定（任意）');
  }

  Logger.log(report.join('\n'));
  return report.join('\n');
}
