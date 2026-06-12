import type { FastifyInstance } from 'fastify';
import { config } from '../config.js';
import { requireSuperAdmin } from '../auth/jwt.js';
import { sendEmail } from '../notify/email.js';
import * as leads from './repo.js';

// 問い合わせ導線のルート。
//   公開: POST /api/public/leads（LPフォーム。認証なし）
//   運営: /api/admin/leads*（super_admin のみ）
export async function registerLeadRoutes(app: FastifyInstance): Promise<void> {
  // --- 公開フォーム ---
  app.post('/api/public/leads', async (req, reply) => {
    const b = (req.body ?? {}) as Record<string, any>;
    if (!b.email && !b.phone) return reply.code(400).send({ error: 'メールアドレスまたは電話番号を入力してください' });

    const lead = await leads.createLead({
      source: 'lp_form',
      category: typeof b.category === 'string' ? b.category : 'inquiry',
      name: b.name, company: b.company, email: b.email, phone: b.phone,
      industry: b.industry, message: b.message,
      meta: { utm_source: b.utm_source, utm_medium: b.utm_medium, utm_campaign: b.utm_campaign, page: b.page },
    });

    // 自社営業への新規リード通知。
    await sendEmail(
      config.leadsNotifyEmail,
      '【AIオペレーター24】新しいお問い合わせがありました',
      [
        `種別: ${b.category ?? 'inquiry'}`,
        `氏名: ${b.name ?? '—'}`,
        `会社: ${b.company ?? '—'}`,
        `メール: ${b.email ?? '—'}`,
        `電話: ${b.phone ?? '—'}`,
        `業種: ${b.industry ?? '—'}`,
        '',
        `内容:\n${b.message ?? '—'}`,
      ].join('\n'),
    ).catch((err) => app.log.error({ err }, 'lead notify failed'));

    return reply.code(201).send({ ok: true, id: lead.id });
  });

  // --- 運営（受信箱） ---
  app.get('/api/admin/leads', { preHandler: requireSuperAdmin }, async (req) => {
    const { status, category, q } = req.query as Record<string, string>;
    return leads.listLeads({ status, category, q });
  });

  app.get('/api/admin/leads/:id', { preHandler: requireSuperAdmin }, async (req, reply) => {
    const lead = await leads.getLead((req.params as any).id);
    if (!lead) return reply.code(404).send({ error: 'not found' });
    return lead;
  });

  app.patch('/api/admin/leads/:id', { preHandler: requireSuperAdmin }, async (req, reply) => {
    const row = await leads.updateLead((req.params as any).id, (req.body ?? {}) as any);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });

  app.post('/api/admin/leads/:id/notes', { preHandler: requireSuperAdmin }, async (req, reply) => {
    const { note } = (req.body ?? {}) as { note?: string };
    if (!note) return reply.code(400).send({ error: 'note required' });
    return leads.addLeadNote((req.params as any).id, note);
  });

  app.post('/api/admin/leads/:id/meetings', { preHandler: requireSuperAdmin }, async (req) => {
    return leads.createMeeting((req.params as any).id, (req.body ?? {}) as any);
  });

  app.patch('/api/admin/meetings/:id', { preHandler: requireSuperAdmin }, async (req, reply) => {
    const row = await leads.updateMeeting((req.params as any).id, (req.body ?? {}) as any);
    if (!row) return reply.code(404).send({ error: 'not found' });
    return row;
  });

  app.post('/api/admin/leads/:id/email', { preHandler: requireSuperAdmin }, async (req, reply) => {
    const { subject, body } = (req.body ?? {}) as { subject?: string; body?: string };
    if (!subject || !body) return reply.code(400).send({ error: 'subject and body required' });
    const res = await leads.sendManualEmail((req.params as any).id, subject, body);
    return { ok: res.ok, error: res.error };
  });

  // worker を手動実行（テスト・デバッグ用）。
  app.post('/api/admin/leads-worker/run', { preHandler: requireSuperAdmin }, async () => {
    const sent = await leads.processDueEmails();
    return { sent };
  });
}
