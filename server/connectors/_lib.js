/* =========================================================================
 * connectors/_lib.js ― コネクタ共通ユーティリティ（依存ゼロ）
 * HTTP、金額換算、カーソル（重複防止）、取込Webhook投入をまとめる。
 * ========================================================================= */
'use strict';
const https = require('https');
const http = require('http');
const fs = require('fs');
const { URL } = require('url');

const request = (method, urlStr, headers, payload) => new Promise((resolve, reject) => {
  const u = new URL(urlStr);
  const lib = u.protocol === 'https:' ? https : http;
  const req = lib.request(u, { method, headers: headers || {} }, (res) => {
    let d = ''; res.on('data', (c) => (d += c));
    res.on('end', () => {
      let b = {}; try { b = JSON.parse(d); } catch (e) {}
      if (res.statusCode >= 200 && res.statusCode < 300) resolve(b);
      else reject(new Error(`${res.statusCode}: ${(b.error_description || b.message || (b.error && b.error.message) || (b.errors && JSON.stringify(b.errors)) || d.slice(0, 200))}`));
    });
  });
  req.on('error', reject);
  if (payload) req.write(payload);
  req.end();
});
const getJson = (url, headers) => request('GET', url, headers);
const postJson = (url, obj, headers) => request('POST', url, Object.assign({ 'Content-Type': 'application/json' }, headers || {}), JSON.stringify(obj));
const postForm = (url, form, headers) => request('POST', url, Object.assign({ 'Content-Type': 'application/x-www-form-urlencoded' }, headers || {}), new URLSearchParams(form).toString());

const pad = (x) => String(x).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const ymdUnix = (sec) => ymd(new Date(sec * 1000));
const ymdIso = (iso) => ymd(new Date(iso));

// 通貨換算：Stripe/Square は最小単位、PayPal 等は主要単位の文字列
const ZERO_DECIMAL = new Set(['jpy', 'krw', 'clp', 'vnd', 'bif', 'djf', 'gnf', 'kmf', 'mga', 'pyg', 'rwf', 'ugx', 'vuv', 'xaf', 'xof', 'xpf']);
const minorToYen = (amount, cur) => (ZERO_DECIMAL.has((cur || 'jpy').toLowerCase()) ? Math.round(amount) : Math.round(amount / 100));
const majorToYen = (value) => Math.round(parseFloat(value) || 0); // 主要単位（JPYはそのまま円）

// テキストから金額（¥/円/カンマ区切り）を推定。年号・日付の誤検出を避けるため、
// 通貨記号かカンマ区切りを伴う数値のみを候補にし、最大値を採用する。
const guessAmount = (text) => {
  const s = String(text || '');
  const cands = [];
  let m;
  const reMarked = /(?:[¥￥]\s?([\d,]{2,})|([\d,]{2,})\s?円)/g;
  while ((m = reMarked.exec(s))) { const n = Number((m[1] || m[2]).replace(/,/g, '')); if (n) cands.push(n); }
  const reGrouped = /(?:^|[^\d.])(\d{1,3}(?:,\d{3})+)(?![\d.])/g;
  while ((m = reGrouped.exec(s))) { const n = Number(m[1].replace(/,/g, '')); if (n) cands.push(n); }
  return cands.length ? Math.max(...cands) : 0;
};

const readCursor = (f) => { try { return fs.readFileSync(f, 'utf8').trim(); } catch (e) { return ''; } };
const writeCursor = (f, v) => fs.writeFileSync(f, String(v));

const cfgBase = () => ({
  syncUrl: (process.env.SYNC_URL || 'http://localhost:8787').replace(/\/$/, ''),
  workspace: process.env.WORKSPACE || '',
  token: process.env.TOKEN || '',
  salesAccount: process.env.SALES_ACCOUNT || '400',
  feeAccount: process.env.FEE_ACCOUNT || '580',
  salesTax: process.env.SALES_TAX || 'sales10',
  feeTax: process.env.FEE_TAX || 'out',
});
const postInbox = async (cfg, items) => {
  if (!cfg.workspace) throw new Error('WORKSPACE が未設定です');
  return postJson(`${cfg.syncUrl}/api/inbox`, { workspace: cfg.workspace, token: cfg.token, items });
};
// 売上＋手数料の2件を生成する共通ヘルパー
const salesAndFee = (cfg, date, desc, gross, fee, tag) => {
  const items = [];
  if (gross > 0) items.push({ date, description: `${tag}売上 ${desc}`, amount: gross, dir: 'in', account: cfg.salesAccount, tax: cfg.salesTax });
  if (fee > 0) items.push({ date, description: `${tag}決済手数料 ${desc}`, amount: fee, dir: 'out', account: cfg.feeAccount, tax: cfg.feeTax });
  return items;
};

module.exports = { request, getJson, postJson, postForm, ymd, ymdUnix, ymdIso, minorToYen, majorToYen, guessAmount, readCursor, writeCursor, cfgBase, postInbox, salesAndFee };
