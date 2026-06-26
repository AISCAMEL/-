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
    const ctx = { lastBody: null };
    const srv = http.createServer((req, res) => {
      if (!req.headers.authorization) return res.writeHead(401).end('no auth');
      let raw = '';
      req.on('data', (c) => (raw += c));
      req.on('end', () => {
        let model = '';
        try {
          ctx.lastBody = JSON.parse(raw || '{}');
          model = ctx.lastBody.model || '';
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
    srv.listen(0, () => resolve({ srv, port: srv.address().port, ctx }));
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
    // CSVナレッジが system プロンプトに載って送信されているか
    // RAG: 質問に関連するFAQだけが注入され、無関係は除外される
    await collect([{ role: 'user', content: '頭金がなくても大丈夫ですか？' }]);
    let sys = (m1.ctx.lastBody.messages || []).find((x) => x.role === 'system');
    assert.ok(sys && sys.content.includes('参考ナレッジ'), 'FAQ見出しが無い');
    assert.ok(sys.content.includes('頭金'), '関連FAQが入っていない');
    assert.ok(!sys.content.includes('営業時間'), '無関係FAQが混入（RAGが効いていない）');
    ok('RAG: 質問に関連するFAQのみ注入（無関係は除外）');

    // 車種提案: 家族向けの質問でミニバンが注入される
    await collect([{ role: 'user', content: '家族で乗れる広い車はありますか？' }]);
    sys = (m1.ctx.lastBody.messages || []).find((x) => x.role === 'system');
    assert.ok(sys.content.includes('取扱車種'), '車種見出しが無い');
    assert.ok(/セレナ|ヴォクシー|フリード|ミニバン/.test(sys.content), '家族質問でミニバンが出ない');
    ok('車種提案: 家族向け質問でミニバンを注入');

    // 金融質問では車種を出さない（精度確認）
    await collect([{ role: 'user', content: '審査の流れを教えてください' }]);
    sys = (m1.ctx.lastBody.messages || []).find((x) => x.role === 'system');
    assert.ok(!sys.content.includes('取扱車種'), '無関係な質問に車種が混入');
    ok('車種提案: 車に無関係な質問では車種を出さない');
  } catch (e) {
    ng('正常ストリーミング/RAG/車種', e);
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

  // 3.5) チャットログ: 既定オフ / CHAT_LOG=1 でJSONL追記
  try {
    const fs = require('fs');
    const os = require('os');
    const path = require('path');
    delete require.cache[require.resolve('../../lib/chat-log')];
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'clog-'));
    process.env.CHAT_LOG_DIR = dir;

    delete process.env.CHAT_LOG; // 既定: 無効
    let log = require('../../lib/chat-log');
    log.appendLog({ messages: [{ role: 'user', content: 'x' }], answer: 'y', model: 'm' });
    assert.strictEqual(fs.readdirSync(dir).length, 0, '無効時に書き込んでいる');

    process.env.CHAT_LOG = '1'; // 有効化
    log.appendLog({ messages: [{ role: 'user', content: 'Q?' }], answer: 'A.', model: 'm' });
    const files = fs.readdirSync(dir).filter((f) => f.endsWith('.jsonl'));
    assert.strictEqual(files.length, 1, 'JSONLが作られていない');
    const rec = JSON.parse(fs.readFileSync(path.join(dir, files[0]), 'utf8').trim());
    assert.strictEqual(rec.question, 'Q?');
    assert.strictEqual(rec.answer, 'A.');
    delete process.env.CHAT_LOG;
    ok('チャットログ（既定オフ / CHAT_LOG=1 でJSONL追記）');
  } catch (e) {
    ng('チャットログ', e);
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
