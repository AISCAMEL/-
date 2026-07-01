import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import type { FastifyInstance } from 'fastify';
import { buildApp } from '../src/server.js';
import { committedMrr, renewalAlerts } from '../src/admin/revenue.js';

test('committedMrr: 稼働テナントの月額合計とプラン別内訳を返す', async () => {
  const m = await committedMrr();
  assert.ok(m.committed_mrr_jpy > 0);
  assert.equal(m.arr_jpy, m.committed_mrr_jpy * 12);
  // デモ：稼働(active)が複数いる
  assert.ok(m.active_count >= 1);
  // 内訳の合計が確定MRRに一致
  const sum = Object.values(m.by_plan).reduce((s: number, v: any) => s + v.mrr_jpy, 0);
  assert.equal(sum, m.committed_mrr_jpy);
});

test('renewalAlerts: 期限間近トライアルと滞納を抽出', async () => {
  const a = await renewalAlerts(14);
  // デモに「あと数日でトライアル終了」「滞納」テナントを用意済み
  assert.ok(a.trial_ending.length >= 1);
  assert.ok(a.overdue.length >= 1);
  assert.equal(a.total, a.trial_ending.length + a.trial_expired.length + a.overdue.length);
});

let app: FastifyInstance;
const sa = { 'x-role': 'super_admin' };
before(async () => { app = await buildApp({ logger: false }); await app.ready(); });
after(async () => { await app.close(); });

test('GET /api/admin/overview → 確定MRR・アラートを含む', async () => {
  const res = await app.inject({ url: '/api/admin/overview', headers: sa });
  assert.equal(res.statusCode, 200);
  const d = res.json();
  assert.ok('committed_mrr_jpy' in d);
  assert.ok('mrr_by_plan' in d);
  assert.ok(d.alerts && typeof d.alerts.total === 'number');
});

test('GET /api/admin/revenue/export → CSV（super_adminのみ）', async () => {
  assert.equal((await app.inject({ url: '/api/admin/revenue/export' })).statusCode, 403); // owner
  const res = await app.inject({ url: '/api/admin/revenue/export', headers: sa });
  assert.equal(res.statusCode, 200);
  assert.match(res.headers['content-type'] as string, /text\/csv/);
  assert.match(res.body, /会社名/);
});

test('テナント台帳: trial_ends_at と payment_status を更新できる', async () => {
  const list = await app.inject({ url: '/api/admin/tenants', headers: sa });
  const id = list.json()[0].id;
  const upd = await app.inject({ method: 'PATCH', url: `/api/admin/tenants/${id}`,
    headers: { 'content-type': 'application/json', ...sa },
    payload: { payment_status: 'paid', trial_ends_at: '2099-12-31' } });
  assert.equal(upd.statusCode, 200);
  assert.equal(upd.json().payment_status, 'paid');
});

import { computePnl } from '../src/admin/pnl.js';

test('computePnl: 売上−原価=粗利、粗利−販管費=営業利益 が整合', async () => {
  const p = await computePnl();
  assert.equal(p.revenue.total, p.revenue.base + p.revenue.overage);
  assert.equal(p.gross_profit, p.revenue.total - p.cogs.total);
  assert.equal(p.operating_profit, p.gross_profit - p.opex.total);
  assert.equal(p.cogs.total, Math.round(p.cogs.ai + p.cogs.transfer));
});

test('P&L API: 経費を追加すると営業利益が減る', async () => {
  const before = (await computePnl()).operating_profit;
  const add = await app.inject({ method: 'POST', url: '/api/admin/expenses',
    headers: { 'content-type': 'application/json', ...sa }, payload: { label: 'テスト経費', category: 'other', monthly_jpy: 100000 } });
  assert.equal(add.statusCode, 200);
  const after = (await computePnl()).operating_profit;
  assert.equal(after, before - 100000);
  // 後片付け
  await app.inject({ method: 'DELETE', url: `/api/admin/expenses/${add.json().id}`, headers: sa });
});

test('P&L: 経費APIは super_admin のみ（owner=403）', async () => {
  assert.equal((await app.inject({ url: '/api/admin/pnl' })).statusCode, 403);
  assert.equal((await app.inject({ url: '/api/admin/expenses' })).statusCode, 403);
});
