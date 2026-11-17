/* =========================================================================
 * connectors/pdfextract.js ― パスワード保護PDFのテキスト抽出（依存ゼロ）
 *
 * Node 標準の crypto / zlib だけで、
 *   ・暗号化なしPDF
 *   ・標準セキュリティハンドラ（R2/R3=RC4, R4=AESV2, R6=AESV3）
 * のテキストを取り出す。USSの精算書のように「開くパスワード（会員番号5桁）」で
 * 保護されたPDFを、パスワードを渡して復号→金額抽出するために使う。
 *
 * 使い方:
 *   const { extractText } = require('./pdfextract');
 *   const text = extractText(buffer, '12345');  // 失敗時は '' を返す（例外は投げない）
 *
 * 注意: スキャン画像PDFや、数字を独自エンコーディングで埋め込むフォントの場合は
 *       テキストが取れないことがある。その場合は呼び出し側で金額0の下書きに
 *       フォールバックする運用。
 * ========================================================================= */
'use strict';
const crypto = require('crypto');
const zlib = require('zlib');

/* ---- RC4（対称ストリーム暗号：暗号化＝復号） ------------------------------ */
function rc4(key, data) {
  const s = []; for (let i = 0; i < 256; i++) s[i] = i;
  let j = 0;
  for (let i = 0; i < 256; i++) { j = (j + s[i] + key[i % key.length]) & 255; const t = s[i]; s[i] = s[j]; s[j] = t; }
  const out = Buffer.alloc(data.length); let a = 0, b = 0;
  for (let k = 0; k < data.length; k++) { a = (a + 1) & 255; b = (b + s[a]) & 255; const t = s[a]; s[a] = s[b]; s[b] = t; out[k] = data[k] ^ s[(s[a] + s[b]) & 255]; }
  return out;
}

/* ---- 標準パスワードパディング（32バイト） -------------------------------- */
const PAD = Buffer.from([
  0x28, 0xBF, 0x4E, 0x5E, 0x4E, 0x75, 0x8A, 0x41, 0x64, 0x00, 0x4E, 0x56, 0xFF, 0xFA, 0x01, 0x08,
  0x2E, 0x2E, 0x00, 0xB6, 0xD0, 0x68, 0x3E, 0x80, 0x2F, 0x0C, 0xA9, 0xFE, 0x64, 0x53, 0x69, 0x7A,
]);
function padPw(pw) {
  const b = Buffer.from(pw || '', 'latin1');
  const out = Buffer.alloc(32);
  b.copy(out, 0, 0, Math.min(32, b.length));
  if (b.length < 32) PAD.copy(out, b.length, 0, 32 - b.length);
  return out;
}

/* ---- ファイル暗号鍵（アルゴリズム2：R2〜R4） ------------------------------ */
function computeFileKey(pw, O, P, id0, R, lengthBits, encryptMetadata) {
  const n = Math.max(5, (lengthBits || 40) / 8);
  let h = crypto.createHash('md5');
  h.update(padPw(pw));
  h.update(O.slice(0, 32));
  const pbuf = Buffer.alloc(4); pbuf.writeInt32LE(P | 0, 0); h.update(pbuf);
  h.update(id0);
  if (R >= 4 && encryptMetadata === false) h.update(Buffer.from([0xff, 0xff, 0xff, 0xff]));
  let key = h.digest();
  if (R >= 3) for (let i = 0; i < 50; i++) key = crypto.createHash('md5').update(key.slice(0, n)).digest();
  return key.slice(0, n);
}

/* ---- オブジェクト鍵（R2〜R4：オブジェクト番号・世代で派生） ---------------- */
function objectKey(fileKey, num, gen, aes) {
  const h = crypto.createHash('md5');
  h.update(fileKey);
  h.update(Buffer.from([num & 255, (num >> 8) & 255, (num >> 16) & 255]));
  h.update(Buffer.from([gen & 255, (gen >> 8) & 255]));
  if (aes) h.update(Buffer.from([0x73, 0x41, 0x6c, 0x54])); // "sAlT"
  const n = Math.min(fileKey.length + 5, 16);
  return h.digest().slice(0, n);
}

