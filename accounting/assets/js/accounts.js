/* =========================================================================
 * accounts.js  ―  勘定科目マスタ（初期値）・税区分の定義
 * ========================================================================= */
window.A = window.A || {};

A.accounts = (function () {
  'use strict';

  // 5要素。貸借対照表(BS)＝資産/負債/純資産、損益計算書(PL)＝収益/費用。
  // side: 増加が借方(debit)か貸方(credit)か＝通常残高の側。
  const CATEGORIES = {
    asset:     { label: '資産',   statement: 'BS', side: 'debit'  },
    liability: { label: '負債',   statement: 'BS', side: 'credit' },
    equity:    { label: '純資産', statement: 'BS', side: 'credit' },
    revenue:   { label: '収益',   statement: 'PL', side: 'credit' },
    expense:   { label: '費用',   statement: 'PL', side: 'debit'  },
  };

  // 消費税の区分。rate は税率(%)、kind は集計上の分類。
  const TAX_CATEGORIES = {
    out:         { label: '対象外',           rate: 0,  kind: 'none' },
    nontax:      { label: '非課税',           rate: 0,  kind: 'none' },
    sales10:     { label: '課税売上 10%',      rate: 10, kind: 'sales' },
    sales8:      { label: '課税売上 8%(軽減)', rate: 8,  kind: 'sales' },
    purchase10:  { label: '課税仕入 10%',      rate: 10, kind: 'purchase' },
    purchase8:   { label: '課税仕入 8%(軽減)', rate: 8,  kind: 'purchase' },
  };

  // 標準的な勘定科目（中小企業・サービス業向けの最小セット）。
  // code は 3桁で BS→PL の順に採番。default_tax は仕訳時の初期税区分。
  const DEFAULT_ACCOUNTS = [
    // 資産
    { code: '100', name: '現金',        category: 'asset', default_tax: 'out' },
    { code: '110', name: '普通預金',    category: 'asset', default_tax: 'out' },
    { code: '120', name: '売掛金',      category: 'asset', default_tax: 'out' },
    { code: '130', name: '未収入金',    category: 'asset', default_tax: 'out' },
    { code: '135', name: '前払費用',    category: 'asset', default_tax: 'out' },
    { code: '140', name: '仮払消費税',  category: 'asset', default_tax: 'out' },
    { code: '150', name: '棚卸資産',    category: 'asset', default_tax: 'out' },
    { code: '180', name: '車両運搬具',  category: 'asset', default_tax: 'out' },
    { code: '185', name: '工具器具備品',category: 'asset', default_tax: 'out' },
    // 負債
    { code: '200', name: '買掛金',      category: 'liability', default_tax: 'out' },
    { code: '210', name: '未払金',      category: 'liability', default_tax: 'out' },
    { code: '220', name: '未払費用',    category: 'liability', default_tax: 'out' },
    { code: '230', name: '預り金',      category: 'liability', default_tax: 'out' },
    { code: '240', name: '仮受消費税',  category: 'liability', default_tax: 'out' },
    { code: '250', name: '未払消費税',  category: 'liability', default_tax: 'out' },
    { code: '260', name: '短期借入金',  category: 'liability', default_tax: 'out' },
    { code: '270', name: '長期借入金',  category: 'liability', default_tax: 'out' },
    // 純資産
    { code: '300', name: '資本金',      category: 'equity', default_tax: 'out' },
    { code: '310', name: '元入金',      category: 'equity', default_tax: 'out' },
    { code: '320', name: '繰越利益剰余金', category: 'equity', default_tax: 'out' },
    // 収益
    { code: '400', name: '売上高',      category: 'revenue', default_tax: 'sales10' },
    { code: '410', name: '受取手数料',  category: 'revenue', default_tax: 'sales10' },
    { code: '491', name: '固定資産売却益', category: 'revenue', default_tax: 'out' },
    { code: '490', name: '雑収入',      category: 'revenue', default_tax: 'sales10' },
    // 費用
    { code: '500', name: '仕入高',      category: 'expense', default_tax: 'purchase10' },
    { code: '510', name: '外注費',      category: 'expense', default_tax: 'purchase10' },
    { code: '520', name: '給料手当',    category: 'expense', default_tax: 'out' },
    { code: '525', name: '法定福利費',  category: 'expense', default_tax: 'out' },
    { code: '530', name: '広告宣伝費',  category: 'expense', default_tax: 'purchase10' },
    { code: '535', name: '荷造運賃',    category: 'expense', default_tax: 'purchase10' },
    { code: '540', name: '旅費交通費',  category: 'expense', default_tax: 'purchase10' },
    { code: '545', name: '通信費',      category: 'expense', default_tax: 'purchase10' },
    { code: '550', name: '接待交際費',  category: 'expense', default_tax: 'purchase10' },
    { code: '555', name: '会議費',      category: 'expense', default_tax: 'purchase10' },
    { code: '560', name: '消耗品費',    category: 'expense', default_tax: 'purchase10' },
    { code: '565', name: '事務用品費',  category: 'expense', default_tax: 'purchase10' },
    { code: '570', name: '地代家賃',    category: 'expense', default_tax: 'purchase10' },
    { code: '575', name: '水道光熱費',  category: 'expense', default_tax: 'purchase10' },
    { code: '580', name: '支払手数料',  category: 'expense', default_tax: 'purchase10' },
    { code: '585', name: '租税公課',    category: 'expense', default_tax: 'out' },
    { code: '590', name: '減価償却費',  category: 'expense', default_tax: 'out' },
    { code: '595', name: '雑費',        category: 'expense', default_tax: 'purchase10' },
    { code: '596', name: '固定資産売却損', category: 'expense', default_tax: 'out' },
    { code: '597', name: '固定資産除却損', category: 'expense', default_tax: 'out' },
    { code: '600', name: '支払利息',    category: 'expense', default_tax: 'out' },
  ];

  return { CATEGORIES, TAX_CATEGORIES, DEFAULT_ACCOUNTS };
})();
