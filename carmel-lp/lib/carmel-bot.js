/**
 * lib/carmel-bot.js  (CommonJS / サーバー共有)
 * ------------------------------------------------------------------
 * OpenRouter 経由でカーメル相談AIの応答をストリーミング生成する共有ロジック。
 * server.js（ローカル/Node ホスティング）と api/chat.js（サーバーレス）の両方から利用。
 *
 * モデルフォールバック方針は CarLoan_System 仕様に準拠:
 *   deepseek(無料) → gemini(無料) → claude-haiku(有料)
 * (docs/開発仕様書.md「AIモデル方針：deepseek-free → gemini-free → claude-haiku」)
 *
 * APIキーは環境変数 OPENROUTER_API_KEY から読み込む。コードには埋め込まない。
 */

'use strict';

const OPENROUTER_URL = 'https://openrouter.ai/api/v1/chat/completions';

/**
 * 利用モデルのフォールバックチェーン。
 * 正確なモデルIDは OpenRouter のモデル一覧で要確認。環境変数で上書き可能。
 * 例) OPENROUTER_MODELS="deepseek/deepseek-chat-v3-0324:free,google/gemini-2.0-flash-exp:free,anthropic/claude-3.5-haiku"
 */
function getModels() {
  const env = process.env.OPENROUTER_MODELS;
  if (env) return env.split(',').map((m) => m.trim()).filter(Boolean);
  return [
    'deepseek/deepseek-chat-v3-0324:free',
    'google/gemini-2.0-flash-exp:free',
    'anthropic/claude-3.5-haiku'
  ];
}

/**
 * カーメル相談AIの人格・ガードレール。
 * 景表法・薬機法相当の断定回避、審査結果の保証禁止、個人情報の収集回避、
 * 具体相談は有人(LINE/電話)へ誘導、という運用方針を内包する。
 * (仕様定義書 4.2 制約 / 17. プライバシー / 20. 運用引継ぎメモ)
 */
const SYSTEM_PROMPT = [
  'あなたは中古車販売「カーメル」の相談AIアシスタントです。',
  '自社ローン・信用回復ローン・車買取・自社リースに関する一次相談を、やさしく丁寧な日本語で行います。',
  '',
  '# 役割',
  '- 審査への不安（過去の滞納歴・他社審査落ち・頭金不安など）に寄り添い、安心感を与える。',
  '- 手続きの流れ、取り扱い車種、相談方法などの一般的な情報を分かりやすく説明する。',
  '- 1〜3文程度の簡潔な回答を基本とし、専門用語は避ける。',
  '',
  '# 厳守事項（ガードレール）',
  '- 審査の合否や融資可否を断定・保証しない。「ご相談ください」「可能性があります」といった表現にとどめる。',
  '- 金利・限度額・支払総額などの具体的数値を断定しない。最終条件は審査・面談によると伝える。',
  '- 誇大表現や法令（景品表示法等）に抵触しうる断定表現を避ける。',
  '- 氏名・電話番号・住所・年収などの個人情報をチャットで聞き出さない。具体的な手続きは有人窓口へ誘導する。',
  '- 専門的・個別具体的な相談、申込手続きは「LINE(無料相談)」または「お電話 050-1793-5554」を案内する。',
  '- カーメルおよびローン相談と無関係な話題は丁寧にお断りし、本題へ案内する。',
  '',
  '# トーン',
  '- 親しみやすく、押し売り感を出さない。絵文字は控えめに（多くて1つ）。'
].join('\n');

/**
 * メッセージ配列を OpenRouter 形式へ整形（systemを先頭に付与）。
 * 想定外のロールや空メッセージは除外する。
 */
function buildMessages(clientMessages) {
  const safe = (Array.isArray(clientMessages) ? clientMessages : [])
    .filter(
      (m) =>
        m &&
        (m.role === 'user' || m.role === 'assistant') &&
        typeof m.content === 'string' &&
        m.content.trim()
    )
    .map((m) => ({ role: m.role, content: String(m.content).slice(0, 2000) }))
    .slice(-12);

  return [{ role: 'system', content: SYSTEM_PROMPT }, ...safe];
}

/**
 * OpenRouter にストリーミングリクエストを送り、テキスト差分を onDelta で返す。
 * モデルは getModels() の順に試行し、最初に成功したものを使う。
 *
 * @param {Array} clientMessages クライアントからの会話履歴
 * @param {(delta:string)=>void} onDelta 差分テキストのコールバック
 * @param {Object} [opts]
 * @param {string} [opts.referer] HTTP-Referer ヘッダ
 * @returns {Promise<{model:string}>}
 */
async function streamChat(clientMessages, onDelta, opts = {}) {
  const apiKey = process.env.OPENROUTER_API_KEY;
  if (!apiKey) {
    throw new Error('OPENROUTER_API_KEY is not configured');
  }

  const messages = buildMessages(clientMessages);
  const models = getModels();
  let lastErr = null;

  for (const model of models) {
    try {
      const res = await fetch(OPENROUTER_URL, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${apiKey}`,
          'Content-Type': 'application/json',
          'HTTP-Referer': opts.referer || 'https://mycarloan.carmelonline.jp/',
          'X-Title': 'Carmel LP Chatbot'
        },
        body: JSON.stringify({
          model,
          messages,
          stream: true,
          temperature: 0.5,
          max_tokens: 600
        })
      });

      if (!res.ok || !res.body) {
        lastErr = new Error(`model ${model} -> HTTP ${res.status}`);
        continue; // 次のモデルへフォールバック
      }

      await pipeOpenRouterStream(res.body, onDelta);
      return { model };
    } catch (err) {
      lastErr = err;
      // ネットワーク等の失敗は次モデルへ
    }
  }

  throw lastErr || new Error('all models failed');
}

/**
 * OpenRouter の SSE レスポンス本文を読み、delta.content を抽出して onDelta に渡す。
 * Node18+ / Edge の Web ReadableStream を getReader で読む。
 */
async function pipeOpenRouterStream(body, onDelta) {
  const reader = body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';

  for (;;) {
    const { value, done } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });

    const lines = buffer.split('\n');
    buffer = lines.pop() || '';

    for (const line of lines) {
      const t = line.trim();
      if (!t.startsWith('data:')) continue;
      const data = t.slice(5).trim();
      if (data === '[DONE]') return;
      try {
        const json = JSON.parse(data);
        const delta = json.choices && json.choices[0] && json.choices[0].delta;
        if (delta && delta.content) onDelta(delta.content);
      } catch (_e) {
        /* keep-alive コメント等は無視 */
      }
    }
  }
}

module.exports = { streamChat, buildMessages, getModels, SYSTEM_PROMPT };
