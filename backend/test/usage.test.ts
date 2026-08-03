import { test } from 'node:test';
import assert from 'node:assert/strict';
import { getUsageSummary } from '../src/db/queries.js';
import { summarizeCall } from '../src/ai/summarize.js';
import { config } from '../src/config.js';

// DB未接続（デモモード）での集計を検証。
test('getUsageSummary: デモテナントの当月集計', async () => {
  const s = await getUsageSummary(config.demoTenantId);
  assert.equal(s.plan.key, 'business');
  // デモ通話3件: 92s,64s,28s → 2+2+1 = 5分
  assert.equal(s.billable_minutes, 5);
  assert.equal(s.revenue_jpy, 7980 + 5 * 40); // 営業プラン: 基本料7980＋通話5分×¥40（1分目から）
  assert.equal(s.cost.total_jpy, 63.16);
  assert.ok(s.margin_rate > 99 && s.margin_rate <= 100);
});

test('summarizeCall: LLM未設定時はフォールバック要約', async () => {
  const s = await summarizeCall([
    { speaker: 'ai', message: 'ご用件をどうぞ' },
    { speaker: 'customer', message: '予約したい' },
  ]);
  assert.equal(s.category, 'other');     // 推測しない
  assert.equal(s.customer_name, null);
  assert.ok(typeof s.summary === 'string');
});
