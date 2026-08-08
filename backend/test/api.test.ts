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

test('連絡先: ステータス変更で活動履歴が記録される / ステータス絞り込み', async () => {
  // 連絡先を1件作成
  const created = await app.inject({ method: 'POST', url: '/api/contacts', headers: J,
    payload: { contacts: [{ name: 'テスト商談', company: 'テスト社', email: 't@e.com', category: 'テスト' }] } });
  assert.equal(created.statusCode, 200);
  const id = created.json()[0].id;

  // ステータスを商談中へ変更
  const upd = await app.inject({ method: 'PATCH', url: `/api/contacts/${id}`, headers: J, payload: { status: 'in_progress' } });
  assert.equal(upd.statusCode, 200);
  assert.equal(upd.json().status, 'in_progress');

  // 活動履歴に status_changed が残る
  const acts = await app.inject({ url: `/api/contacts/${id}/activities`, headers: owner });
  assert.equal(acts.statusCode, 200);
  assert.ok(acts.json().some((a: any) => a.type === 'status_changed'));

  // status 絞り込みで該当が返る
  const filtered = await app.inject({ url: '/api/contacts?status=in_progress', headers: owner });
  assert.equal(filtered.statusCode, 200);
  assert.ok(filtered.json().some((c: any) => c.id === id));
});

test('連絡先: 一斉メール送信で送信履歴(email_sent)が記録される', async () => {
  const created = await app.inject({ method: 'POST', url: '/api/contacts', headers: J,
    payload: { contacts: [{ name: 'メール宛', email: 'mail-target@e.com', category: '一斉テスト' }] } });
  const id = created.json()[0].id;

  const r = await app.inject({ method: 'POST', url: '/api/contacts/bulk-email', headers: J,
    payload: { subject: 'ご案内', body: '{{name}}様へ', category: '一斉テスト' } });
  assert.equal(r.statusCode, 200);
  assert.ok(r.json().total >= 1);

  const acts = await app.inject({ url: `/api/contacts/${id}/activities`, headers: owner });
  assert.ok(acts.json().some((a: any) => a.type === 'email_sent'));
});

test('業種テンプレート: 7業種が返る / clinicを適用するとFAQが追加される', async () => {
  const list = await app.inject({ url: '/api/industry-templates', headers: owner });
  assert.equal(list.statusCode, 200);
  const keys = list.json().map((t: any) => t.key);
  for (const k of ['car_dealer', 'salon', 'restaurant', 'clinic', 'seitai', 'real_estate', 'professional']) {
    assert.ok(keys.includes(k), `missing template: ${k}`);
  }

  const before = (await app.inject({ url: '/api/faqs', headers: owner })).json().length;
  const apply = await app.inject({ method: 'POST', url: '/api/industry-templates/clinic/apply', headers: J, payload: {} });
  assert.equal(apply.statusCode, 200);
  assert.ok(apply.json().applied.faqs > 0);
  const after = (await app.inject({ url: '/api/faqs', headers: owner })).json().length;
  assert.ok(after > before);

  // staff は適用不可（403）
  assert.equal((await app.inject({ method: 'POST', url: '/api/industry-templates/clinic/apply', headers: { ...J, ...staff }, payload: {} })).statusCode, 403);
});

test('GET /api/setup/status → 接続と設定のチェックリストを返す', async () => {
  const res = await app.inject({ url: '/api/setup/status', headers: owner });
  assert.equal(res.statusCode, 200);
  const d = res.json();
  assert.ok(d.integrations && 'ai' in d.integrations && 'email' in d.integrations && 'phone' in d.integrations);
  assert.ok(d.config && typeof d.config.faq_count === 'number');
  assert.equal(typeof d.integrations.ai.connected, 'boolean');
});

test('GET /api/plans → 機能別プランとカタログを返す（公開）', async () => {
  const res = await app.inject({ url: '/api/plans' });
  assert.equal(res.statusCode, 200);
  const d = res.json();
  assert.equal(d.plans.length, 3);
  assert.ok(d.plans[0].features.includes('reception'));
  assert.ok(d.plans[2].features.length > d.plans[0].features.length); // 上位ほど機能多い
  assert.ok(d.feature_catalog.outbound);
});

