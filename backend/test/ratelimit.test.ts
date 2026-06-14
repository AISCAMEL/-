import { test } from 'node:test';
import assert from 'node:assert/strict';
import { rateLimit } from '../src/util/ratelimit.js';

test('rateLimit: max回まで許可しその後ブロック', () => {
  const key = `t-${Math.random()}`;
  for (let i = 0; i < 3; i++) {
    assert.equal(rateLimit(key, 3, 60_000).allowed, true);
  }
  const blocked = rateLimit(key, 3, 60_000);
  assert.equal(blocked.allowed, false);
  assert.ok(blocked.retryAfter > 0);
});

test('rateLimit: ウィンドウ経過でリセット', async () => {
  const key = `t-${Math.random()}`;
  assert.equal(rateLimit(key, 1, 30).allowed, true);
  assert.equal(rateLimit(key, 1, 30).allowed, false);
  await new Promise((r) => setTimeout(r, 40));
  assert.equal(rateLimit(key, 1, 30).allowed, true);
});

test('rateLimit: キーごとに独立', () => {
  assert.equal(rateLimit('a', 1, 60_000).allowed, true);
  assert.equal(rateLimit('b', 1, 60_000).allowed, true);
});
