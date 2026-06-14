import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import type { FastifyInstance } from 'fastify';
import { buildApp } from '../src/server.js';

// AUTH_DEV_MODE=true（既定）前提。ヘッダ x-role でロールを切り替える。
let app: FastifyInstance;
const J = { 'content-type': 'application/json' };
const owner = {};                              // 既定は owner
const staff = { 'x-role': 'staff' };
const sa = { 'x-role': 'super_admin' };

before(async () => { app = await buildApp({ logger: false }); await app.ready(); });
after(async () => { await app.close(); });

test('GET /health → ok', async () => {
  const res = await app.inject({ method: 'GET', url: '/health' });
  assert.equal(res.statusCode, 200);
  assert.equal(res.json().ok, true);
});

test('GET /api/me → ロールを反映', async () => {
  const res = await app.inject({ url: '/api/me', headers: staff });
  assert.equal(res.statusCode, 200);
  assert.equal(res.json().role, 'staff');
});

test('GET /api/dashboard → 集計を返す', async () => {
  const res = await app.inject({ url: '/api/dashboard', headers: owner });
  assert.equal(res.statusCode, 200);
  assert.ok('calls_today' in res.json());
});

test('admin API は super_admin のみ（owner=403, sa=200）', async () => {
  assert.equal((await app.inject({ url: '/api/admin/tenants', headers: owner })).statusCode, 403);
  const ok = await app.inject({ url: '/api/admin/tenants', headers: sa });
  assert.equal(ok.statusCode, 200);
  assert.ok(Array.isArray(ok.json()));
});

test('FAQ: 必須欠落=400 / 作成→一覧に反映', async () => {
  const bad = await app.inject({ method: 'POST', url: '/api/faqs', headers: J, payload: { question: 'q only' } });
  assert.equal(bad.statusCode, 400);

  const created = await app.inject({ method: 'POST', url: '/api/faqs', headers: J, payload: { question: 'テストQ', answer: 'テストA' } });
  assert.equal(created.statusCode, 200);
  const id = created.json().id;

  const list = await app.inject({ url: '/api/faqs' });
  assert.ok(list.json().some((f: any) => f.id === id));
});

test('ユーザー管理: staffは作成不可(403) / ownerは可 / 最後のowner降格=400', async () => {
  assert.equal((await app.inject({ method: 'POST', url: '/api/users', headers: { ...J, ...staff }, payload: { email: 'x@e.com' } })).statusCode, 403);

  const created = await app.inject({ method: 'POST', url: '/api/users', headers: J, payload: { name: 'スタッフ', email: `u${Date.now()}@e.com`, role: 'staff' } });
  assert.equal(created.statusCode, 200);

  // user-1 が唯一の owner（fixtures）。降格は拒否される。
  const demote = await app.inject({ method: 'PATCH', url: '/api/users/user-1', headers: J, payload: { role: 'staff' } });
  assert.equal(demote.statusCode, 400);
});

test('テナント更新: 不正プラン=400 / 正常=200 (super_admin)', async () => {
  const tid = '00000000-0000-0000-0000-000000000001';
  const bad = await app.inject({ method: 'PATCH', url: `/api/admin/tenants/${tid}`, headers: { ...J, ...sa }, payload: { plan: 'ultra' } });
  assert.equal(bad.statusCode, 400);
  const ok = await app.inject({ method: 'PATCH', url: `/api/admin/tenants/${tid}`, headers: { ...J, ...sa }, payload: { plan: 'pro' } });
  assert.equal(ok.statusCode, 200);
  assert.equal(ok.json().plan, 'pro');
});

test('公開リード: 不正メール=400 / ハニーポット=201(黙ってOK) / 正常=201', async () => {
  assert.equal((await app.inject({ method: 'POST', url: '/api/public/leads', headers: J, payload: { email: 'bad' } })).statusCode, 400);

  const hp = await app.inject({ method: 'POST', url: '/api/public/leads', headers: J, payload: { email: 'bot@e.com', company_url: 'http://spam' } });
  assert.equal(hp.statusCode, 201);

  const ok = await app.inject({ method: 'POST', url: '/api/public/leads', headers: J, payload: { name: 'テスト', email: 'lead@e.com', category: 'demo' } });
  assert.equal(ok.statusCode, 201);
  assert.equal(ok.json().ok, true);
});

test('認証必須エンドポイントの権限境界（運営概要は super_admin のみ）', async () => {
  assert.equal((await app.inject({ url: '/api/admin/overview', headers: owner })).statusCode, 403);
  assert.equal((await app.inject({ url: '/api/admin/overview', headers: sa })).statusCode, 200);
});
