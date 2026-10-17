/* =========================================================================
 * util.js  ―  共通ユーティリティ（金額・日付・DOM・ID）
 * すべてグローバル名前空間 `A`（Accounting）配下に登録する。
 * ビルド不要・file:// でも動くよう ES モジュールは使わない。
 * ========================================================================= */
window.A = window.A || {};

A.util = (function () {
  'use strict';

  /* ---- 金額 ------------------------------------------------------------ */
  // 会計では小数を避けるため、金額はすべて「円（整数）」で保持する。
  const yen = (n) => {
    const v = Math.round(Number(n) || 0);
    return v.toLocaleString('ja-JP');
  };
  // 符号付き（マイナスは △ 表記＝会計慣習）
  const yenSigned = (n) => {
    const v = Math.round(Number(n) || 0);
    return v < 0 ? '△' + Math.abs(v).toLocaleString('ja-JP') : v.toLocaleString('ja-JP');
  };
  const parseYen = (s) => {
    if (s === null || s === undefined) return 0;
    const v = String(s).replace(/[,，\s円]/g, '');
    const n = Number(v);
    return Number.isFinite(n) ? Math.round(n) : 0;
  };

  /* ---- 消費税 ---------------------------------------------------------- */
  // 税込金額から内税（含まれる消費税額）を求める。端数は切り捨て。
  const taxIncludedPortion = (grossYen, ratePct) => {
    if (!ratePct) return 0;
    return Math.floor((Number(grossYen) || 0) * ratePct / (100 + ratePct));
  };
  // 税抜金額に対する消費税額（端数切り捨て）
  const taxFromNet = (netYen, ratePct) => {
    return Math.floor((Number(netYen) || 0) * ratePct / 100);
  };

  /* ---- 日付 ------------------------------------------------------------ */
  // 保存は 'YYYY-MM-DD' 文字列に統一。
  const today = () => {
    const d = new Date();
    const p = (x) => String(x).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
  };
  const fmtDate = (s) => (s || '').replaceAll('-', '/');
  // 会計年度の判定（settings の期首月から算出）。返り値は 'YYYY-MM-DD' の {start,end}。
  const fiscalRange = (dateStr, startMonth) => {
    const d = new Date(dateStr + 'T00:00:00');
    const m = d.getMonth() + 1;
    let startYear = d.getFullYear();
    if (m < startMonth) startYear -= 1;
    const p = (x) => String(x).padStart(2, '0');
    const start = `${startYear}-${p(startMonth)}-01`;
    const endD = new Date(startYear + 1, startMonth - 1, 0); // 期首の1年後の前月末
    const end = `${endD.getFullYear()}-${p(endD.getMonth() + 1)}-${p(endD.getDate())}`;
    return { start, end };
  };
  const inRange = (dateStr, start, end) =>
    (!start || dateStr >= start) && (!end || dateStr <= end);

  /* ---- ID -------------------------------------------------------------- */
  let seq = 0;
  const uid = (prefix) => {
    seq += 1;
    const t = Date.now().toString(36);
    const r = Math.floor(Math.random() * 1e6).toString(36);
    return `${prefix || 'id'}_${t}${seq}${r}`;
  };

  /* ---- DOM ------------------------------------------------------------- */
  // 簡易な要素生成: el('div.card', {onclick}, [children|text])
  const el = (spec, attrs, children) => {
    const m = spec.match(/^([a-z0-9]+)?((?:[.#][\w-]+)*)$/i);
    const tag = (m && m[1]) || 'div';
    const node = document.createElement(tag);
    const cls = (m && m[2] || '').match(/\.[\w-]+/g);
    if (cls) node.className = cls.map((c) => c.slice(1)).join(' ');
    const idm = (m && m[2] || '').match(/#([\w-]+)/);
    if (idm) node.id = idm[1];
    if (attrs) {
      for (const k in attrs) {
        const v = attrs[k];
        if (v === null || v === undefined || v === false) continue;
        if (k === 'html') node.innerHTML = v;
        else if (k === 'text') node.textContent = v;
        else if (k.startsWith('on') && typeof v === 'function') {
          node.addEventListener(k.slice(2).toLowerCase(), v);
        } else if (k === 'value') node.value = v;
        else node.setAttribute(k, v);
      }
    }
    const kids = children || (attrs && attrs.children);
    if (kids !== undefined && kids !== null) {
      (Array.isArray(kids) ? kids : [kids]).forEach((c) => {
        if (c === null || c === undefined || c === false) return;
        node.appendChild(typeof c === 'string' || typeof c === 'number'
          ? document.createTextNode(String(c)) : c);
      });
    }
    return node;
  };
  const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

  return {
    yen, yenSigned, parseYen,
    taxIncludedPortion, taxFromNet,
    today, fmtDate, fiscalRange, inRange,
    uid, el, esc,
  };
})();
