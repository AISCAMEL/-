import type { FastifyInstance } from 'fastify';
import { authenticate, requireSuperAdmin } from '../auth/jwt.js';
import * as q from '../db/queries.js';
import { sendCallNotification } from '../notify/email.js';
import { getSettings } from '../db/queries.js';

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
    const { status, category, q: qs, limit } = req.query as Record<string, string>;
    return q.listCalls(p.tenantId, { status, category, q: qs, limit: limit ? Number(limit) : undefined });
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

  // ---- settings ----
  app.get('/api/settings/ai', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.getSettings(p.tenantId);
  });
  app.put('/api/settings/ai', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.updateSettings(p.tenantId, (req.body ?? {}) as Record<string, unknown>);
  });
  app.get('/api/settings/notification', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.getSettings(p.tenantId);
  });
  app.put('/api/settings/notification', { preHandler: authenticate }, async (req, reply) => {
    const p = req.principal!;
    if (!needTenant(p.tenantId)) return reply.code(400).send({ error: 'tenant required' });
    return q.updateSettings(p.tenantId, (req.body ?? {}) as Record<string, unknown>);
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

  // ---- super admin ----
  app.get('/api/admin/tenants', { preHandler: requireSuperAdmin }, async () => q.listTenants());
  app.post('/api/admin/tenants', { preHandler: requireSuperAdmin }, async (req, reply) => {
    const body = (req.body ?? {}) as any;
    if (!body.company_name) return reply.code(400).send({ error: 'company_name required' });
    return q.createTenant(body);
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
