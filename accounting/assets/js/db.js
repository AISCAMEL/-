/* =========================================================================
 * db.js  ―  IndexedDB ラッパ ＋ 初期データ投入 ＋ バックアップ入出力
 * サーバー不要。全データはブラウザ内（オリジンごと）に保存される。
 * ========================================================================= */
window.A = window.A || {};

A.db = (function () {
  'use strict';

  const DB_NAME = 'aizu_kaikei';
  const DB_VERSION = 1;
  // オブジェクトストア（テーブル）一覧
  const STORES = {
    settings: { keyPath: 'key' },
    accounts: { keyPath: 'code' },
    partners: { keyPath: 'id' },
    journals: { keyPath: 'id', indexes: [['date', 'date']] },
    invoices: { keyPath: 'id', indexes: [['date', 'date']] },
  };

  let _db = null;

  const open = () => new Promise((resolve, reject) => {
    if (_db) return resolve(_db);
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      for (const name in STORES) {
        if (db.objectStoreNames.contains(name)) continue;
        const cfg = STORES[name];
        const os = db.createObjectStore(name, { keyPath: cfg.keyPath });
        (cfg.indexes || []).forEach(([idx, path]) => os.createIndex(idx, path));
      }
    };
    req.onsuccess = (e) => { _db = e.target.result; resolve(_db); };
    req.onerror = () => reject(req.error);
  });

  const tx = async (store, mode) => {
    const db = await open();
    return db.transaction(store, mode).objectStore(store);
  };
  const wrap = (req) => new Promise((res, rej) => {
    req.onsuccess = () => res(req.result);
    req.onerror = () => rej(req.error);
  });

  /* ---- CRUD ------------------------------------------------------------ */
  const put = async (store, obj) => wrap((await tx(store, 'readwrite')).put(obj));
  const get = async (store, key) => wrap((await tx(store, 'readonly')).get(key));
  const del = async (store, key) => wrap((await tx(store, 'readwrite')).delete(key));
  const all = async (store) => wrap((await tx(store, 'readonly')).getAll());
  const clear = async (store) => wrap((await tx(store, 'readwrite')).clear());
  const bulkPut = async (store, arr) => {
    const os = await tx(store, 'readwrite');
    await Promise.all(arr.map((o) => wrap(os.put(o))));
  };

  /* ---- 初期化 ---------------------------------------------------------- */
  const DEFAULT_SETTINGS = {
    key: 'company',
    name: '合同会社アイズ',
    invoiceRegNo: 'T0000000000000',       // 適格請求書発行事業者 登録番号
    address: '',
    tel: '',
    email: 'info@aisjaltd.com',
    bank: '',                              // 振込先（請求書に表示）
    fiscalStartMonth: 4,                   // 期首月（4=4月始まり）
    taxMethod: 'included',                 // 税込経理
    invoiceSeq: 0,                         // 請求書番号カウンタ
    estimateSeq: 0,                        // 見積書番号カウンタ
    journalSeq: 0,                         // 仕訳番号カウンタ
  };

  // 初回のみ既定データを投入する。
  const seedIfEmpty = async () => {
    const s = await get('settings', 'company');
    if (!s) await put('settings', DEFAULT_SETTINGS);
    const acc = await all('accounts');
    if (!acc.length) await bulkPut('accounts', A.accounts.DEFAULT_ACCOUNTS.map((a) => ({ ...a })));
  };

  /* ---- バックアップ ---------------------------------------------------- */
  const exportAll = async () => {
    const data = {};
    for (const name in STORES) data[name] = await all(name);
    return {
      _app: 'aizu-kaikei', _version: DB_VERSION,
      _exportedAt: new Date().toISOString(), data,
    };
  };
  const importAll = async (payload, { replace = true } = {}) => {
    const data = (payload && payload.data) || {};
    for (const name in STORES) {
      if (!data[name]) continue;
      if (replace) await clear(name);
      await bulkPut(name, data[name]);
    }
    // settings 内の company を必ず確保
    await seedIfEmpty();
  };

  return {
    open, seedIfEmpty,
    put, get, del, all, clear, bulkPut,
    exportAll, importAll,
    DEFAULT_SETTINGS, STORES,
  };
})();
