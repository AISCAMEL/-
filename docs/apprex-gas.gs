/**
 * APPREX 連携 GAS（1本で完結）
 * ------------------------------------------------------------------
 * WordPress（テーマ apprex）から届く Webhook を種類別に処理します。
 *
 *  event = "inquiry" / "order"      → スプレッドシート記録 + Asana タスク + Slack 通知
 *  event = "post_published"         → Slack 通知 + SNS 投稿（LINE / Facebook / Zapier 経由でX・IG）
 *
 * 使い方：
 *  1) スプレッドシートを作成し、その ID を SHEET_ID に貼る（URL の /d/●●●/edit の●●●）
 *     ※ 見出し行はスクリプトが自動作成します（タブが無ければ自動生成）
 *  2) 拡張機能 > Apps Script に本コードを貼り付け、下の CONFIG を設定
 *  3) デプロイ > 新しいデプロイ > 種類「ウェブアプリ」/ アクセス「全員」
 *  4) 発行された …/exec URL を WordPress（設定 > APPREX 連携 > GAS Webhook URL）に貼る
 *  5) SHARED_TOKEN を WP の「GAS 共有トークン」と同じ値にする
 * ------------------------------------------------------------------
 */

const CONFIG = {
  SHARED_TOKEN : 'ここにWPと同じ合言葉',                 // 必須（なりすまし防止）

  // --- お問い合わせ/発注 用 ---
  SHEET_ID     : 'スプレッドシートのID',                  // 受付台帳
  SHEET_NAME   : '受付',
  SLACK_WEBHOOK: '',                                      // Slack Incoming Webhook（任意）
  ASANA_TOKEN  : '',                                      // Asana 個人アクセストークン（任意）
  ASANA_PROJECT: '',                                      // Asana プロジェクトGID（任意）

  // --- 記事公開→SNS 用 ---
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

/** エントリポイント */
function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);
    if (body.token !== CONFIG.SHARED_TOKEN) {
      return out_('forbidden');
    }
    const d = body.data || {};

    switch (body.event) {
      case 'inquiry':
      case 'order':
        logToSheet_(body, d);   // ← どの種別でも必ずスプレッドシートに1行記録
        notifyTeam_(body, d);   //   Asana タスク + Slack 通知
        break;
      case 'post_published':
        handlePost_(d);
        break;
    }
    return out_('ok');
  } catch (err) {
    return out_('error: ' + err);
  }
}

/* ============================================================
 * 1) お問い合わせ / 発注 → スプレッドシート（1行追記・見出し自動作成）
 * ========================================================== */
function logToSheet_(body, d) {
  if (!CONFIG.SHEET_ID) { return; }

  const ss = SpreadsheetApp.openById(CONFIG.SHEET_ID);
  let sh = ss.getSheetByName(CONFIG.SHEET_NAME);
  if (!sh) { sh = ss.insertSheet(CONFIG.SHEET_NAME); }

  // シートが空のときだけ見出し行を作成（既存データには触れない）
  if (sh.getLastRow() === 0) {
    sh.appendRow(HEADERS);
    sh.getRange(1, 1, 1, HEADERS.length).setFontWeight('bold').setBackground('#eff6ff');
    sh.setFrozenRows(1);
  }

  const kind = d.type_label || (body.event === 'order' ? '見積もり・発注' : (d.type || 'お問い合わせ'));

  sh.appendRow([
    body.time || new Date(),       // 受付日時
    kind,                          // 種別（資料請求/無料体験/ミーティング/パートナー/見積もり・発注 …）
    d.name || '',                  // お名前
    d.company || '',               // 会社名
    d.email || '',                 // メール
    d.phone || '',                 // 電話
    d.service || '',               // サービス（発注時のみ）
    d.plan || '',                  // プラン（発注時のみ）
    num_(d.monthly),               // 月額
    num_(d.initial),               // 初期費用
    num_(d.annual),                // 年間目安
    d.message || '',               // 内容・ご要望
    d.meeting_at || '',            // ご希望日時（ミーティング予約）
    d.source || body.site || '',   // 流入元
    d.admin_url || '',             // 管理画面URL
    body.site || ''                // サイト
  ]);
}

