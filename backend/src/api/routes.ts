import type { FastifyInstance } from 'fastify';
import { authenticate, requireSuperAdmin, requireRole } from '../auth/jwt.js';
import * as q from '../db/queries.js';
import { sendCallNotification } from '../notify/email.js';
import { getSettings } from '../db/queries.js';
import { tenantTestReply, type TestTurn } from '../ai/testchat.js';
import { sendWeeklyDigest } from '../notify/digest.js';
import { getBillingStatus, createOverageInvoice } from '../billing/square.js';
import * as outbound from '../outbound/repo.js';
import { runCampaign } from '../outbound/caller.js';
import { INDUSTRY_TEMPLATES, getTemplate } from '../templates/industry.js';
import * as contacts from '../outbound/contacts.js';
import { chatText } from '../ai/llm.js';
import { sendEmail } from '../notify/email.js';
import * as calendar from '../calendar/index.js';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// 設定更新時の入力検証。問題があればエラーメッセージ、なければ null。
function validateSettingsPatch(patch: Record<string, unknown>): string | null {
  const email = patch.notification_email;
  if (typeof email === 'string' && email.trim() && !EMAIL_RE.test(email.trim())) {
    return '通知先メールアドレスの形式が正しくありません。';
  }
  const phone = patch.transfer_phone_number;
  if (typeof phone === 'string' && phone.trim()) {
    const digits = phone.replace(/[\s-]/g, '');
    if (!/^\+?\d{10,15}$/.test(digits)) {
      return '転送先電話番号の形式が正しくありません（例: +81901234567 または 0901234567）。';
    }
  }
  const slack = patch.slack_webhook_url;
  if (typeof slack === 'string' && slack.trim() && !/^https:\/\/hooks\.slack\.com\//.test(slack.trim())) {
    return 'Slack Webhook URL は https://hooks.slack.com/ で始まる必要があります。';
  }
  return null;
}

