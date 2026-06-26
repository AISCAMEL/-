/**
 * tests/e2e/admin.test.js
 * ------------------------------------------------------------------
 * 管理画面(Basic認証)とカレンダー(.ics)のE2E。
 *   - 認証なし/誤りは401、正しい認証で200
 *   - /api/admin/data が予約・ログ・よくある質問を返す
 *   - /api/admin/ics?id= がカレンダーファイルを返す
 *   - ADMIN未設定なら404（存在しない扱い）
 */
'use strict';
const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
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
      // 認証無しでも応答(401/404)が返れば起動済み
      await fetch(`http://localhost:${port}/admin`);
      return;
    } catch (_e) {
      await new Promise((r) => setTimeout(r, 100));
    }
  }
  throw new Error(`server ${port} not ready`);
}
const basic = (u, p) => 'Basic ' + Buffer.from(`${u}:${p}`).toString('base64');

(async () => {
  // 一時データを用意
  const bookDir = fs.mkdtempSync(path.join(os.tmpdir(), 'abook-'));
  const logDir = fs.mkdtempSync(path.join(os.tmpdir(), 'alog-'));
  fs.writeFileSync(
    path.join(bookDir, '2026-06-26.jsonl'),
    JSON.stringify({
      id: 'B1',
      ts: '2026-06-26T01:00:00.000Z',
      type: '来店予約',
      date: '2026-07-03',
      time: '午後（12-15時）',
      name: '田中',
      contact: '090-5555-6666',
      note: '軽自動車希望'
    }) + '\n'
  );
  fs.writeFileSync(
    path.join(logDir, '2026-06-26.jsonl'),
    [
      JSON.stringify({ ts: '2026-06-26T01:00:00Z', question: '頭金は必要ですか？', answer: 'ご相談可能です' }),
      JSON.stringify({ ts: '2026-06-26T01:05:00Z', question: '頭金は必要ですか？', answer: 'ご相談可能です' }),
      JSON.stringify({ ts: '2026-06-26T01:10:00Z', question: '軽自動車はありますか？', answer: 'ございます' })
    ].join('\n') + '\n'
  );

  const U = 'staff';
  const P = 's3cret';
  const env = { ADMIN_USER: U, ADMIN_PASS: P, BOOKINGS_DIR: bookDir, CHAT_LOG_DIR: logDir };

  const srv = startServer(env, 4621);
  try {
    await waitReady(4621);

    // 認証なし → 401
    let r = await fetch('http://localhost:4621/admin');
    assert.strictEqual(r.status, 401);
    assert.ok((r.headers.get('www-authenticate') || '').includes('Basic'));
    ok('認証なしは401（WWW-Authenticate付き）');

    // 誤った認証 → 401
    r = await fetch('http://localhost:4621/admin', { headers: { Authorization: basic(U, 'wrong') } });
    assert.strictEqual(r.status, 401);
    ok('誤った認証は401');

    // 正しい認証 → 200 HTML
    r = await fetch('http://localhost:4621/admin', { headers: { Authorization: basic(U, P) } });
    assert.strictEqual(r.status, 200);
    const html = await r.text();
    assert.ok(html.includes('管理画面'));
    ok('正しい認証でダッシュボードHTMLを返す');

    // データAPI
    r = await fetch('http://localhost:4621/api/admin/data', { headers: { Authorization: basic(U, P) } });
    const data = await r.json();
    assert.strictEqual(data.counts.bookings, 1);
    assert.strictEqual(data.counts.logs, 3);
    assert.strictEqual(data.bookings[0].name, '田中');
    assert.strictEqual(data.topQuestions[0].question, '頭金は必要ですか？');
    assert.strictEqual(data.topQuestions[0].count, 2);
    ok('data API: 予約・ログ件数・よくある質問を集計');

    // ICS
    r = await fetch('http://localhost:4621/api/admin/ics?id=B1', { headers: { Authorization: basic(U, P) } });
    assert.strictEqual(r.status, 200);
    assert.ok((r.headers.get('content-type') || '').includes('text/calendar'));
    const ics = await r.text();
    assert.ok(ics.includes('BEGIN:VCALENDAR') && ics.includes('DTSTART:20260703T120000'));
    ok('ics API: 予約のカレンダーファイルを返す');

    // 不明ID → 404
    r = await fetch('http://localhost:4621/api/admin/ics?id=NOPE', { headers: { Authorization: basic(U, P) } });
    assert.strictEqual(r.status, 404);
    ok('ics API: 不明IDは404');
  } catch (e) {
    ng('管理画面フロー', e);
  } finally {
    srv.kill();
  }

  // ADMIN未設定 → 404（存在しない扱い）
  const off = startServer({ ADMIN_USER: '', ADMIN_PASS: '' }, 4622);
  try {
    await waitReady(4622);
    const r = await fetch('http://localhost:4622/admin');
    assert.strictEqual(r.status, 404);
    ok('ADMIN未設定なら/adminは404');
  } catch (e) {
    ng('管理画面 無効化', e);
  } finally {
    off.kill();
  }

  console.log(failures.length === 0 ? '\nPASS: admin checks' : `\nFAIL: ${failures.length}`);
  process.exit(failures.length === 0 ? 0 : 1);
})();
