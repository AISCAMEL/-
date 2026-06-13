/**
 * AiExtractor.gs — AI（OpenRouter経由）で請求書から項目を構造化抽出
 * §2 のプロンプト・§7-4 のJSONスキーマに対応。
 *
 * 2段構え：一次モデルで抽出 → 機械検証NGなら高精度モデルで再抽出。
 */

/**
 * 抽出を実行。payload は extractPayload_() の戻り値。
 * vendorHint は送信元から特定した業者（あれば精度向上に使う）。
 * @return {Object} JSONスキーマに沿った抽出結果（_model等のメタ付き）
 */
function aiExtract_(payload, vendorHint) {
  const primary = getProp_(CONFIG.PROP.AI_MODEL_PRIMARY, true);
  const fallback = getProp_(CONFIG.PROP.AI_MODEL_FALLBACK, false);

  let result = callModel_(primary, payload, vendorHint);
  result._model = primary;

  // 一次抽出が機械検証でNGなら、高精度モデルで再挑戦
  if (fallback && validateExtraction_(result).length > 0) {
    try {
      const retry = callModel_(fallback, payload, vendorHint);
      if (validateExtraction_(retry).length < validateExtraction_(result).length) {
        retry._model = fallback;
        result = retry;
      }
    } catch (e) {
      Logger.log('フォールバック失敗: ' + e);
    }
  }
  return result;
}

function callModel_(model, payload, vendorHint) {
  const content = buildContent_(payload, vendorHint);
  const body = {
    model: model,
    messages: [
      { role: 'system', content: SYSTEM_PROMPT_ },
      { role: 'user', content: content },
    ],
    temperature: 0,
    response_format: { type: 'json_object' },
  };
  const text = withRetry_(function () {
    const res = UrlFetchApp.fetch('https://openrouter.ai/api/v1/chat/completions', {
      method: 'post',
      contentType: 'application/json',
      headers: { Authorization: 'Bearer ' + getProp_(CONFIG.PROP.OPENROUTER_API_KEY, true) },
      payload: JSON.stringify(body),
      muteHttpExceptions: true,
    });
    const code = res.getResponseCode();
    if (code !== 200) throw new Error('AI APIエラー ' + code + ': ' + res.getContentText().slice(0, 300));
    const json = JSON.parse(res.getContentText());
    return json.choices[0].message.content;
  }, CONFIG.AI_MAX_RETRY, 'aiExtract');

  const parsed = parseJsonLoose_(text);
  return normalizeExtraction_(parsed);
}

/** AIに渡すcontent配列を構築（添付があれば画像/PDF、無ければ本文テキスト）。 */
function buildContent_(payload, vendorHint) {
  const hint = '【送信元】' + payload.from + '\n' +
    (vendorHint ? '【マスタ上の推定業者名】' + vendorHint.norm + '（不一致なら請求書の記載を優先）\n' : '') +
    '【本日】' + Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy-MM-dd') + '\n' +
    '上記を参考に、以下の請求書から指定JSONを抽出してください。';

  const arr = [{ type: 'text', text: hint }];

  if (payload.files && payload.files.length) {
    payload.files.forEach(function (f) {
      const ct = f.getContentType();
      const b64 = Utilities.base64Encode(f.getBytes());
      if (/pdf/i.test(ct)) {
        // OpenRouterのファイル添付（PDF）。対応はモデル/プラグイン依存。
        arr.push({ type: 'file', file: { filename: f.getName(), file_data: 'data:application/pdf;base64,' + b64 } });
      } else {
        arr.push({ type: 'image_url', image_url: { url: 'data:' + ct + ';base64,' + b64 } });
      }
    });
  } else {
    arr.push({ type: 'text', text: '【請求書本文】\n' + (payload.bodyText || '').slice(0, 8000) });
  }
  return arr;
}

/** 抽出結果を型・表記の面で正規化（金額→整数、日付→YYYY-MM-DD）。 */
function normalizeExtraction_(p) {
  const tax10 = toAmount_(p.tax_10), tax8 = toAmount_(p.tax_8);
  let taxTotal = toAmount_(p.tax_total);
  if (taxTotal == null && (tax10 != null || tax8 != null)) taxTotal = (tax10 || 0) + (tax8 || 0);
  return {
    vendor_name: p.vendor_name || null,
    vendor_registration_no: p.vendor_registration_no || null,
    invoice_number: p.invoice_number || null,
    invoice_date: toDate_(p.invoice_date),
    due_date: toDate_(p.due_date),
    subtotal: toAmount_(p.subtotal),
    tax_10: tax10, tax_8: tax8, tax_total: taxTotal,
    total: toAmount_(p.total),
    withholding_tax: toAmount_(p.withholding_tax) || 0,
    currency: p.currency || 'JPY',
    line_items: Array.isArray(p.line_items) ? p.line_items : [],
    description: p.description || null,
    fields_found: Array.isArray(p.fields_found) ? p.fields_found : [],
    confidence: typeof p.confidence === 'number' ? p.confidence : null,
    ambiguous_notes: p.ambiguous_notes || null,
  };
}

const SYSTEM_PROMPT_ =
  'あなたは日本の請求書を読み取る経理アシスタントです。' +
  '渡された請求書（PDF/画像/テキスト）から指定項目を抽出し、JSONのみを返します。' +
  '推測で値を埋めないこと。読み取れない項目は null にすること。' +
  '金額はカンマ・¥・円を除いた半角整数で返すこと。' +
  '日付は西暦 YYYY-MM-DD に変換すること（和暦は西暦へ換算）。' +
  '実際に紙面で見つけた項目名だけを fields_found 配列に入れること。' +
  '余計な説明文・マークダウンは一切出力しないこと。' +
  '返すJSONのキーは次の通り: ' +
  'vendor_name, vendor_registration_no, invoice_number, invoice_date, due_date, ' +
  'subtotal, tax_10, tax_8, tax_total, total, withholding_tax, currency, ' +
  'line_items(name,amount), description, fields_found, confidence, ambiguous_notes';
