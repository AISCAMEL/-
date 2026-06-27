import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import type { FastifyInstance } from 'fastify';
import { buildApp } from '../src/server.js';
import { availableSlots, hasConflict, jstWallToDate } from '../src/calendar/slots.js';

// ---- 純粋ロジック ----
test('availableSlots: 営業時間からbusyを除外し、過去枠も出さない', () => {
  const date = '2999-01-04'; // 金曜・十分未来
  const hours = { fri: [['10:00', '12:00']] as [string, string][] };
  // 10:00-10:45 を埋める
  const busy = [{ start: jstWallToDate(date, '10:00'), end: jstWallToDate(date, '10:45') }];
  const slots = availableSlots(date, hours, { weekly: [], dates: [] }, busy, 45);
  // 10:00開始は重複で除外、10:45開始(〜11:30)が出る、11:30開始(〜12:15)は閉店超過で出ない
  assert.ok(!slots.some((s) => s.start === jstWallToDate(date, '10:00').toISOString()));
  assert.ok(slots.some((s) => s.start === jstWallToDate(date, '10:45').toISOString()));
});

test('hasConflict: 重なりを正しく判定', () => {
  const a1 = new Date('2999-01-01T01:00:00Z'), a2 = new Date('2999-01-01T02:00:00Z');
  assert.equal(hasConflict(a1, a2, [{ start: new Date('2999-01-01T01:30:00Z'), end: new Date('2999-01-01T02:30:00Z') }]), true);
  assert.equal(hasConflict(a1, a2, [{ start: new Date('2999-01-01T02:00:00Z'), end: new Date('2999-01-01T03:00:00Z') }]), false); // 隣接はOK
});

test('availableSlots: 休業日は空', () => {
  const date = '2999-01-05'; // 土曜
  const hours = { sat: [['10:00', '18:00']] as [string, string][] };
  assert.equal(availableSlots(date, hours, { weekly: ['sat'], dates: [] }, [], 45).length, 0);
});

// ---- API（ダブルブッキング防止） ----
let app: FastifyInstance;
const J = { 'content-type': 'application/json' };
before(async () => { app = await buildApp({ logger: false }); await app.ready(); });
after(async () => { await app.close(); });

test('予約: 同じ時間帯の二重予約は409、ずらせば成功', async () => {
  const start = '2999-02-01T01:00:00.000Z', end = '2999-02-01T01:45:00.000Z';
  const first = await app.inject({ method: 'POST', url: '/api/appointments', headers: J,
    payload: { type: '査定', customer_name: 'テスト客', start_at: start, end_at: end } });
  assert.equal(first.statusCode, 200);
  assert.equal(first.json().ok, true);

  // 重複 → 409
  const dup = await app.inject({ method: 'POST', url: '/api/appointments', headers: J,
    payload: { type: '査定', customer_name: '別客', start_at: '2999-02-01T01:30:00.000Z', end_at: '2999-02-01T02:15:00.000Z' } });
  assert.equal(dup.statusCode, 409);
  assert.equal(dup.json().conflict, true);

  // ずらせばOK
  const ok = await app.inject({ method: 'POST', url: '/api/appointments', headers: J,
    payload: { type: '査定', customer_name: '別客', start_at: '2999-02-01T02:00:00.000Z', end_at: '2999-02-01T02:45:00.000Z' } });
  assert.equal(ok.statusCode, 200);
});

test('予約: キャンセルすると同枠が再予約できる', async () => {
  const start = '2999-03-01T03:00:00.000Z', end = '2999-03-01T03:45:00.000Z';
  const made = await app.inject({ method: 'POST', url: '/api/appointments', headers: J,
    payload: { type: '査定', customer_name: 'A', start_at: start, end_at: end } });
  const id = made.json().appointment.id;
  await app.inject({ method: 'PATCH', url: `/api/appointments/${id}`, headers: J, payload: { status: 'cancelled' } });
  const reuse = await app.inject({ method: 'POST', url: '/api/appointments', headers: J,
    payload: { type: '査定', customer_name: 'B', start_at: start, end_at: end } });
  assert.equal(reuse.statusCode, 200);
});

test('カレンダー状態: 未接続なら google_connected=false', async () => {
  const res = await app.inject({ url: '/api/calendar/status' });
  assert.equal(res.statusCode, 200);
  assert.equal(res.json().google_connected, false);
});
