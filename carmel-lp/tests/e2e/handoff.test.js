/**
 * tests/e2e/handoff.test.js
 * ------------------------------------------------------------------
 * 有人ハイブリッド対応(Slack連携)のE2E。実トークン不要。
 * mock-slack を立て、server.js の /api/handoff/* を通しで検証する。
 *
 *  検証:
 *   - status: enabled / withinHours
 *   - start -> Slackへ親メッセージ投稿、sessionId 取得
 *   - send  -> スレッドへお客様メッセージ中継
 *   - poll  -> 担当者(人間)の返信のみ取得、ボット投稿は除外
 *   - callback -> 後日連絡をSlackへ通知
 *   - off-hours / disabled の分岐
 */
'use strict';
const assert = require('assert');
const { spawn } = require('child_process');

const SLACK_PORT = 4600;
process.env.MOCK_SLACK_PORT = String(SLACK_PORT);
require('./mock-slack'); // 同一プロセスでモックSlackを起動

const failures = [];
const ok = (n) => console.log(`  ok - ${n}`);
const ng = (n, e) => {
  failures.push(n);
  console.error(`  NG - ${n}: ${(e && e.message) || e}`);
};

function startServer(env, port) {
  const child = spawn(process.execPath, ['server.js'], {
    cwd: process.cwd(),
    env: { ...process.env, ...env, PORT: String(port) },
    stdio: 'ignore'
  });
  return child;
}

async function waitReady(port, tries = 50) {
  for (let i = 0; i < tries; i++) {
    try {
      const r = await fetch(`http://localhost:${port}/api/handoff/status`);
      if (r.ok) return;
    } catch (_e) {
      /* retry */
    }
    await new Promise((r) => setTimeout(r, 100));
  }
  throw new Error(`server on ${port} not ready`);
}

const j = (port, path, body) =>
  fetch(`http://localhost:${port}${path}`, {
    method: body ? 'POST' : 'GET',
    headers: body ? { 'Content-Type': 'application/json' } : undefined,
    body: body ? JSON.stringify(body) : undefined
  }).then((r) => r.json());

const inject = (text) =>
  fetch(`http://localhost:${SLACK_PORT}/__inject`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ text })
  });

(async () => {
  const slackEnv = {
    SLACK_BOT_TOKEN: 'xoxb-test',
    SLACK_CHANNEL: 'C_TEST',
    SLACK_BASE_URL: `http://localhost:${SLACK_PORT}`,
    HANDOFF_TIMEOUT_MS: '20000'
  };

  // ---- A) 営業時間内 + Slack有効 ----
  const openSrv = startServer(
    { ...slackEnv, BUSINESS_HOURS_START: '0', BUSINESS_HOURS_END: '24' },
    4611
  );
  try {
    await waitReady(4611);

    const st = await j(4611, '/api/handoff/status');
    assert.strictEqual(st.enabled, true);
    assert.strictEqual(st.withinHours, true);
    ok('status: enabled かつ withinHours=true');

    const started = await j(4611, '/api/handoff/start', { question: '頭金0円で相談したい' });
    assert.strictEqual(started.available, true);
    assert.ok(started.sessionId, 'sessionId が無い');
    ok('start: Slackへ通知しsessionId取得');

    // 開始直後は担当者返信なし
    let p = await j(4611, `/api/handoff/poll?sessionId=${started.sessionId}`);
    assert.strictEqual((p.messages || []).length, 0);

    // お客様メッセージ中継（エラーにならないこと）
    const sent = await j(4611, '/api/handoff/send', {
      sessionId: started.sessionId,
      text: '審査が不安です'
    });
    assert.strictEqual(sent.ok, true);
    ok('send: お客様メッセージをスレッドへ中継');

    // 担当者(人間)の返信を差し込む → pollで取得できる
    await inject('お任せください。頭金なしでもご相談可能です。');
    p = await j(4611, `/api/handoff/poll?sessionId=${started.sessionId}`);
    const texts = (p.messages || []).map((m) => m.text);
    assert.ok(texts.some((t) => t.includes('頭金なしでもご相談可能')), '担当者返信が取得できない');
    // ボット投稿(親/お客様中継)は混ざらない
    assert.ok(!texts.some((t) => t.includes('🙋 お客様')), 'ボット投稿が混入している');
    ok('poll: 担当者の返信のみ取得（ボット投稿は除外）');

    // 取得済みは再取得されない
    p = await j(4611, `/api/handoff/poll?sessionId=${started.sessionId}`);
    assert.strictEqual((p.messages || []).length, 0);
    ok('poll: 既読分は重複取得しない');

    // 後日連絡
    const cb = await j(4611, '/api/handoff/callback', {
      name: '山田',
      contact: '090-0000-0000',
      message: '折り返し希望'
    });
    assert.strictEqual(cb.ok, true);
    ok('callback: 後日連絡をSlackへ通知');
  } catch (e) {
    ng('営業時間内フロー', e);
  } finally {
    openSrv.kill();
  }

  // ---- B) 営業時間外（start が available:false / off-hours） ----
  const closedSrv = startServer(
    { ...slackEnv, BUSINESS_HOURS_START: '0', BUSINESS_HOURS_END: '0' },
    4612
  );
  try {
    await waitReady(4612);
    const st = await j(4612, '/api/handoff/status');
    assert.strictEqual(st.withinHours, false);
    const started = await j(4612, '/api/handoff/start', { question: 'x' });
    assert.strictEqual(started.available, false);
    assert.strictEqual(started.reason, 'off-hours');
    ok('off-hours: start が available:false (reason=off-hours)');
  } catch (e) {
    ng('営業時間外フロー', e);
  } finally {
    closedSrv.kill();
  }

  // ---- C) Slack未設定（disabled） ----
  const offSrv = startServer(
    { SLACK_BOT_TOKEN: '', SLACK_CHANNEL: '', SLACK_BASE_URL: '' },
    4613
  );
  try {
    await waitReady(4613);
    const st = await j(4613, '/api/handoff/status');
    assert.strictEqual(st.enabled, false);
    const started = await j(4613, '/api/handoff/start', { question: 'x' });
    assert.strictEqual(started.available, false);
    assert.strictEqual(started.reason, 'disabled');
    ok('disabled: Slack未設定なら available:false (reason=disabled)');
  } catch (e) {
    ng('未設定フロー', e);
  } finally {
    offSrv.kill();
  }

  console.log(failures.length === 0 ? '\nPASS: handoff checks' : `\nFAIL: ${failures.length}`);
  process.exit(failures.length === 0 ? 0 : 1);
})();