// 管理画面 API（docs/api.md 準拠）。全エンドポイントは JWT(またはdevモード) 認証必須。
export async function registerApiRoutes(app: FastifyInstance): Promise<void> {
  // テナント未解決(super_admin が tenant 指定なし)の保護
  const needTenant = (tenantId: string | null | undefined): tenantId is string => Boolean(tenantId);
  // owner/admin/super_admin のみ操作可（架電・連絡先など）
  const manageOutbound = requireRole(['owner', 'admin', 'super_admin']);

  // ---- me ----
  app.get('/api/me', { preHandler: authenticate }, async (req) => {
    const p = req.principal!;
    return { auth_user_id: p.authUserId, tenant_id: p.tenantId, role: p.role, email: p.email };
  });

  // ---- dashboard ----
  app.get('/api/dashboard', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const [dash, contactSummary] = await Promise.all([
      q.getDashboard(p.tenantId),
      contacts.contactStatusSummary(p.tenantId),
    ]);
    return { ...dash, contacts_summary: contactSummary };
  });

  // ---- calls ----
  app.get('/api/calls', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { status, category, q: qs, period, attention, tag, limit } = req.query as Record<string, string>;
    return q.listCalls(p.tenantId, {
      status, category, q: qs, period, tag, attention: attention === '1' || attention === 'true',
      limit: limit ? Number(limit) : undefined,
    });
  });

  // 通話履歴CSV（フィルタ対応・UTF-8 BOM）。:id ルートより前に定義する。
  app.get('/api/calls/export', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { status, category, q: qs, period, attention } = req.query as Record<string, string>;
    const items = await q.listCalls(p.tenantId, {
      status, category, q: qs, period, attention: attention === '1' || attention === 'true', limit: 1000,
    });
    const csv = '﻿' + q.callsToCsv(items);
    reply.header('Content-Type', 'text/csv; charset=utf-8');
    reply.header('Content-Disposition', 'attachment; filename="calls.csv"');
    return reply.send(csv);
  });

  app.get('/api/calls/:id', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const call = await q.getCall(p.tenantId, (req.params as any).id);
    if (!call) return reply.code(404).send({ error: 'not found' });
    return call;
  });

  app.patch('/api/calls/:id/status', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { status } = (req.body ?? {}) as { status?: string };
    if (!status) return reply.code(400).send({ error: 'status required' });
    const row = await q.updateCallStatus(p.tenantId, (req.params as any).id, status);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });

  app.patch('/api/calls/:id/tags', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { tags } = (req.body ?? {}) as { tags?: string[] };
    if (!Array.isArray(tags)) return reply.code(400).send({ error: 'tags array required' });
    const row = await q.updateCallTags(p.tenantId, (req.params as any).id, tags);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });

  app.post('/api/calls/:id/notes', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { note } = (req.body ?? {}) as { note?: string };
    if (!note) return reply.code(400).send({ error: 'note required' });
    const row = await q.addCallNote(p.tenantId, (req.params as any).id, p.authUserId, note);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });

  app.post('/api/calls/:id/summarize', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const summary = await q.resummarizeCall(p.tenantId, (req.params as any).id);
    if (!summary) return reply.code(404).send({ error: 'not found' });
    return summary;
  });

  app.post('/api/calls/:id/notify', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const call = await q.getCall(p.tenantId, (req.params as any).id);
    if (!call) return reply.code(404).send({ error: 'not found' });
    const settings = await getSettings(p.tenantId);
    const dest = settings?.notification_email ?? 'owner@example.com';
    const result = await sendCallNotification(dest, {
      fromNumber: call.from_number ?? '',
      summary: {
        summary: call.summary ?? '', category: call.category ?? 'other',
        customer_name: call.customer_name ?? null, company_name: call.company_name ?? null,
        requested_datetime: call.requested_datetime ?? null, request_detail: call.request_detail ?? null,
        next_action: call.next_action ?? null, urgency: call.urgency ?? 'normal',
        sentiment: call.sentiment ?? 'neutral', callback_requested: call.status === 'callback_requested',
        should_follow_up: false,
      },
      statusLabel: '再通知',
    });
    return { ok: result.ok, destination: dest, error: result.error };
  });

  // ---- FAQ ----
  app.get('/api/faqs', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.listFaqs(p.tenantId);
  });

  app.post('/api/faqs', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const body = (req.body ?? {}) as any;
    if (!body.question || !body.answer) return reply.code(400).send({ error: 'question and answer required' });
    return q.createFaq(p.tenantId, body);
  });

  app.put('/api/faqs/:id', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const row = await q.updateFaq(p.tenantId, (req.params as any).id, (req.body ?? {}) as any);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });

  app.delete('/api/faqs/:id', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const ok = await q.deleteFaq(p.tenantId, (req.params as any).id);
    if (!ok) return reply.code(404).send({ error: 'not found' });
    return { ok: true };
  });
  app.post('/api/faqs/:id/move', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { dir } = (req.body ?? {}) as { dir?: string };
    if (dir !== 'up' && dir !== 'down') return reply.code(400).send({ error: 'dir must be up or down' });
    await q.moveFaq(p.tenantId, (req.params as any).id, dir);
    return { ok: true };
  });

  // ---- settings ----
  app.get('/api/settings/ai', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.getSettings(p.tenantId);
  });
  app.put('/api/settings/ai', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const body = (req.body ?? {}) as Record<string, unknown>;
    const err = validateSettingsPatch(body);
    if (err) return reply.code(400).send({ error: err });
    return q.updateSettings(p.tenantId, body);
  });
  app.get('/api/settings/notification', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.getSettings(p.tenantId);
  });
  app.get('/api/notifications', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.listNotifications(p.tenantId);
  });
  app.put('/api/settings/notification', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const body = (req.body ?? {}) as Record<string, unknown>;
    const err = validateSettingsPatch(body);
    if (err) return reply.code(400).send({ error: err });
    return q.updateSettings(p.tenantId, body);
  });

  // ---- 発信者ルール（ブロック/専用アナウンス） ----
  app.get('/api/caller-rules', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.listCallerRules(p.tenantId);
  });
  app.post('/api/caller-rules', { preHandler: requireRole(['owner', 'admin', 'super_admin']) }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const body = (req.body ?? {}) as any;
    if (!body.phone_number) return reply.code(400).send({ error: '電話番号を入力してください' });
    if (body.action !== 'block' && body.action !== 'greeting') return reply.code(400).send({ error: 'action は block か greeting' });
    return q.createCallerRule(p.tenantId, body);
  });
  app.delete('/api/caller-rules/:id', { preHandler: requireRole(['owner', 'admin', 'super_admin']) }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const ok = await q.deleteCallerRule(p.tenantId, (req.params as any).id);
    if (!ok) return reply.code(404).send({ error: 'not found' });
    return { ok: true };
  });

  // ---- phone numbers ----
  app.get('/api/phone-numbers', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.listPhoneNumbers(p.tenantId);
  });
  app.patch('/api/phone-numbers/:id', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const row = await q.updatePhoneNumber(p.tenantId, (req.params as any).id, (req.body ?? {}) as any);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });

  // ---- AI応対テスト（テナントの設定/FAQで会話を試す） ----
  app.post('/api/ai-test', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const body = (req.body ?? {}) as { message?: string; history?: TestTurn[] };
    const message = (body.message ?? '').toString().slice(0, 1000).trim();
    if (!message) return reply.code(400).send({ error: 'message required' });
    const ctx = await q.getTenantAiContext(p.tenantId);
    const history = Array.isArray(body.history) ? body.history.slice(-12) : [];
    const result = await tenantTestReply(ctx, history, message);
    return result;
  });

  // ---- 週次サマリーメール（手動送信） ----
  app.post('/api/digest/weekly', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return sendWeeklyDigest(p.tenantId);
  });

  // ---- 請求（Square） ----
  app.get('/api/billing', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return getBillingStatus(p.tenantId);
  });
  app.post('/api/billing/invoice-overage', { preHandler: requireRole(['owner', 'admin', 'super_admin']) }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { month } = (req.body ?? {}) as { month?: string };
    return createOverageInvoice(p.tenantId, month);
  });

  // ---- usage / 原価モニタリング ----
  app.get('/api/usage', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { month } = req.query as Record<string, string>;
    return q.getUsageSummary(p.tenantId, month);
  });

  // 請求書データ（JSON）。フロントで印刷/PDF化する。
  app.get('/api/usage/invoice', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { month } = req.query as Record<string, string>;
    return q.getInvoice(p.tenantId, month);
  });

  // 通話明細CSV（Excel向けにUTF-8 BOM付き）。
  app.get('/api/usage/export', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { month } = req.query as Record<string, string>;
    const items = await q.getCallLineItems(p.tenantId, month);
    const csv = '﻿' + q.lineItemsToCsv(items);
    const fname = `usage_${month ?? 'current'}.csv`;
    reply.header('Content-Type', 'text/csv; charset=utf-8');
    reply.header('Content-Disposition', `attachment; filename="${fname}"`);
    return reply.send(csv);
  });

  // ---- ユーザー管理（owner / admin / super_admin のみ） ----
  const manageUsers = requireRole(['owner', 'admin', 'super_admin']);

  app.get('/api/users', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.listUsers(p.tenantId);
  });

  app.post('/api/users', { preHandler: manageUsers }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const body = (req.body ?? {}) as { name?: string; email?: string; role?: string };
    if (!body.email) return reply.code(400).send({ error: 'email required' });
    const res = await q.createUser(p.tenantId, { name: body.name, email: body.email, role: body.role ?? 'staff' });
    if ('error' in res) return reply.code(409).send({ error: 'このメールアドレスは既に登録されています' });
    return res.user;
  });

  app.patch('/api/users/:id', { preHandler: manageUsers }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const id = (req.params as any).id;
    const body = (req.body ?? {}) as { role?: string; is_active?: boolean; name?: string };
    // 最後のownerを降格/無効化しないよう保護。
    const demotingOwner = (body.role && body.role !== 'owner') || body.is_active === false;
    if (demotingOwner && (await q.countActiveOwners(p.tenantId, id)) === 0) {
      return reply.code(400).send({ error: 'オーナーは最低1人必要です' });
    }
    const row = await q.updateUser(p.tenantId, id, body);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });

  app.delete('/api/users/:id', { preHandler: manageUsers }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const id = (req.params as any).id;
    if ((await q.countActiveOwners(p.tenantId, id)) === 0) {
      return reply.code(400).send({ error: 'オーナーは最低1人必要です' });
    }
    const ok = await q.deleteUser(p.tenantId, id);
    if (!ok) return reply.code(404).send({ error: 'not found' });
    return { ok: true };
  });

  // ---- 連絡先リスト（見込み客CRM） ----
  app.get('/api/contacts', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { category, q: qs, status } = req.query as Record<string, string>;
    return contacts.listContacts(p.tenantId, { category, q: qs, status });
  });
  app.get('/api/contacts/:id/activities', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return contacts.listActivities(p.tenantId, (req.params as any).id);
  });
  app.get('/api/contacts/categories', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return contacts.contactCategories(p.tenantId);
  });
  app.post('/api/contacts', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const body = (req.body ?? {}) as any;
    const items = Array.isArray(body.contacts) ? body.contacts : [body];
    return contacts.createContacts(p.tenantId, items);
  });
  app.patch('/api/contacts/:id', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const row = await contacts.updateContact(p.tenantId, (req.params as any).id, (req.body ?? {}) as any);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });
  app.delete('/api/contacts/:id', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const ok = await contacts.deleteContact(p.tenantId, (req.params as any).id);
    if (!ok) return reply.code(404).send({ error: 'not found' });
    return { ok: true };
  });
  // 連絡先へ一斉メール送信（カテゴリ絞り込み可。{{name}}/{{company}}差し込み）
  app.post('/api/contacts/bulk-email', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { subject, body: text, category } = (req.body ?? {}) as { subject?: string; body?: string; category?: string };
    if (!subject || !text) return reply.code(400).send({ error: '件名と本文を入力してください' });
    const list = await contacts.listContacts(p.tenantId, { category });
    const targets = list.filter((c: any) => c.email && c.status !== 'do_not_contact');
    let sent = 0, failed = 0;
    for (const c of targets) {
      const personalize = (s: string) => s.replace(/\{\{name\}\}/g, c.name || 'ご担当者').replace(/\{\{company\}\}/g, c.company || '');
      const r = await sendEmail(c.email, personalize(subject), personalize(text));
      if (r.ok) { sent++; await contacts.logActivity(p.tenantId, c.id, 'email_sent', `一斉メール：${subject}`); }
      else failed++;
    }
    return { ok: true, total: targets.length, sent, failed };
  });

  // 連絡先（カテゴリ）から架電キャンペーンを作成
  app.post('/api/contacts/to-campaign', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const body = (req.body ?? {}) as any;
    if (!body.name) return reply.code(400).send({ error: 'キャンペーン名を入力してください' });
    return contacts.campaignFromContacts(p.tenantId, body);
  });
  // 連絡先へメール送信（個別）
  app.post('/api/contacts/:id/email', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { subject, body: text } = (req.body ?? {}) as { subject?: string; body?: string };
    if (!subject || !text) return reply.code(400).send({ error: 'subject and body required' });
    const list = await contacts.listContacts(p.tenantId);
    const c = list.find((x: any) => x.id === (req.params as any).id);
    if (!c?.email) return reply.code(400).send({ error: 'この連絡先にメールアドレスがありません' });
    const r = await sendEmail(c.email, subject, text);
    if (r.ok) await contacts.logActivity(p.tenantId, c.id, 'email_sent', subject);
    return { ok: r.ok, destination: c.email, error: r.error };
  });

  // ---- 予約（査定・来店など）。Googleカレンダーと突合し重複を防ぐ ----
  app.get('/api/calendar/status', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return calendar.calendarStatus(p.tenantId);
  });
  // Googleカレンダー連携の設定（calendar_id / refresh_token / 所要時間）
  app.put('/api/calendar/settings', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const b = (req.body ?? {}) as any;
    const patch: Record<string, unknown> = {};
    if ('google_calendar_id' in b) patch.google_calendar_id = String(b.google_calendar_id ?? '');
    if ('google_refresh_token' in b) patch.google_refresh_token = String(b.google_refresh_token ?? '');
    if ('appointment_duration_min' in b) patch.appointment_duration_min = Number(b.appointment_duration_min) || 45;
    await q.updateSettings(p.tenantId, patch);
    return calendar.calendarStatus(p.tenantId);
  });
  // 指定日の空き枠（内部予約＋Google予定を除外）
  app.get('/api/appointments/slots', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { date, duration } = req.query as Record<string, string>;
    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) return reply.code(400).send({ error: 'date(YYYY-MM-DD)が必要です' });
    const slots = await calendar.findSlots(p.tenantId, date, duration ? Number(duration) : undefined);
    return { date, slots };
  });
  // 予約一覧（?from=&to= ISO、既定は今日以降30日）
  app.get('/api/appointments', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { from, to } = req.query as Record<string, string>;
    return calendar.listAppointments(p.tenantId, from, to, false);
  });
  // 予約を取る（重複時は409）
  app.post('/api/appointments', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const b = (req.body ?? {}) as any;
    if (!b.start_at || !b.end_at) return reply.code(400).send({ error: '開始・終了時刻が必要です' });
    const r = await calendar.book(p.tenantId, b);
    if (!r.ok) return reply.code(r.conflict ? 409 : 400).send({ error: r.error, conflict: r.conflict });
    return r;
  });
  // 予約のステータス変更（confirmed/cancelled/done）
  app.patch('/api/appointments/:id', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { status } = (req.body ?? {}) as { status?: string };
    if (!status) return reply.code(400).send({ error: 'status required' });
    const row = await calendar.changeStatus(p.tenantId, (req.params as any).id, status);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });

  // AIで営業メール/トーク文面を作成（会社データは作らない。文面作成のみ）
  app.post('/api/ai/draft', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { kind, product, target, tone } = (req.body ?? {}) as { kind?: string; product?: string; target?: string; tone?: string };
    if (!product) return reply.code(400).send({ error: '商品・サービスの説明を入力してください' });
    const isEmail = kind !== 'call';
    const sys = `あなたは日本のBtoB営業アシスタントです。${isEmail ? '丁寧な営業メールの件名と本文' : '電話の営業トークスクリプト'}を作成します。誇大表現や虚偽は書かない。簡潔に。`;
    const usr = `商品/サービス: ${product}\n想定の相手: ${target || '中小企業のご担当者'}\nトーン: ${tone || '丁寧'}\n${isEmail ? '件名と本文を作って。' : '最初の挨拶〜用件〜打診の短いトークを作って。'}`;
    const text = await chatText([{ role: 'system', content: sys }, { role: 'user', content: usr }]);
    if (text) return { ok: true, text };
    // フォールバック（LLM未接続）
    const fb = isEmail
      ? `件名：【ご案内】${product}のご提案\n\nお世話になっております。〇〇です。\n${product}についてご案内したくご連絡しました。貴社の業務効率化にお役立ていただける内容です。\nもしご関心がございましたら、5分ほどお打合せのお時間をいただけますでしょうか。\nどうぞよろしくお願いいたします。`
      : `お世話になっております、〇〇です。${product}のご案内でお電話しました。少しだけお時間よろしいでしょうか。実は〜という課題を解決できるサービスでして、もしご関心あれば担当より詳しくご説明します。`;
    return { ok: true, text: fb, fallback: true };
  });

  // ---- アウトバウンド架電（AI営業/催促 等） ----
  app.get('/api/campaigns', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return outbound.listCampaigns(p.tenantId);
  });
  app.get('/api/campaigns/:id', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const c = await outbound.getCampaign(p.tenantId, (req.params as any).id);
    if (!c) return reply.code(404).send({ error: 'not found' });
    return c;
  });
  app.post('/api/campaigns', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return outbound.createCampaign(p.tenantId, (req.body ?? {}) as any);
  });
  app.patch('/api/campaigns/:id', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const row = await outbound.updateCampaign(p.tenantId, (req.params as any).id, (req.body ?? {}) as any);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });
  app.post('/api/campaigns/:id/targets', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const { targets } = (req.body ?? {}) as { targets?: any[] };
    if (!Array.isArray(targets) || targets.length === 0) return reply.code(400).send({ error: 'targets required' });
    return outbound.addTargets(p.tenantId, (req.params as any).id, targets);
  });
  app.patch('/api/targets/:id', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const row = await outbound.updateTarget(p.tenantId, (req.params as any).id, (req.body ?? {}) as any);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });
  app.post('/api/campaigns/:id/run', { preHandler: manageOutbound }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    await outbound.updateCampaign(p.tenantId, (req.params as any).id, { status: 'running' });
    return runCampaign(p.tenantId, (req.params as any).id);
  });

  // ---- 業種テンプレート ----
  app.get('/api/industry-templates', { preHandler: authenticate }, async () =>
    INDUSTRY_TEMPLATES.map((t) => ({ key: t.key, label: t.label, summary: t.summary, faq_count: t.faqs.length, campaign_count: t.campaigns.length })));

  app.post('/api/industry-templates/:key/apply', { preHandler: requireRole(['owner', 'admin', 'super_admin']) }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    const tpl = getTemplate((req.params as any).key);
    if (!tpl) return reply.code(404).send({ error: 'template not found' });
    const opts = (req.body ?? {}) as { faqs?: boolean; campaigns?: boolean; settings?: boolean };

    if (opts.settings !== false) {
      await q.updateSettings(p.tenantId, { greeting_message: tpl.greeting, ai_tone: tpl.ai_tone });
      await q.updateTenant(p.tenantId, { industry: tpl.industry });
    }
    let faqs = 0, campaigns = 0;
    if (opts.faqs !== false) {
      for (const f of tpl.faqs) { await q.createFaq(p.tenantId, f); faqs++; }
    }
    if (opts.campaigns !== false) {
      for (const c of tpl.campaigns) { await outbound.createCampaign(p.tenantId, c); campaigns++; }
    }
    return { ok: true, applied: { settings: opts.settings !== false, faqs, campaigns } };
  });

  // ---- super admin ----
  app.get('/api/admin/tenants', { preHandler: requireSuperAdmin }, async () => q.listTenants());
  app.post('/api/admin/tenants', { preHandler: requireSuperAdmin }, async (req, reply) => {
    const body = (req.body ?? {}) as any;
    if (!body.company_name) return reply.code(400).send({ error: 'company_name required' });
    return q.createTenant(body);
  });
  app.get('/api/admin/tenants/:id', { preHandler: requireSuperAdmin }, async (req, reply) => {
    const detail = await q.getTenantDetail((req.params as any).id);
    if (!detail) return reply.code(404).send({ error: 'not found' });
    return detail;
  });
  app.patch('/api/admin/tenants/:id', { preHandler: requireSuperAdmin }, async (req, reply) => {
    const res = await q.updateTenant((req.params as any).id, (req.body ?? {}) as Record<string, unknown>);
    if (!res) return reply.code(404).send({ error: 'not found' });
    if ('error' in res) return reply.code(400).send({ error: res.error });
    return res.tenant;
  });
  app.get('/api/admin/calls', { preHandler: requireSuperAdmin }, async (req) => {
    const { limit } = req.query as Record<string, string>;
    return q.listAllCalls({ limit: limit ? Number(limit) : undefined });
  });
  app.get('/api/admin/usage', { preHandler: requireSuperAdmin }, async (req) => {
    const { month } = req.query as Record<string, string>;
    return q.getAdminUsageSummary(month);
  });
}
