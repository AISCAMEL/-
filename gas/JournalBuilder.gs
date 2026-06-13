/**
 * JournalBuilder.gs — 業者マスタ＋仕訳パターン＋抽出金額から「仕訳明細」を組み立てる
 * templates/仕訳パターン明細.csv の定義を読み、{プレースホルダ}を実値に置換する。
 *
 * 出力は MfExporter がCSV化するための中間表現（借方/貸方の行配列）。
 */
const PATTERN_COL = { ID: 0, NAME: 1, LINE: 2, SIDE: 3, ACCOUNT: 4, SUB: 5, TAX: 6, RULE: 7 };

/** 仕訳パターン明細を {パターンID: [行,...]} の形で読み込む。 */
function loadPatterns_() {
  const ss = SpreadsheetApp.openById(getProp_(CONFIG.PROP.SPREADSHEET_ID, true));
  const sh = ss.getSheetByName(CONFIG.SHEET.PATTERN);
  const values = sh.getDataRange().getValues();
  const map = {};
  for (let i = 1; i < values.length; i++) {
    const r = values[i];
    const id = r[PATTERN_COL.ID];
    if (!id) continue;
    if (!map[id]) map[id] = [];
    map[id].push({
      line: r[PATTERN_COL.LINE], side: r[PATTERN_COL.SIDE], account: r[PATTERN_COL.ACCOUNT],
      sub: r[PATTERN_COL.SUB], tax: r[PATTERN_COL.TAX], rule: String(r[PATTERN_COL.RULE] || ''),
    });
  }
  return map;
}

/**
 * 仕訳明細を生成。
 * @param {Object} ext 抽出結果（normalizeExtraction_ 済み）
 * @param {Object} vendor 業者マスタ行（null可）
 * @param {Object} patterns loadPatterns_ の結果
 * @return {Array} [{side,account,sub,tax,amount}, ...]（借方合計=貸方合計になる想定）
 */
function buildJournal_(ext, vendor, patterns) {
  const patternId = (vendor && vendor.pattern) || defaultPatternId_();
  const tmpl = patterns[patternId];
  if (!tmpl) return []; // パターン未定義 → 仕訳は後で手動

  const ctx = {
    drAccount: (vendor && vendor.drAccount) || ext._drAccount || '',
    crAccount: (vendor && vendor.crAccount) || '未払金',
    drSub: (vendor && vendor.drSub) || '',
    taxClass: (vendor && vendor.taxClass) || '課税仕入10%',
    normVendor: ext._normVendor || ext.vendor_name || '',
    subtotal: ext.subtotal || 0,
    tax: ext.tax_total || 0,
    tax10: ext.tax_10 || 0,
    tax8: ext.tax_8 || 0,
    total: ext.total || 0,
    gensen: ext.withholding_tax || 0,
  };

  return tmpl.map(function (line) {
    return {
      side: line.side,
      account: resolveToken_(line.account, ctx),
      sub: resolveToken_(line.sub, ctx),
      tax: resolveToken_(line.tax, ctx),
      amount: resolveAmount_(line.rule, ctx),
    };
  }).filter(function (l) { return l.amount && l.amount > 0; });
}

/** 既定パターン（税抜/税込の運用設定に従う）。 */
function defaultPatternId_() {
  const mode = getProp_(CONFIG.PROP.TAX_INPUT_MODE, false) || 'NUKI';
  return mode === 'KOMI' ? 'STD_KAZEI_KOMI' : 'STD_KAZEI_NUKI';
}

/** {業者.既定借方勘定科目} などのプレースホルダを実値へ。 */
function resolveToken_(token, ctx) {
  if (token == null) return '';
  let s = String(token);
  s = s.replace('{業者.既定借方勘定科目}', ctx.drAccount)
       .replace('{業者.既定貸方勘定科目}', ctx.crAccount)
       .replace('{業者.既定税区分}', ctx.taxClass)
       .replace('{業者.正規化業者名}', ctx.normVendor);
  return s;
}

/** 金額ルール文字列 → 数値。日本語ルールを解釈する。 */
function resolveAmount_(rule, ctx) {
  if (!rule) return 0;
  if (/税込合計から源泉税を引いた額|税込合計-源泉税|差引/.test(rule)) return ctx.total - ctx.gensen;
  if (/税込合計|税込/.test(rule)) return ctx.total;
  if (/税抜金額|税抜/.test(rule)) return ctx.subtotal;
  if (/10%消費税|10%対象の税抜|消費税10/.test(rule)) return /税抜/.test(rule) ? ctx.subtotal : ctx.tax10;
  if (/8%消費税|8%対象の税抜/.test(rule)) return /税抜/.test(rule) ? 0 : ctx.tax8;
  if (/税額|消費税/.test(rule)) return ctx.tax;
  if (/源泉税/.test(rule)) return ctx.gensen;
  const n = toAmount_(rule);
  return n || 0;
}
