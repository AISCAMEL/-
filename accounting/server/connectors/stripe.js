/* =========================================================================
 * connectors/stripe.js ― Stripe → 会計アプリ 取込コネクタ（依存ゼロ）
 *
 * Stripe の売上（balance transactions の charge）を取得し、
 * 会計アプリの取込Webhook（/api/inbox）へ「売上」と「決済手数料」の
 * 2件の仕訳データを投入する。API キーはこのサーバー側だけで保持する。
 *
 * 実行:
 *   STRIPE_API_KEY=sk_live_xxx \
 *   SYNC_URL=http://localhost:8787 WORKSPACE=aizu-2026 TOKEN=合言葉 \
 *   node server/connectors/stripe.js
 *
 * 定期実行（cron 例：15分ごと）:
 *   *\/15 * * * *  cd /path/to/app && STRIPE_API_KEY=... SYNC_URL=... WORKSPACE=... TOKEN=... node server/connectors/stripe.js
 *
 * 重複防止：前回取得した最大 created を .stripe_cursor に保存し、次回はそれ以降のみ取得。
 * ========================================================================= */
'use strict';
const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');
const { URL } = require('url');

const CFG = {
  stripeKey: process.env.STRIPE_API_KEY || '',
  stripeBase: process.env.STRIPE_API_BASE || 'https://api.stripe.com',
  syncUrl: (process.env.SYNC_URL || 'http://localhost:8787').replace(/\/$/, ''),
  workspace: process.env.WORKSPACE || '',
  token: process.env.TOKEN || '',
  // 勘定科目コード（会計アプリ側のマスタに合わせる）
  salesAccount: process.env.SALES_ACCOUNT || '400',   // 売上高
  feeAccount: process.env.FEE_ACCOUNT || '580',       // 支払手数料
  salesTax: process.env.SALES_TAX || 'sales10',
  feeTax: process.env.FEE_TAX || 'out',
  limit: Number(process.env.LIMIT || 100),
};
const CURSOR_FILE = path.join(__dirname, '.stripe_cursor');

const getJson = (urlStr, headers) => new Promise((resolve, reject) => {
  const u = new URL(urlStr);
  const lib = u.protocol === 'https:' ? https : http;
  const req = lib.request(u, { method: 'GET', headers }, (res) => {
    let d = ''; res.on('data', (c) => (d += c));
    res.on('end', () => {
      let body = {}; try { body = JSON.parse(d); } catch (e) {}
      if (res.statusCode >= 200 && res.statusCode < 300) resolve(body);
      else reject(new Error(`Stripe ${res.statusCode}: ${(body.error && body.error.message) || d.slice(0, 200)}`));
    });
  });
  req.on('error', reject); req.end();
});

const postJson = (urlStr, obj) => new Promise((resolve, reject) => {
  const u = new URL(urlStr);
  const lib = u.protocol === 'https:' ? https : http;
  const payload = JSON.stringify(obj);
  const req = lib.request(u, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) } }, (res) => {
    let d = ''; res.on('data', (c) => (d += c));
    res.on('end', () => { let body = {}; try { body = JSON.parse(d); } catch (e) {} (res.statusCode >= 200 && res.statusCode < 300) ? resolve(body) : reject(new Error(`inbox ${res.statusCode}: ${d.slice(0, 200)}`)); });
  });
  req.on('error', reject); req.end(payload);
});

const ymd = (unixSec) => {
  const dt = new Date(unixSec * 1000);
  const p = (x) => String(x).padStart(2, '0');
  return `${dt.getFullYear()}-${p(dt.getMonth() + 1)}-${p(dt.getDate())}`;
};
// 日本円などゼロデシマル通貨はそのまま円。それ以外は最小単位/100。
const ZERO_DECIMAL = new Set(['jpy', 'krw', 'clp', 'vnd']);
const toYen = (amount, currency) => ZERO_DECIMAL.has((currency || 'jpy').toLowerCase()) ? amount : Math.round(amount / 100);

async function main() {
  if (!CFG.stripeKey) throw new Error('STRIPE_API_KEY が未設定です');
  if (!CFG.workspace) throw new Error('WORKSPACE が未設定です');

  let cursor = 0;
  try { cursor = Number(fs.readFileSync(CURSOR_FILE, 'utf8').trim()) || 0; } catch (e) {}

  // charge タイプの balance transaction を取得（created 昇順の重複防止のため created[gt]=cursor）
  const q = new URLSearchParams({ limit: String(CFG.limit), type: 'charge' });
  if (cursor) q.set('created[gt]', String(cursor));
  const url = `${CFG.stripeBase}/v1/balance_transactions?${q.toString()}`;
  const res = await getJson(url, { Authorization: `Bearer ${CFG.stripeKey}` });
  const txns = (res.data || []).filter((t) => t.type === 'charge');

  const items = [];
  let maxCreated = cursor;
  for (const t of txns) {
    const gross = toYen(t.amount, t.currency);
    const fee = toYen(t.fee, t.currency);
    const desc = t.description || t.source || t.id;
    const date = ymd(t.created);
    if (gross > 0) items.push({ date, description: `Stripe売上 ${desc}`, amount: gross, dir: 'in', account: CFG.salesAccount, tax: CFG.salesTax });
    if (fee > 0) items.push({ date, description: `Stripe決済手数料 ${desc}`, amount: fee, dir: 'out', account: CFG.feeAccount, tax: CFG.feeTax });
    if (t.created > maxCreated) maxCreated = t.created;
  }

  if (!items.length) { console.log('新規の取引はありません。'); return; }

  const r = await postJson(`${CFG.syncUrl}/api/inbox`, { workspace: CFG.workspace, token: CFG.token, items });
  fs.writeFileSync(CURSOR_FILE, String(maxCreated));
  console.log(`Stripeの取引 ${txns.length} 件から ${items.length} 件の仕訳データを投入しました（受信キュー計 ${r.total} 件）。`);
  console.log('会計アプリの「明細の取込」→「受信データを取得」で取り込めます。');
}

main().catch((e) => { console.error('エラー:', e.message); process.exit(1); });
