/* =========================================================================
 * connectors/gmail.js ― Gmail → 会計アプリ 取込コネクタ（依存ゼロ）
 *
 * 指定した送信元ルールに一致するメールを Gmail から取得し、会計アプリの
 * 取込Webhook（/api/inbox）へ「下書き仕訳データ」を投入する。
 * 金額はメール本文に無い場合（USS精算書など添付PDF）は0で下書きし、
 * アプリの取込画面で確認・入力する運用（承認制）。
 *
 * 認証：Google OAuth2（リフレッシュトークン）。
 * 実行:
 *   GMAIL_CLIENT_ID=... GMAIL_CLIENT_SECRET=... GMAIL_REFRESH_TOKEN=... \
 *   SYNC_URL=http://localhost:8787 WORKSPACE=aizu TOKEN=合言葉 \
 *   node server/connectors/gmail.js
 * ========================================================================= */
'use strict';
const path = require('path');
const L = require('./_lib');

const CURSOR = path.join(__dirname, '.gmail_cursor');
const GBASE = process.env.GMAIL_API_BASE || 'https://gmail.googleapis.com';
const OAUTH = process.env.GOOGLE_OAUTH_BASE || 'https://oauth2.googleapis.com';

// 取込ルール：from に一致したら会計対象。JSON(GMAIL_RULES)で上書き可。
const DEFAULT_RULES = [
  { from: 'ussnet.co.jp', account: '500', tax: 'purchase10', dir: 'out', label: 'USSオークション精算' },
];
const rules = (() => { try { return JSON.parse(process.env.GMAIL_RULES) || DEFAULT_RULES; } catch (e) { return DEFAULT_RULES; } });

const header = (msg, name) => {
  const h = ((msg.payload && msg.payload.headers) || []).find((x) => x.name.toLowerCase() === name.toLowerCase());
  return h ? h.value : '';
};
// 本文・件名から金額（¥/円）を推定（無ければ0）。
// 年月日（2026 や 09/24）の誤検出を避けるため、通貨記号(¥/￥/円)か
// カンマ区切り(1,250,000)を伴う数値だけを候補にし、最大値を採用する。
const guessAmount = (text) => {
  const s = String(text || '');
  const cands = [];
  let m;
  const reMarked = /(?:[¥￥]\s?([\d,]{2,})|([\d,]{2,})\s?円)/g; // ¥付き or 円付き
  while ((m = reMarked.exec(s))) { const n = Number((m[1] || m[2]).replace(/,/g, '')); if (n) cands.push(n); }
  const reGrouped = /\b(\d{1,3}(?:,\d{3})+)\b/g; // 1,250,000 のようなカンマ区切り
  while ((m = reGrouped.exec(s))) { const n = Number(m[1].replace(/,/g, '')); if (n) cands.push(n); }
  return cands.length ? Math.max(...cands) : 0;
};

async function main() {
  const cfg = L.cfgBase();
  const cid = process.env.GMAIL_CLIENT_ID, csec = process.env.GMAIL_CLIENT_SECRET, rtok = process.env.GMAIL_REFRESH_TOKEN;
  if (!cid || !csec || !rtok) throw new Error('GMAIL_CLIENT_ID / GMAIL_CLIENT_SECRET / GMAIL_REFRESH_TOKEN が未設定です');
  const R = rules();

  // アクセストークン取得
  const tok = await L.postForm(`${OAUTH}/token`, { client_id: cid, client_secret: csec, refresh_token: rtok, grant_type: 'refresh_token' });
  const access = tok.access_token;
  if (!access) throw new Error('アクセストークンを取得できませんでした');
  const auth = { Authorization: `Bearer ${access}` };

  const cursor = Number(L.readCursor(CURSOR)) || 0; // 最後に取得した internalDate(ms)
  // 検索クエリ：ルールの from を OR で結合＋前回以降
  const fromQ = R.map((r) => `from:${r.from}`).join(' OR ');
  const afterSec = cursor ? Math.floor(cursor / 1000) : Math.floor((Date.now() - 30 * 86400000) / 1000);
  const q = encodeURIComponent(`(${fromQ}) after:${afterSec}`);
  const list = await L.getJson(`${GBASE}/gmail/v1/users/me/messages?q=${q}&maxResults=50`, auth);
  const ids = (list.messages || []).map((m) => m.id);

  const items = []; let maxInternal = cursor;
  for (const id of ids) {
    const msg = await L.getJson(`${GBASE}/gmail/v1/users/me/messages/${id}?format=metadata&metadataHeaders=From&metadataHeaders=Subject&metadataHeaders=Date`, auth);
    const internal = Number(msg.internalDate) || 0;
    if (internal <= cursor) continue;
    if (internal > maxInternal) maxInternal = internal;
    const from = header(msg, 'From');
    const subject = header(msg, 'Subject');
    const rule = R.find((r) => from.includes(r.from));
    if (!rule) continue;
    const date = L.ymdUnix(Math.floor(internal / 1000));
    const amount = guessAmount(subject + ' ' + (msg.snippet || ''));
    items.push({
      date, description: `[メール取込/${rule.label}] ${subject}`.slice(0, 160),
      amount, dir: rule.dir, account: rule.account, tax: rule.tax,
    });
  }
  if (!items.length) { console.log('新規の対象メールはありません。'); return; }
  const res = await L.postInbox(cfg, items);
  L.writeCursor(CURSOR, maxInternal);
  console.log(`Gmail 対象メール ${items.length}件 → 下書きを投入（受信キュー計 ${res.total} 件）。`);
  console.log('金額が0の下書きは、添付（精算書PDF等）を確認して取込画面で入力してください。');
}
main().catch((e) => { console.error('エラー:', e.message); process.exit(1); });
