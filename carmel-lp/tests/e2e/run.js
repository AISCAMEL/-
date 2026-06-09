/**
 * tests/e2e/run.js
 * ------------------------------------------------------------------
 * 実APIキー不要の自動E2E。OpenRouter互換モックを立て、lib/carmel-bot.js の
 * streamChat を検証する（差分連結・モデルフォールバック・全失敗時エラー）。
 *
 * 実行: npm test  (または node tests/e2e/run.js)
 */
'use strict';
const http = require('http');
const assert = require('assert');

const REPLY = 'ご相談ありがとうございます。まずは無料相談を承ります。';

/** 指定モデルを500にできるモックOpenRouterを起動 */
function startMock(failSubstr) {
  return new Promise((resolve) => {
    const srv = http.createServer((req, res) => {
      if (!req.headers.authorization) return res.writeHead(401).end('no auth');
      let raw = '';
      req.on('data', (c) => (raw += c));
      req.on('end', () => {
        let model = '';
        try {
          model = JSON.parse(raw || '{}').model || '';
        } catch (_e) {}
        if (failSubstr && model.includes(failSubstr)) {
          return res.writeHead(500).end('fail');
        }
        res.writeHead(200, { 'Content-Type': 'text/event-stream' });
        const chunks = REPLY.match(/.{1,6}/gu);
        chunks.forEach((c) =>
          res.write(`data: ${JSON.stringify({ choices: [{ delta: { content: c } }] })}\n\n`)
        );
        res.write('data: [DONE]\n\n');
        res.end();
      });
    });
    srv.listen(0, () => resolve({ srv, port: srv.address().port }));
  });
}

async function collect(messages) {
  const { streamChat } = require('../../lib/carmel-bot');
  let out = '';
  const r = await streamChat(messages, (d) => (out += d), {});
  return { text: out, model: r.model };
}

(async () => {
  process.env.OPENROUTER_API_KEY = 'test-key';
  process.env.OPENROUTER_MODELS =
    'deepseek/deepseek-chat-v3-0324:free,google/gemini-2.0-flash-exp:free';
  const msg = [{ role: 'user', content: 'こんにちは' }];
  let failures = 0;
  const ok = (name) => console.log(`  ok - ${name}`);
  const ng = (name, e) => {
    failures++;
    console.error(`  NG - ${name}: ${e && e.message}`);
  };

  // 1) 正常系: 差分が連結されて全文になる
  let m1 = await startMock(null);
  process.env.OPENROUTER_BASE_URL = `http://localhost:${m1.port}`;
  try {
    const { text, model } = await collect(msg);
    assert.strictEqual(text, REPLY);
    assert.ok(model.includes('deepseek'));
    ok('正常ストリーミング（差分連結 = 全文 / 先頭モデル使用）');
  } catch (e) {
    ng('正常ストリーミング', e);
  } finally {
    m1.srv.close();
  }

  // 2) フォールバック: 先頭(deepseek)が500 → 2番目(gemini)で成功
  let m2 = await startMock('deepseek');
  process.env.OPENROUTER_BASE_URL = `http://localhost:${m2.port}`;
  try {
    const { text, model } = await collect(msg);
    assert.strictEqual(text, REPLY);
    assert.ok(model.includes('gemini'));
    ok('モデルフォールバック（deepseek失敗 → geminiで応答）');
  } catch (e) {
    ng('モデルフォールバック', e);
  } finally {
    m2.srv.close();
  }

  // 3) 全失敗: throw する（クライアントはLINE/電話へフォールバック）
  let m3 = await startMock(':free');
  process.env.OPENROUTER_BASE_URL = `http://localhost:${m3.port}`;
  try {
    await collect(msg);
    ng('全失敗時エラー', new Error('throwされなかった'));
  } catch (_e) {
    ok('全モデル失敗時にエラーを投げる');
  } finally {
    m3.srv.close();
  }

  // 4) APIキー未設定: 明確なエラー
  delete process.env.OPENROUTER_API_KEY;
  try {
    await collect(msg);
    ng('キー未設定エラー', new Error('throwされなかった'));
  } catch (e) {
    assert.ok(/API_KEY/.test(e.message));
    ok('APIキー未設定で明確にエラー');
  }

  console.log(failures === 0 ? '\nPASS: all checks' : `\nFAIL: ${failures} check(s)`);
  process.exit(failures === 0 ? 0 : 1);
})();
