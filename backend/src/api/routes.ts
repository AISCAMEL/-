import type { FastifyInstance } from 'fastify';
import { authenticate, requireSuperAdmin, requireRole } from '../auth/jwt.js';
import * as q from '../db/queries.js';
import { sendCallNotification } from '../notify/email.js';
import { getSettings } from '../db/queries.js';
import { tenantTestReply, type TestTurn } from '../ai/testchat.js';
import { sendWeeklyDigest } from '../notify/digest.js';

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

  // ---- me ----
  app.get('/api/me', { preHandler: authenticate }, async (req) => {
    const p = req.principal!;
    return { auth_user_id: p.authUserId, tenant_id: p.tenantId, role: p.role, email: p.email };
  });

  // ---- dashboard ----
  app.get('/api/dashboard', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.getDashboard(p.tenantId);
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
