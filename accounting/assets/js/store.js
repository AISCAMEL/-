/* =========================================================================
 * store.js  ―  ドメイン層（設定・勘定科目・取引先・仕訳・請求書のCRUD）
 * db.js（IndexedDB）の上に、業務ルールと便利メソッドを載せる。
 * ========================================================================= */
window.A = window.A || {};

A.store = (function () {
  'use strict';
  const db = A.db;
  const U = A.util;

  /* ---- 設定（会社情報・連番） ----------------------------------------- */
  let _settingsCache = null;
  const settings = {
    async load() { _settingsCache = await db.get('settings', 'company'); return _settingsCache; },
    get() { return _settingsCache || db.DEFAULT_SETTINGS; },
    async save(patch) {
      const cur = (await db.get('settings', 'company')) || { ...db.DEFAULT_SETTINGS };
      const next = { ...cur, ...patch, key: 'company' };
      await db.put('settings', next);
      _settingsCache = next;
      return next;
    },
    // 連番を1つ進めて採番。type: invoiceSeq / estimateSeq / journalSeq
    async nextSeq(type) {
      const cur = (await db.get('settings', 'company')) || { ...db.DEFAULT_SETTINGS };
      const n = (cur[type] || 0) + 1;
      await this.save({ [type]: n });
      return n;
    },
  };

  /* ---- 勘定科目 -------------------------------------------------------- */
  let _accountsCache = null;
  const accounts = {
    async loadAll() {
      const list = await db.all('accounts');
      list.sort((a, b) => a.code.localeCompare(b.code));
      _accountsCache = list;
      return list;
    },
    all() { return _accountsCache || []; },
    byCode(code) { return (_accountsCache || []).find((a) => a.code === code) || null; },
    name(code) { const a = this.byCode(code); return a ? a.name : '(不明:' + code + ')'; },
    category(code) { const a = this.byCode(code); return a ? a.category : null; },
    async save(acc) { await db.put('accounts', acc); await this.loadAll(); },
    async remove(code) { await db.del('accounts', code); await this.loadAll(); },
  };

  /* ---- 取引先 ---------------------------------------------------------- */
  const partners = {
    async loadAll() {
      const list = await db.all('partners');
      list.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'ja'));
      return list;
    },
    byId(list, id) { return list.find((p) => p.id === id) || null; },
    async save(p) {
      if (!p.id) p.id = U.uid('pt');
      await db.put('partners', p);
      return p;
    },
    async remove(id) { await db.del('partners', id); },
  };

  /* ---- 仕訳 ------------------------------------------------------------
   * journal = {
   *   id, no, date, description, source,   // source: 'manual'|'invoice'|'expense'
   *   lines: [ { side:'debit'|'credit', account, amount, tax, memo } ]
   * }
   * ------------------------------------------------------------------- */
  const journals = {
    async loadAll() {
      const list = await db.all('journals');
      list.sort((a, b) => (a.date === b.date ? (a.no - b.no) : a.date.localeCompare(b.date)));
      return list;
    },
    // 借方合計・貸方合計
    totals(j) {
      let d = 0, c = 0;
      (j.lines || []).forEach((l) => {
        if (l.side === 'debit') d += Number(l.amount) || 0;
        else c += Number(l.amount) || 0;
      });
      return { debit: d, credit: c, balanced: d === c && d > 0 };
    },
    async save(j) {
      if (!j.id) { j.id = U.uid('jr'); j.no = await settings.nextSeq('journalSeq'); }
      j.source = j.source || 'manual';
      await db.put('journals', j);
      return j;
    },
    // 連番を採番して複数まとめて保存（CSV一括計上・繰越などで使用）
    async saveMany(list) {
      const out = [];
      for (const j of list) out.push(await this.save(j));
      return out;
    },
    async remove(id) { await db.del('journals', id); },
    // 請求書・経費など「元データ」から生成した仕訳を削除
    async removeBySource(refId) {
      const list = await this.loadAll();
      await Promise.all(list.filter((j) => j.refId === refId).map((j) => db.del('journals', j.id)));
    },
    // source が一致する仕訳をまとめて削除（例：ある年度の期首残高を作り直す）
    async removeWhere(pred) {
      const list = await this.loadAll();
      await Promise.all(list.filter(pred).map((j) => db.del('journals', j.id)));
    },
  };

  /* ---- 固定資産 -------------------------------------------------------
   * asset = {
   *   id, name, accountCode(資産科目), acquireDate, acquireCost,
   *   usefulLife(耐用年数/年), residual(残存価額), method:'straight'(定額法),
   *   startDate(事業供用日), note, disposed(除却済), postedYears:[fiscalStartYear...]
   * }
   * 減価償却は直接法（借:減価償却費 / 貸:資産科目）で計上する。
   * ------------------------------------------------------------------- */
  const assets = {
    async loadAll() {
      const list = await db.all('assets');
      list.sort((a, b) => (a.acquireDate || '').localeCompare(b.acquireDate || ''));
      return list;
    },
    async save(a) {
      if (!a.id) a.id = U.uid('as');
      await db.put('assets', a);
      return a;
    },
    async remove(id) {
      await journals.removeWhere((j) => j.refId === id && j.source === 'depreciation');
      await db.del('assets', id);
    },
  };

  /* ---- 請求書・見積書 -------------------------------------------------
   * invoice = {
   *   id, type:'invoice'|'estimate', no, date, dueDate, partnerId, partnerName,
   *   items:[ {name, qty, unitPrice, taxRate, reduced} ], note, posted, journalRefId
   * }
   * 金額は税抜単価×数量で小計、税率ごとに消費税を計算（適格請求書方式）。
   * ------------------------------------------------------------------- */
  const invoices = {
    async loadAll() {
      const list = await db.all('invoices');
      list.sort((a, b) => (a.date === b.date ? (b.no - a.no) : b.date.localeCompare(a.date)));
      return list;
    },
    async get(id) { return db.get('invoices', id); },
    // 税率ごとの集計（10%対象額・8%対象額・各消費税・合計）
    calc(inv) {
      const buckets = {}; // rate -> {net, tax}
      (inv.items || []).forEach((it) => {
        const rate = Number(it.taxRate) || 0;
        const net = (Number(it.qty) || 0) * (Number(it.unitPrice) || 0);
        if (!buckets[rate]) buckets[rate] = { net: 0, tax: 0 };
        buckets[rate].net += net;
      });
      let net = 0, tax = 0;
      Object.keys(buckets).forEach((r) => {
        const b = buckets[r];
        b.tax = U.taxFromNet(b.net, Number(r)); // 税率区分ごとに端数処理（インボイス要件）
        net += b.net; tax += b.tax;
      });
      return { buckets, net, tax, total: net + tax };
    },
    async save(inv) {
      if (!inv.id) {
        inv.id = U.uid('iv');
        const seqType = inv.type === 'estimate' ? 'estimateSeq' : 'invoiceSeq';
        inv.no = await settings.nextSeq(seqType);
      }
      await db.put('invoices', inv);
      return inv;
    },
    async remove(id) {
      await journals.removeBySource(id);
      await db.del('invoices', id);
    },
    // 請求書番号の整形（例: INV-2026-0007 / EST-2026-0007）
    displayNo(inv) {
      const yy = (inv.date || U.today()).slice(0, 4);
      const p = String(inv.no || 0).padStart(4, '0');
      return `${inv.type === 'estimate' ? 'EST' : 'INV'}-${yy}-${p}`;
    },
  };

  /* ---- 証憑（電子帳簿保存法対応の添付ファイル） -----------------------
   * attachment = {
   *   id, journalId(任意), date(取引年月日), amount(取引金額), partner(取引先),
   *   filename, mime, size, dataUrl(base64), note, createdAt(登録日時)
   * }
   * 電帳法の検索要件（日付・金額・取引先）を満たすため、これらを保持する。
   * ------------------------------------------------------------------- */
  const attachments = {
    async loadAll() {
      const list = await db.all('attachments');
      list.sort((a, b) => (b.date || '').localeCompare(a.date || ''));
      return list;
    },
    async byJournal(journalId) {
      return (await db.all('attachments')).filter((x) => x.journalId === journalId);
    },
    async save(att) {
      if (!att.id) att.id = U.uid('at');
      if (!att.createdAt) att.createdAt = new Date().toISOString();
      await db.put('attachments', att);
      return att;
    },
    async remove(id) { await db.del('attachments', id); },
  };

  return { settings, accounts, partners, journals, invoices, assets, attachments };
})();
