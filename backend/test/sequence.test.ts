import { test } from 'node:test';
import assert from 'node:assert/strict';
import { DEFAULT_SEQUENCE, renderTemplate } from '../src/leads/sequence.js';

test('renderTemplate: name/company を差し込む', () => {
  const out = renderTemplate('{{name}} 様 / {{company}}', { name: '山田太郎', company: '山田商店' });
  assert.equal(out, '山田太郎 様 / 山田商店');
});

test('renderTemplate: name未指定は「ご担当者」', () => {
  const out = renderTemplate('{{name}} 様', { name: null, company: null });
  assert.equal(out, 'ご担当者 様');
});

test('DEFAULT_SEQUENCE: 4ステップ・遅延は単調増加', () => {
  assert.equal(DEFAULT_SEQUENCE.length, 4);
  for (let i = 1; i < DEFAULT_SEQUENCE.length; i++) {
    assert.ok(DEFAULT_SEQUENCE[i].delayHours > DEFAULT_SEQUENCE[i - 1].delayHours);
  }
  assert.equal(DEFAULT_SEQUENCE[0].delayHours, 0); // 即時お礼
});