test('APIキー: 発行→そのキーで査定依頼を登録→失効で拒否', async () => {
  // 発行（owner）
  const created = await app.inject({ method: 'POST', url: '/api/api-keys', headers: J, payload: { name: 'HP査定フォーム' } });
  assert.equal(created.statusCode, 200);
  const { key, record } = created.json();
  assert.ok(key.startsWith('aiop_live_'));
  assert.ok(record.key_prefix.startsWith('aiop_live_'));

  // 一覧はマスク（平文キーは含まない）
  const list = await app.inject({ url: '/api/api-keys', headers: owner });
  assert.ok(list.json().every((k: any) => !('key' in k) && !('key_hash' in k)));

  // そのキーで外部から査定依頼（X-API-Key）
  const ing = await app.inject({ method: 'POST', url: '/api/v1/inquiries',
    headers: { 'content-type': 'application/json', 'x-api-key': key },
    payload: { name: '外部太郎', phone: '09000000000', car: 'プリウス 2019 4万km' } });
  assert.equal(ing.statusCode, 201);
  assert.ok(ing.json().contact_id);

  // Bearer でも通る
  const ing2 = await app.inject({ method: 'POST', url: '/api/v1/inquiries',
    headers: { 'content-type': 'application/json', authorization: `Bearer ${key}` }, payload: { name: '外部花子' } });
  assert.equal(ing2.statusCode, 201);

  // 不正キーは401
  const bad = await app.inject({ method: 'POST', url: '/api/v1/inquiries', headers: { 'content-type': 'application/json', 'x-api-key': 'aiop_live_wrong' }, payload: { name: 'x' } });
  assert.equal(bad.statusCode, 401);

  // 失効させると拒否
  await app.inject({ method: 'DELETE', url: `/api/api-keys/${record.id}`, headers: owner });
  const after = await app.inject({ method: 'POST', url: '/api/v1/inquiries', headers: { 'content-type': 'application/json', 'x-api-key': key }, payload: { name: 'y' } });
  assert.equal(after.statusCode, 401);
});

test('APIキー: 発行は owner/admin のみ（staff=403）', async () => {
  assert.equal((await app.inject({ method: 'POST', url: '/api/api-keys', headers: { ...J, ...staff }, payload: { name: 'x' } })).statusCode, 403);
});

test('業種テンプレート: replace=true で既存FAQを置き換えて別業種に切替', async () => {
  // まず何か適用してFAQを入れておく
  await app.inject({ method: 'POST', url: '/api/industry-templates/salon/apply', headers: J, payload: {} });
  // replaceで飲食店に切り替え → 既存FAQを消して入れ替え
  const r = await app.inject({ method: 'POST', url: '/api/industry-templates/restaurant/apply', headers: J, payload: { replace: true } });
  assert.equal(r.statusCode, 200);
  assert.equal(r.json().replaced, true);
  assert.ok(r.json().applied.cleared_faqs >= 1);     // 既存を消した
  assert.ok(r.json().applied.faqs >= 1);             // 新規を入れた
  // 結果のFAQ件数はテンプレの件数に一致（混ざっていない）
  const faqs = (await app.inject({ url: '/api/faqs', headers: owner })).json();
  const tpls = (await app.inject({ url: '/api/industry-templates', headers: owner })).json();
  const restaurant = tpls.find((t: any) => t.key === 'restaurant');
  assert.equal(faqs.length, restaurant.faq_count);
});

test('査定フォームSMS: URL設定済みならドライラン送信、番号なしは400', async () => {
  const ok = await app.inject({ method: 'POST', url: '/api/appraisal/send-sms', headers: J, payload: { phone: '09012345678', name: 'テスト' } });
  assert.equal(ok.statusCode, 200);
  assert.equal(ok.json().ok, true); // Twilio未接続なのでdryRun

  const noPhone = await app.inject({ method: 'POST', url: '/api/appraisal/send-sms', headers: J, payload: {} });
  assert.equal(noPhone.statusCode, 400);
});
