/**
 * tests/e2e/mock-openrouter.js
 * ------------------------------------------------------------------
 * OpenRouter の chat/completions(stream) を模した最小モックサーバー。
 * 実APIキー無しで、サーバープロキシ→SSE配線を検証するために使用する。
 *
 * 起動: node tests/e2e/mock-openrouter.js  (既定 PORT=4545)
 * 挙動: Authorization が無ければ401。あれば固定文をトークン分割でSSEストリーム。
 */
'use strict';
const http = require('http');

const PORT = process.env.MOCK_PORT || 4545;
const REPLY = 'ご相談ありがとうございます。過去に滞納歴がある方でも、まずは無料相談を承っております。詳しくはLINEまたはお電話でお気軽にどうぞ。';

const server = http.createServer((req, res) => {
  if (!req.headers.authorization) {
    res.writeHead(401).end('no auth');
    return;
  }
  let raw = '';
  req.on('data', (c) => (raw += c));
  req.on('end', () => {
    // フォールバック検証用: 指定モデルは 500 を返す
    if (process.env.MOCK_FAIL_MODEL) {
      try {
        const { model } = JSON.parse(raw || '{}');
        if (model && model.includes(process.env.MOCK_FAIL_MODEL)) {
          res.writeHead(500).end('simulated model failure');
          return;
        }
      } catch (_e) {
        /* ignore */
      }
    }
    res.writeHead(200, {
      'Content-Type': 'text/event-stream; charset=utf-8',
      'Cache-Control': 'no-cache'
    });
    // 数文字ずつ delta として送出
    const chunks = REPLY.match(/.{1,8}/gu) || [REPLY];
    let i = 0;
    const timer = setInterval(() => {
      if (i < chunks.length) {
        const payload = { choices: [{ delta: { content: chunks[i++] } }] };
        res.write(`data: ${JSON.stringify(payload)}\n\n`);
      } else {
        clearInterval(timer);
        res.write('data: [DONE]\n\n');
        res.end();
      }
    }, 10);
  });
});

server.listen(PORT, () => {
  // eslint-disable-next-line no-console
  console.log(`mock-openrouter on http://localhost:${PORT}`);
});
