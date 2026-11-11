/* =========================================================================
 * connectors/gmail.js ― Gmail → 会計アプリ 取込コネクタ（依存ゼロ）
 *
 * 指定した送信元ルールに一致するメールを Gmail から取得し、会計アプリの
 * 取込Webhook（/api/inbox）へ「仕訳データ」を投入する。
 *   ・金額はメール本文（件名・スニペット）から推定。
 *   ・本文に無い場合は添付PDF（USS精算書など）を復号して金額を読み取る。
 *     パスワードは環境変数/ルールで渡す（会員番号5桁など）。
 *   ・読み取れない場合は金額0の下書きにして、取込画面で人が確認・入力（承認制）。
 *   ・ルールに autopost:true を指定した送信元は、金額を確実に読めた明細だけ
 *     自動計上フラグ（auto）を付けて投入できる。
 *
 * 認証：Google OAuth2（リフレッシュトークン）。
 * 実行:
 *   GMAIL_CLIENT_ID=... GMAIL_CLIENT_SECRET=... GMAIL_REFRESH_TOKEN=... \
 *   GMAIL_PDF_PASSWORDS=12345 \  # 任意：PDFのパスワード（カンマ区切りで複数可）
 *   SYNC_URL=http://localhost:8787 WORKSPACE=aizu TOKEN=合言葉 \
 *   node server/connectors/gmail.js
 * ========================================================================= */
'use strict';
const path = require('path');
const L = require('./_lib');
const pdf = require('./pdfextract');

const CURSOR = path.join(__dirname, '.gmail_cursor');
const GBASE = process.env.GMAIL_API_BASE || 'https://gmail.googleapis.com';
const OAUTH = process.env.GOOGLE_OAUTH_BASE || 'https://oauth2.googleapis.com';

// 取込ルール：from に一致したら会計対象。JSON(GMAIL_RULES)で上書き可。
// 各ルール: { from, account, tax, dir, label, autopost?, pdfPassword? }
const DEFAULT_RULES = [
  { from: 'ussnet.co.jp', account: '500', tax: 'purchase10', dir: 'out', label: 'USSオークション精算' },
];
const rules = (() => { try { return JSON.parse(process.env.GMAIL_RULES) || DEFAULT_RULES; } catch (e) { return DEFAULT_RULES; } });

// 試すPDFパスワード候補（空文字＝パスワードなしPDFも対象に含める）
const envPasswords = (process.env.GMAIL_PDF_PASSWORDS || process.env.GMAIL_PDF_PASSWORD || '')
  .split(',').map((s) => s.trim()).filter(Boolean);

const header = (msg, name) => {
  const h = ((msg.payload && msg.payload.headers) || []).find((x) => x.name.toLowerCase() === name.toLowerCase());
  return h ? h.value : '';
};

// payload を再帰的にたどってPDF添付（filename と attachmentId）を集める
const collectPdfParts = (part, out) => {
  if (!part) return out;
  const fn = (part.filename || '');
  const mt = (part.mimeType || '');
  if ((/\.pdf$/i.test(fn) || mt === 'application/pdf') && part.body && part.body.attachmentId) {
    out.push({ filename: fn, attachmentId: part.body.attachmentId });
  }
  (part.parts || []).forEach((p) => collectPdfParts(p, out));
  return out;
};
const b64urlToBuf = (s) => Buffer.from(String(s || '').replace(/-/g, '+').replace(/_/g, '/'), 'base64');

// 添付PDFを取得→パスワード候補で復号→金額を推定（読めたら返す、無ければ0）
async function amountFromAttachments(auth, id, msg, rule) {
  const parts = collectPdfParts(msg.payload, []);
  if (!parts.length) return 0;
  const pws = [rule.pdfPassword, ...envPasswords, ''].filter((v, i, a) => v !== undefined && a.indexOf(v) === i);
  for (const p of parts) {
    let att;
    try { att = await L.getJson(`${GBASE}/gmail/v1/users/me/messages/${id}/attachments/${p.attachmentId}`, auth); }
    catch (e) { continue; }
    const buf = b64urlToBuf(att.data);
    for (const pw of pws) {
      const text = pdf.extractText(buf, pw);
      const amt = L.guessAmount(text);
      if (amt > 0) return amt;
    }
  }
  return 0;
}

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

  const items = []; let maxInternal = cursor; let autoCount = 0, pdfCount = 0;
  for (const id of ids) {
    const msg = await L.getJson(`${GBASE}/gmail/v1/users/me/messages/${id}?format=full`, auth);
    const internal = Number(msg.internalDate) || 0;
    if (internal <= cursor) continue;
    if (internal > maxInternal) maxInternal = internal;
    const from = header(msg, 'From');
    const subject = header(msg, 'Subject');
    const rule = R.find((r) => from.includes(r.from));
    if (!rule) continue;
    const date = L.ymdUnix(Math.floor(internal / 1000));

    // ① 件名・スニペットから金額を推定 → ② 無ければ添付PDFから読み取り
    let amount = L.guessAmount(subject + ' ' + (msg.snippet || ''));
    let fromPdf = false;
    if (!amount) { const a = await amountFromAttachments(auth, id, msg, rule); if (a > 0) { amount = a; fromPdf = true; pdfCount++; } }

    const item = {
      date, description: `[メール取込/${rule.label}] ${subject}`.slice(0, 160),
      amount, dir: rule.dir, account: rule.account, tax: rule.tax,
    };
    // 自動計上：ルールがautopost かつ金額を確実に読めた場合のみ auto フラグを付与
    // counter（相手勘定：支払元の口座/未払金など）はルールで指定する
    if (rule.counter) item.counter = rule.counter;
    if (rule.autopost && amount > 0 && rule.counter) { item.auto = true; autoCount++; }
    items.push(item);
  }
  if (!items.length) { console.log('新規の対象メールはありません。'); return; }
  const res = await L.postInbox(cfg, items);
  L.writeCursor(CURSOR, maxInternal);
  console.log(`Gmail 対象メール ${items.length}件 → 受信キューに投入（計 ${res.total} 件）。`);
  if (pdfCount) console.log(`  うち ${pdfCount}件は添付PDFから金額を自動読み取りしました。`);
  if (autoCount) console.log(`  うち ${autoCount}件は自動計上（auto）対象です。取込画面で「受信データを取得」すると計上されます。`);
  const zero = items.filter((i) => !i.amount).length;
  if (zero) console.log(`  金額0の下書き ${zero}件は、添付PDFのパスワード設定を確認するか、取込画面で金額を入力してください。`);
}
main().catch((e) => { console.error('エラー:', e.message); process.exit(1); });
