import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  billableMinutes, aiCostJpy, monthlyRevenueJpy, planDef,
  AI_COST_USD_PER_MIN, USD_JPY, PLANS,
} from '../src/billing/rates.js';

test('billableMinutes: 切り上げ・最低0', () => {
  assert.equal(billableMinutes(0), 0);
  assert.equal(billableMinutes(null), 0);
  assert.equal(billableMinutes(1), 1);
  assert.equal(billableMinutes(60), 1);
  assert.equal(billableMinutes(61), 2);
  assert.equal(billableMinutes(92), 2);
});

test('AI原価/分 ≈ ¥12.63 (為替155前提)', () => {
  // relay 0.07 + inbound 0.0085 + llm 0.003 = 0.0815 USD/min
  assert.ok(Math.abs(AI_COST_USD_PER_MIN - 0.0815) < 1e-9);
  assert.equal(USD_JPY, 155);
  assert.equal(aiCostJpy(1), 12.63);
  assert.equal(aiCostJpy(5), 63.16);
});

test('monthlyRevenueJpy: 基本料＋1分目からの従量（無料通話分なし）', () => {
  const biz = PLANS.business; // 低額基本料＋全通話従量モデル
  assert.equal(biz.allowanceMin, 0);                                       // 無料通話分は0
  assert.equal(monthlyRevenueJpy(biz, 0), biz.baseJpy);                    // 通話0なら基本料のみ
  assert.equal(monthlyRevenueJpy(biz, 100), biz.baseJpy + 100 * biz.overageJpyPerMin); // 1分目から従量
});

test('planDef: 未知プランは starter にフォールバック', () => {
  assert.equal(planDef('pro').label, PLANS.pro.label);
  assert.equal(planDef('unknown').label, PLANS.starter.label);
  assert.equal(planDef(null).label, PLANS.starter.label);
});

test('planHasFeature: プランごとに機能範囲が異なる', async () => {
  const { planHasFeature } = await import('../src/billing/rates.js');
  // 受付プランはインバウンド機能のみ
  assert.equal(planHasFeature('starter', 'reception'), true);
  assert.equal(planHasFeature('starter', 'appointment'), true);
  assert.equal(planHasFeature('starter', 'outbound'), false);   // AI架電は営業プラン以上
  assert.equal(planHasFeature('starter', 'calendar'), false);   // カレンダーは統合プラン
  // 営業プランは集客機能まで
  assert.equal(planHasFeature('business', 'outbound'), true);
  assert.equal(planHasFeature('business', 'calendar'), false);
  // 統合プランは全部
  assert.equal(planHasFeature('pro', 'calendar'), true);
  assert.equal(planHasFeature('pro', 'multi_number'), true);
});
