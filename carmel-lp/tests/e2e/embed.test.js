/**
 * tests/e2e/embed.test.js
 * ------------------------------------------------------------------
 * WordPress埋め込みの配信面を検証する。
 *   - embed.js / embed.css / embed-entry.js が配信される
 *   - CORS: OPTIONSプリフライト204、APIレスポンスにAccess-Control-Allow-Origin
 *   - ALLOWED_ORIGINS で許可元を限定できる
 */
'use strict';
const assert = require('assert');
const { spawn } = require('child_process');

const failures = [];
const ok = (n) => console.log(`  ok - ${n}`);
const ng = (n, e) => {
  failures.push(n);
  console.error(`  NG - ${n}: ${(e && e.message) || e}`);
};

function startServer(env, port) {
  return spawn(process.execPath, ['server.js'], {
    cwd: process.cwd(),
    env: { ...process.env, ...env, PORT: String(port) },
    stdio: 'ignore'
  });
}
async function waitReady(port, tries = 50) {
  for (let i = 0; i < tries; i++) {
    try {
      await fetch(`http://localhost:${port}/assets/embed.js`);
      return;
    } catch (_e) {
      await new Promise((r) => setTimeout(r, 100));
    }
  }
  throw new Error(`server ${port} not ready`);
}

(async () => {
  // 全許可（ALLOWED_ORIGINS 未設定）
  const srv = startServer({}, 4631);
  try {
    await waitReady(4631);

    // 配信物
    let r = await fetch('http://localhost:4631/assets/embed.js');
    assert.strictEqual(r.status, 200);
    assert.ok((r.headers.get('content-type') || '').includes('javascript'));
    const js = await r.text();
    assert.ok(js.includes('CARMEL_CHAT'), 'embed.js の内容が不正');
    ok('embed.js が配信される（JS MIME）');

    r = await fetch('http://localhost:4631/assets/css/embed.css');
    assert.strictEqual(r.status, 200);
    assert.ok((r.headers.get('content-type') || '').includes('text/css'));
    ok('embed.css が配信される');

    r = await fetch('http://localhost:4631/assets/js/embed-entry.js');
    assert.strictEqual(r.status, 200);
    ok('embed-entry.js が配信される');

    // CORS: 静的・APIともに全許可で *
    assert.strictEqual(
      (await fetch('http://localhost:4631/assets/embed.js')).headers.get('access-control-allow-origin'),
      '*'
    );
    ok('CORS: 全許可時は Access-Control-Allow-Origin: *');

    // プリフライト
    r = await fetch('http://localhost:4631/api/chat', { method: 'OPTIONS' });
    assert.strictEqual(r.status, 204);
    assert.strictEqual(r.headers.get('access-control-allow-origin'), '*');
    assert.ok((r.headers.get('access-control-allow-methods') || '').includes('POST'));
    ok('CORS: OPTIONSプリフライトが204を返す');
  } catch (e) {
    ng('配信/CORS（全許可）', e);
  } finally {
    srv.kill();
  }

  // ALLOWED_ORIGINS で限定
  const srv2 = startServer({ ALLOWED_ORIGINS: 'https://wp.example.com' }, 4632);
  try {
    await waitReady(4632);
    // 許可オリジン → エコー
    let r = await fetch('http://localhost:4632/assets/embed.js', {
      headers: { Origin: 'https://wp.example.com' }
    });
    assert.strictEqual(r.headers.get('access-control-allow-origin'), 'https://wp.example.com');
    ok('CORS: 許可オリジンはエコーされる');

    // 非許可オリジン → * にはしない（許可リスト先頭を返す）
    r = await fetch('http://localhost:4632/assets/embed.js', {
      headers: { Origin: 'https://evil.example.com' }
    });
    assert.notStrictEqual(r.headers.get('access-control-allow-origin'), '*');
    assert.notStrictEqual(r.headers.get('access-control-allow-origin'), 'https://evil.example.com');
    ok('CORS: 非許可オリジンには * を返さない');
  } catch (e) {
    ng('配信/CORS（限定）', e);
  } finally {
    srv2.kill();
  }

  console.log(failures.length === 0 ? '\nPASS: embed checks' : `\nFAIL: ${failures.length}`);
  process.exit(failures.length === 0 ? 0 : 1);
})();
