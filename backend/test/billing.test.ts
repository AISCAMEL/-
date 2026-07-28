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

test('monthlyRevenueJpy: 上限内は基本料金、超過は従量加算', () => {
  const biz = PLANS.business; // 低額基本料＋従量モデル
  assert.equal(monthlyRevenueJpy(biz, biz.allowanceMin - 1), biz.baseJpy);      // 上限内
  assert.equal(monthlyRevenueJpy(biz, biz.allowanceMin), biz.baseJpy);          // ちょうど
  assert.equal(monthlyRevenueJpy(biz, biz.allowanceMin + 100), biz.baseJpy + 100 * biz.overageJpyPerMin); // 超過
});

test('planDef: 未知プランは starter にフォールバック', () => {
  assert.equal(planDef('pro').label, PLANS.pro.label);
  assert.equal(planDef('unknown').label, PLANS.starter.label);
  assert.equal(planDef(null).label, PLANS.starter.label);
});