/* ---- R6（AESV3）：ハッシュ アルゴリズム2.B ------------------------------- */
function hash2B(pwBuf, salt, udata) {
  let K = crypto.createHash('sha256').update(Buffer.concat([pwBuf, salt, udata || Buffer.alloc(0)])).digest();
  for (let round = 0; ; round++) {
    const block = Buffer.concat([pwBuf, K, udata || Buffer.alloc(0)]);
    const K1 = Buffer.concat(Array(64).fill(block));
    const cipher = crypto.createCipheriv('aes-128-cbc', K.slice(0, 16), K.slice(16, 32));
    cipher.setAutoPadding(false);
    const E = Buffer.concat([cipher.update(K1), cipher.final()]);
    let mod = 0; for (let i = 0; i < 16; i++) mod += E[i]; mod %= 3;
    const algo = mod === 0 ? 'sha256' : mod === 1 ? 'sha384' : 'sha512';
    K = crypto.createHash(algo).update(E).digest();
    if (round >= 63 && E[E.length - 1] <= round - 32) break;
  }
  return K.slice(0, 32);
}
function computeFileKeyR6(pw, U, UE) {
  const pwBuf = Buffer.from(pw || '', 'utf8');
  const keySalt = U.slice(40, 48);
  const ik = hash2B(pwBuf, keySalt, null);
  const d = crypto.createDecipheriv('aes-256-cbc', ik, Buffer.alloc(16));
  d.setAutoPadding(false);
  return Buffer.concat([d.update(UE), d.final()]);
}

/* ---- PDFの最小パーサ（生成PDF向けの実用レベル） -------------------------- */
// name/number/string/hex/ref をたどれる程度に、辞書からキーを拾う。
function findDictValue(dict, key) {
  const re = new RegExp('/' + key + '\\s*([^/>\\]]*)');
  const m = dict.match(re);
  return m ? m[1].trim() : null;
}
function refOf(dict, key) {
  const re = new RegExp('/' + key + '\\s+(\\d+)\\s+(\\d+)\\s+R');
  const m = dict.match(re);
  return m ? { num: Number(m[1]), gen: Number(m[2]) } : null;
}
function litStr(dict, key) { // (....) リテラル文字列（エスケープ簡易）
  const re = new RegExp('/' + key + '\\s*\\(((?:\\\\.|[^\\\\()])*)\\)');
  const m = dict.match(re);
  if (!m) return null;
  return Buffer.from(m[1].replace(/\\([nrtbf()\\])/g, (s, c) => ({ n: '\n', r: '\r', t: '\t', b: '\b', f: '\f' }[c] || c)), 'latin1');
}
function hexStr(dict, key) { // <....> 16進文字列
  const re = new RegExp('/' + key + '\\s*<([0-9A-Fa-f\\s]*)>');
  const m = dict.match(re);
  if (!m) return null;
  return Buffer.from(m[1].replace(/\s/g, ''), 'hex');
}
function anyStr(dict, key) { return litStr(dict, key) || hexStr(dict, key); }

// バイト列から "N G obj ... endobj" を全て列挙
function parseObjects(buf) {
  const s = buf.toString('latin1');
  const objs = {};
  const re = /(\d+)\s+(\d+)\s+obj\b/g;
  let m;
  while ((m = re.exec(s))) {
    const num = Number(m[1]), gen = Number(m[2]);
    const start = re.lastIndex;
    const end = s.indexOf('endobj', start);
    if (end < 0) continue;
    const body = s.slice(start, end);
    const si = body.indexOf('stream');
    let dict = body, stream = null;
    if (si >= 0) {
      dict = body.slice(0, si);
      // stream の直後は \r\n か \n
      let ds = si + 'stream'.length;
      if (body[ds] === '\r') ds++;
      if (body[ds] === '\n') ds++;
      let de = body.indexOf('endstream', ds);
      if (de < 0) de = body.length;
      // 末尾の改行を落とす
      let deReal = de;
      if (body[deReal - 1] === '\n') deReal--;
      if (body[deReal - 1] === '\r') deReal--;
      stream = Buffer.from(body.slice(ds, deReal), 'latin1');
    }
    objs[num + '_' + gen] = { num, gen, dict, stream };
    objs[num] = objs[num + '_' + gen];
  }
  return { objs, raw: s };
}

// 文字列オペレータ（Tj / TJ / ' / "）からテキストを結合
function extractTextFromContent(content) {
  const s = content.toString('latin1');
  let out = '';
  const unesc = (t) => t.replace(/\\([nrtbf()\\]|\d{1,3})/g, (m0, c) => {
    if (/^\d+$/.test(c)) return String.fromCharCode(parseInt(c, 8));
    return ({ n: '\n', r: '\r', t: '\t', b: '\b', f: '\f' }[c] || c);
  });
  // ( ... ) Tj / '
  const reTj = /\(((?:\\.|[^\\()])*)\)\s*(?:Tj|')/g;
  let m;
  while ((m = reTj.exec(s))) out += unesc(m[1]);
  // [ (..) n (..) ] TJ
  const reTJ = /\[((?:[^\]\\]|\\.)*)\]\s*TJ/g;
  while ((m = reTJ.exec(s))) {
    const arr = m[1];
    const reIn = /\(((?:\\.|[^\\()])*)\)/g;
    let mm; while ((mm = reIn.exec(arr))) out += unesc(mm[1]);
  }
  return out;
}

