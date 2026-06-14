import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ConversationOrchestrator } from '../src/ai/orchestrator.js';
import type { TenantContext } from '../src/types.js';

// LLM未設定（OPENAI_API_KEY なし）前提のフォールバック挙動を検証。
function ctx(overrides: Partial<TenantContext> = {}): TenantContext {
  return {
    tenantId: 't1', companyName: 'テスト店', industry: null,
    greetingMessage: 'こんにちは', aiTone: 'polite', businessHours: {}, holidaySettings: {},
    humanTransferEnabled: true, transferPhoneNumber: '+819000000000',
    notificationEmail: null, slackWebhookUrl: null,
    notifyOnCallEnd: true, notifyOnCallback: true, notifyOnTransfer: true,
    fallbackMessage: null, faqs: [],
    ...overrides,
  };
}

test('担当者希望でフォールバックが転送を返す', async () => {
  const o = new ConversationOrchestrator(ctx());
  const r = await o.handleUserUtterance('担当者につないでください');
  assert.equal(r.should_transfer, true);
  assert.equal(r.intent, 'transfer');
  assert.equal(o.transcript.length, 2); // customer + ai
});

test('転送先未設定なら転送しない', async () => {
  const o = new ConversationOrchestrator(ctx({ transferPhoneNumber: null }));
  const r = await o.handleUserUtterance('担当者につないで');
  assert.equal(r.should_transfer, false);
});

test('通常発話は転送せず extracted の形を保つ', async () => {
  const o = new ConversationOrchestrator(ctx());
  const r = await o.handleUserUtterance('予約したいです');
  assert.equal(r.should_transfer, false);
  assert.equal(r.extracted.callback_requested, false);
  assert.ok('customer_name' in r.extracted);
});