/** 文字列・記号混じりでも数値だけ取り出す（空なら空欄のまま）。 */
function num_(v) {
  if (v === undefined || v === null || v === '') { return ''; }
  const n = Number(String(v).replace(/[^0-9.-]/g, ''));
  return isNaN(n) ? '' : n;
}

/* ============================================================
 * 2) 社内通知（Asana タスク + Slack）
 * ========================================================== */
function notifyTeam_(body, d) {
  const kind = d.type_label || (body.event === 'order' ? '見積もり・発注' : (d.type || 'お問い合わせ'));
  const amount = buildAmount_(d);

  // Asana タスク
  if (CONFIG.ASANA_TOKEN && CONFIG.ASANA_PROJECT) {
    const notes = `メール: ${d.email || ''}\n電話: ${d.phone || ''}\n`
                + (d.service ? `サービス: ${d.service} / ${d.plan || ''}\n` : '')
                + (amount ? `金額: ${amount}\n` : '')
                + (d.meeting_at ? `ご希望日時: ${d.meeting_at}\n` : '')
                + `内容: ${d.message || ''}\n管理: ${d.admin_url || ''}`;
    UrlFetchApp.fetch('https://app.asana.com/api/1.0/tasks', {
      method: 'post', muteHttpExceptions: true,
      headers: { Authorization: 'Bearer ' + CONFIG.ASANA_TOKEN },
      contentType: 'application/json',
      payload: JSON.stringify({ data: {
        name: `【${kind}】${d.name || ''}（${d.company || ''}）`,
        notes: notes, projects: [CONFIG.ASANA_PROJECT]
      }})
    });
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

/** 月額・初期費用の表示文字列を組み立てる。 */
function buildAmount_(d) {
  const parts = [];
  if (d.monthly) { parts.push('月額' + Number(d.monthly).toLocaleString() + '円'); }
  if (d.initial) { parts.push('初期' + Number(d.initial).toLocaleString() + '円'); }
  return parts.join(' / ');
}

/* ============================================================
 * 3) 記事公開 → Slack + SNS
 * ========================================================== */
function handlePost_(d) {
  const title = d.title || '';
  const url   = d.url || '';
  const text  = `${title}\n${url}`;

  // Slack
  postSlack_(`:newspaper: 新着記事を公開しました\n*${title}*\n${url}`);

  // LINE 公式（ブロードキャスト）
  if (CONFIG.LINE_TOKEN) {
    UrlFetchApp.fetch('https://api.line.me/v2/bot/message/broadcast', {
      method: 'post', muteHttpExceptions: true,
      headers: { Authorization: 'Bearer ' + CONFIG.LINE_TOKEN },
      contentType: 'application/json',
      payload: JSON.stringify({ messages: [{ type: 'text', text: `🆕 新着記事\n${text}` }] })
    });
  }

  // Facebook ページ投稿
  if (CONFIG.FB_PAGE_ID && CONFIG.FB_PAGE_TOKEN) {
    UrlFetchApp.fetch(`https://graph.facebook.com/${CONFIG.FB_PAGE_ID}/feed`, {
      method: 'post', muteHttpExceptions: true,
      payload: { message: `🆕 ${title}`, link: url, access_token: CONFIG.FB_PAGE_TOKEN }
    });
  }

  // X / Instagram は Zapier 経由（API認証が複雑なため）
  if (CONFIG.ZAPIER_HOOK) {
    UrlFetchApp.fetch(CONFIG.ZAPIER_HOOK, {
      method: 'post', muteHttpExceptions: true,
      contentType: 'application/json',
      payload: JSON.stringify({ title: title, url: url, image: d.image || '', excerpt: d.excerpt || '' })
    });
  }
}

/* ============================================================
 * 共通ヘルパー
 * ========================================================== */
function postSlack_(text) {
  if (!CONFIG.SLACK_WEBHOOK) return;
  UrlFetchApp.fetch(CONFIG.SLACK_WEBHOOK, {
    method: 'post', muteHttpExceptions: true,
    contentType: 'application/json',
    payload: JSON.stringify({ text: text })
  });
}

function out_(s) {
  return ContentService.createTextOutput(s);
}
