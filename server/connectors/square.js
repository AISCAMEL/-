/* =========================================================================
 * connectors/square.js ― Square → 会計アプリ 取込コネクタ（依存ゼロ）
 *
 * Square の決済（Payments API）を取得し、売上と決済手数料の2件の仕訳データを
 * 取込Webhook（/api/inbox）へ投入する。
 *
 * 実行:
 *   SQUARE_ACCESS_TOKEN=xxx SYNC_URL=http://localhost:8787 \
 *   WORKSPACE=aizu-2026 TOKEN=合言葉 node server/connectors/square.js
 * ========================================================================= */
'use strict';
const path = require('path');
const L = require('./_lib');

const CURSOR = path.join(__dirname, '.square_cursor');

async function main() {
  const cfg = L.cfgBase();
  const token = process.env.SQUARE_ACCESS_TOKEN || '';
  const base = process.env.SQUARE_API_BASE || 'https://connect.squareup.com';
  const version = process.env.SQUARE_VERSION || '2024-01-18';
  if (!token) throw new Error('SQUARE_ACCESS_TOKEN が未設定です');

  const cursor = L.readCursor(CURSOR); // 最後に取得した created_at（ISO）
  const q = new URLSearchParams({ sort_order: 'ASC' });
  if (cursor) q.set('begin_time', cursor);
  const res = await L.getJson(`${base}/v2/payments?${q}`, { Authorization: `Bearer ${token}`, 'Square-Version': version });
  // begin_time は含む場合があるため、カーソルと同時刻は除外
  const payments = (res.payments || []).filter((p) => p.status !== 'FAILED' && p.status !== 'CANCELED' && (!cursor || p.created_at > cursor));

  const items = []; let maxCreated = cursor || '';
  for (const p of payments) {
    const cur = (p.amount_money && p.amount_money.currency) || 'JPY';
    const gross = L.minorToYen((p.amount_money && p.amount_money.amount) || 0, cur);
    const fee = (p.processing_fee || []).reduce((s, f) => s + L.minorToYen((f.amount_money && f.amount_money.amount) || 0, cur), 0);
    const desc = p.note || p.id;
    items.push(...L.salesAndFee(cfg, L.ymdIso(p.created_at), desc, gross, fee, 'Square'));
    if (!maxCreated || p.created_at > maxCreated) maxCreated = p.created_at;
  }
  if (!items.length) { console.log('新規の取引はありません。'); return; }
  const r = await L.postInbox(cfg, items);
  if (maxCreated) L.writeCursor(CURSOR, maxCreated);
  console.log(`Square ${payments.length}件 → ${items.length}件を投入（受信キュー計 ${r.total} 件）。`);
}
main().catch((e) => { console.error('エラー:', e.message); process.exit(1); });
