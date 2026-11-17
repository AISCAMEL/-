/* =========================================================================
 * connectors/paypal.js ― PayPal → 会計アプリ 取込コネクタ（依存ゼロ）
 *
 * PayPal の取引（Transaction Search API）を取得し、売上と決済手数料の2件の
 * 仕訳データを取込Webhook（/api/inbox）へ投入する。
 *
 * 実行:
 *   PAYPAL_CLIENT_ID=xxx PAYPAL_SECRET=yyy SYNC_URL=http://localhost:8787 \
 *   WORKSPACE=aizu-2026 TOKEN=合言葉 node server/connectors/paypal.js
 *
 * ※ 金額はJPY前提（主要単位＝円）。start_date は過去約3年以内が有効。
 * ========================================================================= */
'use strict';
const path = require('path');
const L = require('./_lib');

const CURSOR = path.join(__dirname, '.paypal_cursor');

async function main() {
  const cfg = L.cfgBase();
  const clientId = process.env.PAYPAL_CLIENT_ID || '';
  const secret = process.env.PAYPAL_SECRET || '';
  const base = process.env.PAYPAL_API_BASE || 'https://api-m.paypal.com';
  if (!clientId || !secret) throw new Error('PAYPAL_CLIENT_ID / PAYPAL_SECRET が未設定です');

  // OAuth2（client_credentials）
  const basic = Buffer.from(`${clientId}:${secret}`).toString('base64');
  const tok = await L.postForm(`${base}/v1/oauth2/token`, { grant_type: 'client_credentials' }, { Authorization: `Basic ${basic}` });
  const access = tok.access_token;
  if (!access) throw new Error('アクセストークンを取得できませんでした');

  const cursor = L.readCursor(CURSOR); // 最後に取得した initiation date（ISO）
  const now = new Date();
  const start = cursor ? new Date(cursor) : new Date(now.getTime() - 31 * 86400000);
  const res = await L.getJson(
    `${base}/v1/reporting/transactions?start_date=${encodeURIComponent(start.toISOString())}&end_date=${encodeURIComponent(now.toISOString())}&fields=all&page_size=100`,
    { Authorization: `Bearer ${access}` });
  const details = (res.transaction_details || []).map((d) => d.transaction_info || {})
    .filter((t) => t.transaction_initiation_date && (!cursor || t.transaction_initiation_date > cursor));

  const items = []; let maxDate = cursor || '';
  for (const t of details) {
    const gross = L.majorToYen((t.transaction_amount && t.transaction_amount.value) || 0);
    const fee = Math.abs(L.majorToYen((t.fee_amount && t.fee_amount.value) || 0));
    const desc = t.transaction_id || '';
    const date = L.ymdIso(t.transaction_initiation_date);
    if (gross > 0) { // 入金（売上）のみ対象。返金・出金は符号で除外
      items.push(...L.salesAndFee(cfg, date, desc, gross, fee, 'PayPal'));
    }
    if (!maxDate || t.transaction_initiation_date > maxDate) maxDate = t.transaction_initiation_date;
  }
  if (!items.length) { console.log('新規の取引はありません。'); return; }
  const r = await L.postInbox(cfg, items);
  if (maxDate) L.writeCursor(CURSOR, maxDate);
  console.log(`PayPal ${details.length}件 → ${items.length}件を投入（受信キュー計 ${r.total} 件）。`);
}
main().catch((e) => { console.error('エラー:', e.message); process.exit(1); });
