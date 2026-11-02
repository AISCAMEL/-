/* zip.js ― 依存ゼロのZIP生成（無圧縮=storeメソッド）
 * A.zip.create([{name, data}]) → Blob（ブラウザ）
 *   data は文字列（UTF-8化される）または Uint8Array。
 * 税理士へ渡すCSV一式を1ファイルにまとめる用途。
 */
window.A = window.A || {};
(function () {
  'use strict';
  const table = (() => { const t = []; for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); t[n] = c >>> 0; } return t; })();
  const crc32 = (buf) => { let c = 0xFFFFFFFF; for (let i = 0; i < buf.length; i++) c = table[(c ^ buf[i]) & 0xFF] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; };
  const enc = (s) => new TextEncoder().encode(s);
  const u16 = (n) => [n & 255, (n >>> 8) & 255];
  const u32 = (n) => [n & 255, (n >>> 8) & 255, (n >>> 16) & 255, (n >>> 24) & 255];

  // DOS形式の日時（固定で現在時刻）
  const dosDate = () => { const d = new Date(); return ((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate(); };
  const dosTime = () => { const d = new Date(); return (d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1); };

  const create = (files) => {
    const dt = dosTime(), dd = dosDate();
    const parts = []; const central = []; let offset = 0;
    files.forEach((f) => {
      const name = enc(f.name);
      const data = typeof f.data === 'string' ? enc(f.data) : f.data;
      const crc = crc32(data);
      // 汎用フラグに bit11(0x0800)=ファイル名はUTF-8 を立てる（日本語名の文字化け防止）
      const local = new Uint8Array([].concat(
        u32(0x04034b50), u16(20), u16(0x0800), u16(0), u16(dt), u16(dd),
        u32(crc), u32(data.length), u32(data.length), u16(name.length), u16(0)));
      parts.push(local, name, data);
      const cen = new Uint8Array([].concat(
        u32(0x02014b50), u16(20), u16(20), u16(0x0800), u16(0), u16(dt), u16(dd),
        u32(crc), u32(data.length), u32(data.length), u16(name.length), u16(0), u16(0), u16(0), u16(0), u32(0), u32(offset)));
      central.push(cen, name);
      offset += local.length + name.length + data.length;
    });
    let cdSize = 0; central.forEach((c) => (cdSize += c.length));
    const end = new Uint8Array([].concat(
      u32(0x06054b50), u16(0), u16(0), u16(files.length), u16(files.length), u32(cdSize), u32(offset), u16(0)));
    return new Blob([...parts, ...central, end], { type: 'application/zip' });
  };

  A.zip = { create, crc32 };
})();
