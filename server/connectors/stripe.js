/* =========================================================================
 * connectors/stripe.js ― Stripe → 会計アプリ 取込コネクタ（依存ゼロ）
 *
 * Stripe の売上（balance transactions の charge）を取得し、売上と決済手数料の
 * 2件の仕訳データを取込Webhook（/api/inbox）へ投入する。
 *
 * 実行:
 *   STRIPE_API_KEY=sk_live_xxx SYNC_URL=http://localhost:8787 \
 *   WORKSPACE=aizu-2026 TOKEN=合言葉 node server/connectors/stripe.js
 * ========================================================================= */
'use strict';
const path = require('path');
const L = require('./_lib');

const CURSOR = path.join(__dirname, '.stripe_cursor');

async function main() {
  const cfg = L.cfgBase();
  const key = process.env.STRIPE_API_KEY || '';
  const base = process.env.STRIPE_API_BASE || 'https://api.stripe.com';
  const limit = Number(process.env.LIMIT || 100);
  if (!key) throw new Error('STRIPE_API_KEY が未設定です');

  const cursor = Number(L.readCursor(CURSOR)) || 0;
  const q = new URLSearchParams({ limit: String(limit), type: 'charge' });
  if (cursor) q.set('created[gt]', String(cursor));
  const res = await L.getJson(`${base}/v1/balance_transactions?${q}`, { Authorization: `Bearer ${key}` });
  const txns = (res.data || []).filter((t) => t.type === 'charge');

  const items = []; let maxCreated = cursor;
  for (const t of txns) {
    const gross = L.minorToYen(t.amount, t.currency);
    const fee = L.minorToYen(t.fee, t.currency);
    const desc = t.description || t.source || t.id;
    items.push(...L.salesAndFee(cfg, L.ymdUnix(t.created), desc, gross, fee, 'Stripe'));
    if (t.created > maxCreated) maxCreated = t.created;
  }
  if (!items.length) { console.log('新規の取引はありません。'); return; }
  const r = await L.postInbox(cfg, items);
  L.writeCursor(CURSOR, maxCreated);
  console.log(`Stripe ${txns.length}件 → ${items.length}件を投入（受信キュー計 ${r.total} 件）。`);
}
main().catch((e) => { console.error('エラー:', e.message); process.exit(1); });
