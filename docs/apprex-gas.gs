/**
 * APPREX 連携 GAS（1本で完結）
 * ------------------------------------------------------------------
 * WordPress（テーマ apprex）から届く Webhook を種類別に処理します。
 *
 *  event = "inquiry" / "order"      → スプレッド記録 + Asana タスク + Slack 通知
 *  event = "post_published"         → Slack 通知 + SNS 投稿（LINE / Facebook / Zapier 経由でX・IG）
 *
 * 使い方：
 *  1) スプレッドシートを作成し、タブ名を「受付」にする
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
        handleInquiry_(body, d);
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
 * 1) お問い合わせ / 発注 → スプレッド + Asana + Slack
 * ========================================================== */
function handleInquiry_(body, d) {
  const amount = d.billing === 'monthly' ? ('月額' + (d.monthly || 0))
               : (d.monthly ? ('月額' + d.monthly) : (d.oneoff ? (d.oneoff + '円') : ''));

  // A. スプレッドシート（1行追記）
  if (CONFIG.SHEET_ID) {
    const sh = SpreadsheetApp.openById(CONFIG.SHEET_ID).getSheetByName(CONFIG.SHEET_NAME);
    sh.appendRow([
      body.time, d.type_label || d.type, d.name, d.company, d.email,
      d.phone, d.message, d.meeting_at || '', amount, d.admin_url
    ]);
  }

  // B. Asana タスク
  if (CONFIG.ASANA_TOKEN && CONFIG.ASANA_PROJECT) {
    const notes = `メール: ${d.email}\n電話: ${d.phone || ''}\n内容: ${d.message || ''}\n`
                + (amount ? `金額: ${amount}\n` : '') + `管理: ${d.admin_url || ''}`;
    UrlFetchApp.fetch('https://app.asana.com/api/1.0/tasks', {
      method: 'post', muteHttpExceptions: true,
      headers: { Authorization: 'Bearer ' + CONFIG.ASANA_TOKEN },
      contentType: 'application/json',
      payload: JSON.stringify({ data: {
        name: `【${d.type_label || d.type}】${d.name}（${d.company || ''}）`,
        notes: notes, projects: [CONFIG.ASANA_PROJECT]
      }})
    });
  }

  // C. Slack 通知
  postSlack_(`:bell: 新規【${d.type_label || d.type}】\n`
    + `氏名: ${d.name}（${d.company || ''}）\n`
    + `メール: ${d.email} / 電話: ${d.phone || ''}\n`
    + (amount ? `金額: ${amount}\n` : '')
    + `内容: ${d.message || ''}\n▶ ${d.admin_url || ''}`);
}

/* ============================================================
 * 2) 記事公開 → Slack + SNS
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
