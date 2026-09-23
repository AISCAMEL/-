/* =========================================================================
 * demo.js ― デモ（サンプル）データの投入
 * 合同会社アイズ（車オークション代行）を題材に、各画面が埋まるよう
 * 取引・請求書・固定資産・給与・棚卸・予算などを一括生成する。
 * ========================================================================= */
window.A = window.A || {};

A.demo = (function () {
  'use strict';
  const db = A.db, S = A.store, U = A.util;

  // 会計年度（今日基準）の開始年を使う
  const load = async () => {
    // 全消去して初期化
    for (const name in db.STORES) await db.clear(name);
    await db.seedIfEmpty();
    await S.accounts.loadAll();

    const s0 = S.settings.get();
    const fy = U.fiscalRange(U.today(), 4);
    const Y = Number(fy.start.slice(0, 4)); // 期首年
    const d = (m, day) => { // 期首(4月)からの相対月 → YYYY-MM-DD
      const mm = ((4 - 1 + m) % 12) + 1;
      const yy = Y + Math.floor((4 - 1 + m) / 12);
      return `${yy}-${String(mm).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    };

    // 部門・従業員・会社情報
    const depts = [{ id: 'd_auc', name: 'オークション代行' }, { id: 'd_sales', name: '車両販売' }];
    const emps = [
      { id: 'e_yamada', name: '山田 太郎', base: 300000, allowance: 20000, commute: 12000, dependents: 1 },
      { id: 'e_sato', name: '佐藤 花子', base: 260000, allowance: 10000, commute: 8000, dependents: 0 },
    ];
    await S.settings.save({
      name: '合同会社アイズ', invoiceRegNo: 'T1234567890123',
      address: '福島県会津若松市〇〇町1-2-3', tel: '', email: s0.email || 'info@aisjaltd.com',
      bank: '〇〇銀行 会津支店 普通 1234567 ゴウドウガイシャアイズ',
      departments: depts, employees: emps,
      budgets: { [fy.start]: { '400': 9000000, '500': 3600000, '520': 3800000, '570': 1200000, '540': 400000 } },
      recurringInvoices: [{ id: 'rc_demo', partnerName: '株式会社Aモータース', day: 25, items: [{ name: '月額オークション代行サポート', qty: 1, unitPrice: 50000, taxRate: 10 }], note: '', lastGenerated: '' }],
    });

    // 取引先
    const partners = [
      { id: 'pt_a', name: '株式会社Aモータース', kind: 'customer', regNo: 'T2000000000001' },
      { id: 'pt_b', name: 'B商会', kind: 'customer' },
      { id: 'pt_c', name: 'Cオート（仕入先）', kind: 'supplier' },
    ];
    for (const p of partners) await S.partners.save(p);

    // 固定資産＋当期減価償却
    const asset = await S.assets.save({ name: '営業車（軽トラック）', accountCode: '180', acquireDate: `${Y}-04-01`, startDate: `${Y}-04-01`, acquireCost: 1200000, usefulLife: 5, residual: 0, method: 'straight', note: 'デモ' });
    await S.journals.save({ refId: asset.id, source: 'depreciation', date: fy.end, description: `減価償却：営業車（${Y}年度）`, lines: [{ side: 'debit', account: '590', tax: 'out', amount: 240000 }, { side: 'credit', account: '180', tax: 'out', amount: 240000 }] });

    // 月次の取引（4月〜9月相当＝相対0〜5）
    const J = [];
    const jr = (date, desc, lines, dept, source) => J.push({ date, description: desc, dept: dept || '', source: source || 'manual', lines });
    for (let m = 0; m <= 5; m++) {
      const aucSale = 380000 + m * 15000;
      jr(d(m, 5), 'オークション代行手数料（現金）', [{ side: 'debit', account: '100', tax: 'out', amount: aucSale }, { side: 'credit', account: '400', tax: 'sales10', amount: aucSale }], 'd_auc');
      const carSale = 900000;
      jr(d(m, 12), '車両販売（掛）', [{ side: 'debit', account: '120', tax: 'out', amount: carSale }, { side: 'credit', account: '400', tax: 'sales10', amount: carSale }], 'd_sales');
      jr(d(m, 20), '入金（車両販売）', [{ side: 'debit', account: '110', tax: 'out', amount: carSale }, { side: 'credit', account: '120', tax: 'out', amount: carSale }], 'd_sales', 'invoice');
      jr(d(m, 8), '車両仕入（Cオート）', [{ side: 'debit', account: '500', tax: 'purchase10', amount: 520000 }, { side: 'credit', account: '110', tax: 'out', amount: 520000 }], 'd_sales');
      jr(d(m, 10), '地代家賃', [{ side: 'debit', account: '570', tax: 'purchase10', amount: 120000 }, { side: 'credit', account: '110', tax: 'out', amount: 120000 }]);
      jr(d(m, 15), 'ガソリン・旅費', [{ side: 'debit', account: '540', tax: 'purchase10', amount: 28000 }, { side: 'credit', account: '100', tax: 'out', amount: 28000 }], 'd_auc', 'expense');
      jr(d(m, 18), '通信費', [{ side: 'debit', account: '545', tax: 'purchase10', amount: 15000 }, { side: 'credit', account: '110', tax: 'out', amount: 15000 }]);
      if (m % 2 === 0) jr(d(m, 22), '広告宣伝費', [{ side: 'debit', account: '530', tax: 'purchase10', amount: 60000 }, { side: 'credit', account: '110', tax: 'out', amount: 60000 }], 'd_auc');
      if (m % 3 === 0) jr(d(m, 25), '接待交際費', [{ side: 'debit', account: '550', tax: 'purchase10', amount: 22000 }, { side: 'credit', account: '100', tax: 'out', amount: 22000 }]);
    }
    for (const j of J) await S.journals.save(j);

    // 給与（3か月分・従業員2名）＋給与仕訳
    const rates = { health: 5.0, pension: 9.15, employment: 0.6 };
    for (let m = 3; m <= 5; m++) {
      for (const e of emps) {
        const gross = e.base + e.allowance + e.commute;
        const siBase = e.base + e.allowance;
        const health = Math.floor(siBase * rates.health / 100), pension = Math.floor(siBase * rates.pension / 100), employment = Math.floor(gross * rates.employment / 100);
        const social = health + pension + employment;
        const incomeTax = A.payrolltax.monthlyWithholding(siBase - social, e.dependents);
        const deductionTotal = social + incomeTax, net = gross - deductionTotal;
        const month = d(m, 25).slice(0, 7);
        const j = await S.journals.save({ source: 'payroll', date: d(m, 25), description: `給与 ${month} ${e.name}`, lines: [{ side: 'debit', account: '520', tax: 'out', amount: gross }, { side: 'credit', account: '230', tax: 'out', amount: deductionTotal }, { side: 'credit', account: '110', tax: 'out', amount: net }] });
        await S.payslips.save({ month, employeeId: e.id, name: e.name, dependents: e.dependents, base: e.base, allowance: e.allowance, overtime: 0, commute: e.commute, gross, health, pension, employment, incomeTax, deductionTotal, net, journalId: j.id });
      }
    }

    // 請求書（売上計上済み・入金済み／未入金／見積）
    const inv1 = await S.invoices.save({ type: 'invoice', date: d(1, 25), dueDate: d(2, 25), partnerId: 'pt_a', partnerName: '株式会社Aモータース', items: [{ name: 'オークション代行手数料', qty: 3, unitPrice: 30000, taxRate: 10 }], note: '', posted: true, paid: true });
    const inv2 = await S.invoices.save({ type: 'invoice', date: d(5, 25), dueDate: d(6, 25), partnerId: 'pt_b', partnerName: 'B商会', items: [{ name: '成約手数料', qty: 1, unitPrice: 80000, taxRate: 10 }, { name: '陸送費', qty: 1, unitPrice: 20000, taxRate: 10 }], note: '', posted: true, paid: false });
    await S.invoices.save({ type: 'estimate', date: d(5, 10), partnerId: 'pt_b', partnerName: 'B商会', items: [{ name: '整備・登録代行', qty: 1, unitPrice: 150000, taxRate: 10 }], note: '' });

    // 棚卸（商品＋期末棚卸）
    const prod = await S.products.save({ name: '中古タイヤ（4本set）', unit: 'set', unitPrice: 20000, note: 'デモ' });
    const closing = 20000 * 6;
    await S.journals.save({ source: 'inventory', date: fy.end, description: `棚卸（期末棚卸高 ¥${U.yen(closing)}）`, lines: [{ side: 'debit', account: '150', tax: 'out', amount: closing }, { side: 'credit', account: '500', tax: 'out', amount: closing }] });
    await S.settings.save({ inventory: { [fy.start]: { closing, counts: { [prod.id]: 6 } } } });

    // 証憑（サンプル画像メタ）
    const px = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQAY3Y2wAAAAAElFTkSuQmCC';
    await S.attachments.save({ date: d(2, 15), amount: 28000, partner: 'ガソリンスタンド', note: '給油', filename: 'receipt_gas.png', mime: 'image/png', size: 100, dataUrl: px });
    await S.attachments.save({ date: d(3, 10), amount: 120000, partner: '〇〇不動産', note: '家賃', filename: 'invoice_rent.png', mime: 'image/png', size: 100, dataUrl: px });

    await S.settings.load(); await S.accounts.loadAll();
    // 期間を当期に
    try { A.app.setPeriod({ start: fy.start, end: fy.end }); } catch (e) {}
  };

  return { load };
})();
