/**
 * カーメル LINEボット AI応答エンドポイント（Google Apps Script 雛形）
 *
 * Carmel_LINE_Bot が `carmel_line_ai_endpoint` に POST する想定。
 *   受信： {"message":"ユーザー発話","user_id":"Uxxxx","context":"carmel-line-bot"}
 *   返却： {"reply":"AIの返答テキスト"}
 *
 * 使い方：
 *   1) script.google.com で新規プロジェクト → 本コードを貼り付け
 *   2) プロジェクトの設定 → スクリプト プロパティに API_KEY を登録
 *   3) デプロイ → 新しいデプロイ → 種類「ウェブアプリ」
 *        実行ユーザー：自分 / アクセス：全員
 *   4) 発行された /exec URL を WP の option `carmel_line_ai_endpoint` に設定
 *
 * 既定は Anthropic Claude Messages API を呼ぶ例。OpenAI / Gemini に差し替え可
 * （callLLM_ を書き換えるだけ）。LLM 呼び出しに失敗したら空 reply を返し、
 * WP 側は組み込みFAQ／既定案内にフォールバックする。
 */

// 返答に使うモデル（公開モデルIDを設定。任意で変更可）
var MODEL = 'claude-3-5-haiku-latest';

// カーメルの人格・回答方針（必要に応じて編集）
var SYSTEM_PROMPT =
  'あなたは中古車サービス「カーメル」のLINE応対AIです。' +
  'ローン販売・車買取・自社リースを扱います。' +
  '日本語で簡潔（最大3文程度）に、丁寧かつ親しみやすく答えてください。' +
  '与信審査の可否やローンの最終条件は断定せず「審査により決定」と案内します。' +
  '価格・在庫・見積りは個別案内が必要なので、審査申込フォームやお問い合わせへ自然に誘導します。' +
  '分からないこと・個人情報が必要なことは、担当者から連絡する旨を伝えます。';

function doPost(e) {
  var out = { reply: '' };
  try {
    var body = JSON.parse((e && e.postData && e.postData.contents) || '{}');
    var message = (body.message || '').toString().slice(0, 2000);
    if (message) {
      out.reply = (callLLM_(message) || '').toString().slice(0, 1000);
    }
  } catch (err) {
    // 失敗時は空 reply（WP側でFAQ/既定にフォールバック）
    out.reply = '';
  }
  return ContentService
    .createTextOutput(JSON.stringify(out))
    .setMimeType(ContentService.MimeType.JSON);
}

/** Anthropic Claude Messages API を呼んで返答テキストを得る。 */
function callLLM_(message) {
  var apiKey = PropertiesService.getScriptProperties().getProperty('API_KEY');
  if (!apiKey) return '';

  var res = UrlFetchApp.fetch('https://api.anthropic.com/v1/messages', {
    method: 'post',
    contentType: 'application/json',
    muteHttpExceptions: true,
    headers: {
      'x-api-key': apiKey,
      'anthropic-version': '2023-06-01'
    },
    payload: JSON.stringify({
      model: MODEL,
      max_tokens: 300,
      system: SYSTEM_PROMPT,
      messages: [{ role: 'user', content: message }]
    })
  });

  if (res.getResponseCode() >= 300) return '';
  var data = JSON.parse(res.getContentText() || '{}');
  // Claude のテキストは content[0].text
  if (data && data.content && data.content[0] && data.content[0].text) {
    return data.content[0].text;
  }
  return '';
}

/* ------------------------------------------------------------------ *
 * 参考：OpenAI に差し替える場合の callLLM_（コメントアウト例）
 * ------------------------------------------------------------------ *
function callLLM_(message) {
  var apiKey = PropertiesService.getScriptProperties().getProperty('API_KEY');
  if (!apiKey) return '';
  var res = UrlFetchApp.fetch('https://api.openai.com/v1/chat/completions', {
    method: 'post', contentType: 'application/json', muteHttpExceptions: true,
    headers: { 'Authorization': 'Bearer ' + apiKey },
    payload: JSON.stringify({
      model: 'gpt-4o-mini', max_tokens: 300,
      messages: [
        { role: 'system', content: SYSTEM_PROMPT },
        { role: 'user', content: message }
      ]
    })
  });
  if (res.getResponseCode() >= 300) return '';
  var d = JSON.parse(res.getContentText() || '{}');
  return (d.choices && d.choices[0] && d.choices[0].message && d.choices[0].message.content) || '';
}
*/
