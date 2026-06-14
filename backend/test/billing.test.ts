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

test('monthlyRevenueJpy: 上限内は基本料金、超過は加算', () => {
  const biz = PLANS.business; // base 29800 / 500分 / 超過60円
  assert.equal(monthlyRevenueJpy(biz, 300), 29800);
  assert.equal(monthlyRevenueJpy(biz, 500), 29800);
  assert.equal(monthlyRevenueJpy(biz, 600), 29800 + 100 * 60);
});

test('planDef: 未知プランは starter にフォールバック', () => {
  assert.equal(planDef('pro').label, 'Pro');
  assert.equal(planDef('unknown').label, 'Starter');
  assert.equal(planDef(null).label, 'Starter');
});
