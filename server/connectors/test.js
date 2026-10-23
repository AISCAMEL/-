/* =========================================================================
 * connectors/test.js ― コネクタのユニットテスト（依存ゼロ）
 *   node server/connectors/test.js
 * guessAmount と pdfextract（暗号化なし/RC4/AESV2/AESV3）を検証する。
 * ========================================================================= */
'use strict';
const zlib = require('zlib');
const crypto = require('crypto');
const L = require('./_lib');
const P = require('./pdfextract');

let pass = 0, fail = 0;
const ok = (name, cond) => { if (cond) { pass++; } else { fail++; console.log('  ✗ ' + name); } };

/* ---- guessAmount ---- */
ok('guessAmount: 円付き', L.guessAmount('合計 1,250,000 円') === 1250000);
ok('guessAmount: ¥付き', L.guessAmount('お支払い ¥45,800 です') === 45800);
ok('guessAmount: カンマ区切りのみ', L.guessAmount('請求額 1,250,000 を確認') === 1250000);
ok('guessAmount: 年号は除外', L.guessAmount('2026年 定期便のご案内') === 0);
ok('guessAmount: 日付は除外', L.guessAmount('[USS] 2026/09/24 精算書') === 0);
ok('guessAmount: 最大値を採用', L.guessAmount('内訳 1,000,000 円 手数料 55,000 円') === 1000000);
ok('guessAmount: 該当なし', L.guessAmount('精算書を添付します') === 0);

/* ---- pdfextract：テスト用PDF生成ヘルパ ---- */
const ID0 = Buffer.from('0123456789abcdef0123456789abcdef', 'hex');
const flateOf = (t) => zlib.deflateSync(Buffer.from(t, 'latin1'));
const common = [
  '<< /Type /Catalog /Pages 2 0 R >>',
  '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
  '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R >>',
];
const contentObj = (streamBuf) => '<< /Length ' + streamBuf.length + ' /FlateDecode >>\nstream\n' + streamBuf.toString('latin1') + '\nendstream';
const build = (objs, trailerExtra) => {
  let s = '%PDF-1.7\n';
  objs.forEach((o, i) => { s += (i + 1) + ' 0 obj\n' + o + '\nendobj\n'; });
  s += 'trailer\n<< /Size ' + (objs.length + 1) + ' /Root 1 0 R /ID [<' + ID0.toString('hex') + '><' + ID0.toString('hex') + '>] ' + (trailerExtra || '') + '>>\n%%EOF';
  return Buffer.from(s, 'latin1');
};
const TEXT = 'BT /F1 12 Tf 72 700 Td (USS Seisan Goukei 1,250,000 en) Tj ET';
const flate = flateOf(TEXT);
const hasAmt = (t) => L.guessAmount(t) === 1250000;

// 1) 暗号化なし
ok('pdf: 暗号化なし', hasAmt(P.extractText(build([...common, contentObj(flate)]), '')));

// 2) RC4 (R3,V2,128)
{
  const O = crypto.randomBytes(32), U = crypto.randomBytes(32), Pv = -44;
  const fk = P.computeFileKey('12345', O, Pv, ID0, 3, 128, true);
  const enc = P.rc4(P.objectKey(fk, 4, 0, false), flate);
  const encDict = '<< /Filter /Standard /V 2 /R 3 /Length 128 /O <' + O.toString('hex') + '> /U <' + U.toString('hex') + '> /P ' + Pv + ' >>';
  const pdf = build([...common, contentObj(enc), encDict], '/Encrypt 5 0 R ');
  ok('pdf: RC4 正パスワード', hasAmt(P.extractText(pdf, '12345')));
  ok('pdf: RC4 誤パスワードは読めない', !hasAmt(P.extractText(pdf, '99999')));
}

// 3) AESV2 (R4,V4,128)
{
  const O = crypto.randomBytes(32), U = crypto.randomBytes(32), Pv = -44;
  const fk = P.computeFileKey('12345', O, Pv, ID0, 4, 128, true);
  const iv = crypto.randomBytes(16);
  const c = crypto.createCipheriv('aes-128-cbc', P.objectKey(fk, 4, 0, true), iv);
  const enc = Buffer.concat([iv, c.update(flate), c.final()]);
  const encDict = '<< /Filter /Standard /V 4 /R 4 /Length 128 /CF << /StdCF << /CFM /AESV2 /Length 16 >> >> /StmF /StdCF /StrF /StdCF /O <' + O.toString('hex') + '> /U <' + U.toString('hex') + '> /P ' + Pv + ' >>';
  const pdf = build([...common, contentObj(enc), encDict], '/Encrypt 5 0 R ');
  ok('pdf: AESV2 正パスワード', hasAmt(P.extractText(pdf, '12345')));
}

// 4) AESV3 (R6,V5,256)
{
  const pwBuf = Buffer.from('12345', 'utf8');
  const fk = crypto.randomBytes(32);
  const keySalt = crypto.randomBytes(8), valSalt = crypto.randomBytes(8);
  const ik = P.hash2B(pwBuf, keySalt, null);
  const ce = crypto.createCipheriv('aes-256-cbc', ik, Buffer.alloc(16)); ce.setAutoPadding(false);
  const UE = Buffer.concat([ce.update(fk), ce.final()]);
  const U = Buffer.concat([P.hash2B(pwBuf, valSalt, null), valSalt, keySalt]);
  const O = crypto.randomBytes(48), OE = crypto.randomBytes(32);
  const iv = crypto.randomBytes(16);
  const c = crypto.createCipheriv('aes-256-cbc', fk, iv);
  const enc = Buffer.concat([iv, c.update(flate), c.final()]);
  const encDict = '<< /Filter /Standard /V 5 /R 6 /Length 256 /CF << /StdCF << /CFM /AESV3 /Length 32 >> >> /StmF /StdCF /StrF /StdCF /O <' + O.toString('hex') + '> /OE <' + OE.toString('hex') + '> /U <' + U.toString('hex') + '> /UE <' + UE.toString('hex') + '> /P -44 >>';
  const pdf = build([...common, contentObj(enc), encDict], '/Encrypt 5 0 R ');
  ok('pdf: AESV3 正パスワード', hasAmt(P.extractText(pdf, '12345')));
}

console.log(`\n合計: ${pass + fail} 件 / 成功 ${pass} / 失敗 ${fail}`);
if (fail) process.exit(1); else console.log('✅ すべて成功');