function inflateMaybe(dict, data) {
  if (/\/FlateDecode/.test(dict)) { try { return zlib.inflateSync(data); } catch (e) { try { return zlib.inflateRawSync(data); } catch (e2) { return data; } } }
  return data;
}

/* ---- メイン：テキスト抽出 ------------------------------------------------ */
function extractText(buffer, password) {
  try {
    const { objs, raw } = parseObjects(buffer);

    // 暗号情報（trailer の /Encrypt と /ID）
    let enc = null, id0 = Buffer.alloc(0);
    const encRefM = raw.match(/\/Encrypt\s+(\d+)\s+(\d+)\s+R/);
    const idM = raw.match(/\/ID\s*\[\s*<([0-9A-Fa-f]*)>/);
    if (idM) id0 = Buffer.from(idM[1], 'hex');
    if (encRefM) {
      const e = objs[Number(encRefM[1])];
      if (e) {
        const d = e.dict;
        const R = Number(findDictValue(d, 'R'));
        const V = Number(findDictValue(d, 'V'));
        const P = Number(findDictValue(d, 'P'));
        const len = Number(findDictValue(d, 'Length')) || 40;
        const O = anyStr(d, 'O') || Buffer.alloc(32);
        const U = anyStr(d, 'U') || Buffer.alloc(32);
        const UE = anyStr(d, 'UE');
        const emeta = /\/EncryptMetadata\s+false/.test(d) ? false : true;
        // 暗号方式（AES or RC4）
        let aes = false, aes256 = false;
        if (V >= 4) {
          if (/AESV3/.test(d)) { aes = true; aes256 = true; }
          else if (/AESV2/.test(d)) aes = true;
          else if (/\/V2/.test(d)) aes = false; // RC4
        }
        let fileKey;
        if (R >= 5) fileKey = computeFileKeyR6(password, U, UE || Buffer.alloc(32));
        else fileKey = computeFileKey(password, O, P, id0, R, len, emeta);
        enc = { R, V, aes, aes256, fileKey };
      }
    }

    const decrypt = (o) => {
      if (!o.stream) return null;
      let data = o.stream;
      if (enc) {
        try {
          if (enc.R >= 5 || enc.aes256) {
            const iv = data.slice(0, 16);
            const dc = crypto.createDecipheriv('aes-256-cbc', enc.fileKey, iv); dc.setAutoPadding(false);
            data = Buffer.concat([dc.update(data.slice(16)), dc.final()]);
            data = stripPkcs7(data);
          } else if (enc.aes) {
            const k = objectKey(enc.fileKey, o.num, o.gen, true);
            const iv = data.slice(0, 16);
            const dc = crypto.createDecipheriv('aes-' + (k.length * 8) + '-cbc', k, iv); dc.setAutoPadding(false);
            data = Buffer.concat([dc.update(data.slice(16)), dc.final()]);
            data = stripPkcs7(data);
          } else {
            const k = objectKey(enc.fileKey, o.num, o.gen, false);
            data = rc4(k, data);
          }
        } catch (e) { /* この objは復号失敗 → スキップ */ return null; }
      }
      return data;
    };

    // すべてのストリームを復号→（Flate）展開→テキスト抽出（コンテンツ以外も含めて広めに走査）
    let text = '';
    Object.keys(objs).forEach((k) => {
      if (k.indexOf('_') < 0) return; // num のエイリアスは飛ばす
      const o = objs[k];
      const data = decrypt(o);
      if (!data) return;
      if (/\/XObject|\/Image|\/Font(?!Descriptor)|\/ObjStm/.test(o.dict) && !/\/Contents/.test(o.dict)) {
        // 画像等はスキップ（ObjStmは今回対象外）
        if (/\/Image/.test(o.dict)) return;
      }
      const inflated = inflateMaybe(o.dict, data);
      text += ' ' + extractTextFromContent(inflated);
    });
    return text;
  } catch (e) {
    return '';
  }
}
function stripPkcs7(buf) {
  if (!buf.length) return buf;
  const p = buf[buf.length - 1];
  if (p > 0 && p <= 16 && p <= buf.length) return buf.slice(0, buf.length - p);
  return buf;
}

module.exports = { extractText, rc4, computeFileKey, objectKey, padPw, hash2B, computeFileKeyR6 };
