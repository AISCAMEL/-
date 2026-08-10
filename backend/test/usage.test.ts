import { test } from 'node:test';
import assert from 'node:assert/strict';
import { getUsageSummary } from '../src/db/queries.js';
import { summarizeCall } from '../src/ai/summarize.js';
import { config } from '../src/config.js';

// DB未接続（デモモード）での集計を検証。
// デモには当日の詳細通話3件＋直近2週間の履歴が含まれ、当月件数・分数は実行日により変動する。
// そのため固定値ではなく「必ず成り立つ下限」と「収益＝基本料＋着信×単価＋従量分×単価の内部整合」で検証する。
test('getUsageSummary: デモテナントの当月集計', async () => {
  const s = await getUsageSummary(config.demoTenantId);
  assert.equal(s.plan.key, 'business');
  // 当日の詳細3件（96s,68s,40s → 2+2+1）は必ず当月に含まれる
  assert.ok(s.calls >= 3, `calls=${s.calls}`);
  assert.ok(s.billable_minutes >= 5, `billable=${s.billable_minutes}`);
  // 営業プラン: 基本料6980 ＋ 着信×¥30 ＋ 従量分×¥25（無料分なし）で内部整合していること
  assert.equal(s.revenue_jpy, 6980 + s.calls * 30 + s.billable_minutes * 25);
  assert.ok(s.cost.total_jpy > 0);
  assert.ok(s.margin_rate > 90 && s.margin_rate <= 100);
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
