/**
 * api/chat.js  (Vercel / Node サーバーレス関数)
 * ------------------------------------------------------------------
 * POST /api/chat  body: { messages: [{role,content}, ...] }
 * OpenRouter へプロキシし、SSE でテキスト差分をストリーミングする。
 *
 * 環境変数:
 *   OPENROUTER_API_KEY  … 必須。OpenRouter の API キー。
 *   OPENROUTER_MODELS   … 任意。モデルフォールバックチェーン（カンマ区切り）。
 *
 * Netlify Functions 等へ移植する場合もロジックは lib/carmel-bot.js を共有する。
 */

'use strict';

const { streamChat } = require('../lib/carmel-bot');
const { appendLog } = require('../lib/chat-log');

module.exports = async function handler(req, res) {
  if (req.method !== 'POST') {
    res.statusCode = 405;
    res.end('Method Not Allowed');
    return;
  }

  res.writeHead(200, {
    'Content-Type': 'text/event-stream; charset=utf-8',
    'Cache-Control': 'no-cache, no-transform',
    Connection: 'keep-alive'
  });

  try {
    // req.body はフレームワークによりパース済みの場合と未パースの場合がある
    const body = req.body && typeof req.body === 'object' ? req.body : await readJson(req);
    const messages = (body && body.messages) || [];

    let answer = '';
    const { model } = await streamChat(
      messages,
      (delta) => {
        answer += delta;
        res.write(`data: ${JSON.stringify({ delta })}\n\n`);
      },
      { referer: req.headers && req.headers.referer }
    );
    res.write('data: [DONE]\n\n');
    res.end();
    // CHAT_LOG=1 のとき記録（サーバーレスではFS揮発に注意。CHAT_LOG_DIRで永続先指定推奨）
    appendLog({ messages, answer, model });
  } catch (err) {
    res.write(`data: ${JSON.stringify({ error: (err && err.message) || 'chat failed' })}\n\n`);
    res.end();
  }
};

function readJson(req) {
  return new Promise((resolve) => {
    let raw = '';
    req.on('data', (c) => {
      raw += c;
    });
    req.on('end', () => {
      try {
        resolve(JSON.parse(raw || '{}'));
      } catch (_e) {
        resolve({});
      }
    });
    req.on('error', () => resolve({}));
  });
}
