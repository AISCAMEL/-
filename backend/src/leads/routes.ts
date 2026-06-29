import type { FastifyInstance } from 'fastify';
import { config } from '../config.js';
import { requireSuperAdmin } from '../auth/jwt.js';
import { sendEmail } from '../notify/email.js';
import * as leads from './repo.js';
import { listTenants, getAdminUsageSummary } from '../db/queries.js';
import { committedMrr, renewalAlerts } from '../admin/revenue.js';
import { rateLimit } from '../util/ratelimit.js';
import { salesChatReply, type ChatTurn } from './chat.js';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// 問い合わせ導線のルート。
//   公開: POST /api/public/leads（LPフォーム。認証なし）
//   運営: /api/admin/leads*（super_admin のみ）
export async function registerLeadRoutes(app: FastifyInstance): Promise<void> {
  // --- 公開フォーム ---
  app.post('/api/public/leads', async (req, reply) => {
    const b = (req.body ?? {}) as Record<string, any>;

    // レート制限（IP単位・10分で5件まで）。
    const ip = (req.headers['x-forwarded-for'] as string)?.split(',')[0]?.trim() || req.ip;
    const rl = rateLimit(`lead:${ip}`, 5, 10 * 60_000);
    if (!rl.allowed) {
      reply.header('Retry-After', rl.retryAfter);
      return reply.code(429).send({ error: '送信回数が多すぎます。しばらくしてからお試しください。' });
    }

    // ハニーポット（人間には見えない隠しフィールド。埋まっていればbotとみなし黙って成功扱い）。
    if (typeof b.company_url === 'string' && b.company_url.trim() !== '') {
      return reply.code(201).send({ ok: true });
    }

    if (!b.email && !b.phone) return reply.code(400).send({ error: 'メールアドレスまたは電話番号を入力してください' });
    if (b.email && !EMAIL_RE.test(String(b.email))) return reply.code(400).send({ error: 'メールアドレスの形式が正しくありません' });

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

  // --- 公開チャットボット（LP用） ---
  app.post('/api/public/chat', async (req, reply) => {
    const ip = (req.headers['x-forwarded-for'] as string)?.split(',')[0]?.trim() || req.ip;
    const rl = rateLimit(`chat:${ip}`, 30, 5 * 60_000);
    if (!rl.allowed) return reply.code(429).send({ reply: '少し時間をおいて再度お試しください。' });

    const b = (req.body ?? {}) as { message?: string; history?: ChatTurn[] };
    const message = (b.message ?? '').toString().slice(0, 1000).trim();
    if (!message) return reply.code(400).send({ error: 'message required' });

    const history = Array.isArray(b.history) ? b.history.slice(-8) : [];
    const text = await salesChatReply(message, history);
    return { reply: text };
  });

  // --- 運営ダッシュボード（経営KPI） ---
  app.get('/api/admin/overview', { preHandler: requireSuperAdmin }, async () => {
    const [tenants, usage, leadStats, meetings, recentLeads, mrr, alerts] = await Promise.all([
      listTenants(),
      getAdminUsageSummary(),
      leads.getLeadStats(),
      leads.getUpcomingMeetings(5),
      leads.getRecentLeads(6),
      committedMrr(),
      renewalAlerts(14),
    ]);
    const byTenantStatus: Record<string, number> = {};
    for (const t of tenants) byTenantStatus[t.status] = (byTenantStatus[t.status] ?? 0) + 1;
    const wonLost = leadStats.won + leadStats.lost;
    return {
      tenants: { total: tenants.length, active: byTenantStatus['active'] ?? 0, trial: byTenantStatus['trial'] ?? 0, by_status: byTenantStatus },
      mrr_jpy: usage.totals.revenue_jpy,             // 当月の見込み売上（利用ベース）
      committed_mrr_jpy: mrr.committed_mrr_jpy,       // 確定MRR（稼働テナントの月額合計）
      arr_jpy: mrr.arr_jpy,
      mrr_by_plan: mrr.by_plan,
      cost_jpy: usage.totals.cost_jpy,
      margin_jpy: usage.totals.margin_jpy,
      calls_this_month: usage.totals.calls,
      leads: leadStats,
      conversion_rate: wonLost > 0 ? Math.round((leadStats.won / wonLost) * 1000) / 10 : 0,
      upcoming_meetings: meetings,
      recent_leads: recentLeads,
      alerts,
      month: usage.month,
    };
  });

  // 売上レポート（確定MRR＋プラン別＋テナント別の当月利用売上）
  app.get('/api/admin/revenue', { preHandler: requireSuperAdmin }, async (req) => {
    const { month } = req.query as Record<string, string>;
    const [mrr, usage] = await Promise.all([committedMrr(), getAdminUsageSummary(month)]);
    return { mrr, usage };
  });

  // テナント別 売上明細CSV
  app.get('/api/admin/revenue/export', { preHandler: requireSuperAdmin }, async (req, reply) => {
    const { month } = req.query as Record<string, string>;
    const usage = await getAdminUsageSummary(month);
    const header = ['会社名', 'プラン', '通話数', '課金対象分', '原価(円)', '売上(円)', '粗利(円)'];
    const rows = usage.tenants.map((t: any) => [
      t.company_name, t.plan ?? '', t.calls, t.billable_minutes,
      Math.round(t.cost?.total_jpy ?? 0), Math.round(t.revenue_jpy ?? 0), Math.round(t.margin_jpy ?? 0),
    ]);
    const esc = (v: unknown) => { const s = String(v ?? ''); return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; };
    const csv = [header, ...rows].map((r) => r.map(esc).join(',')).join('\r\n');
    reply.header('Content-Type', 'text/csv; charset=utf-8');
    reply.header('Content-Disposition', `attachment; filename="revenue_${usage.month}.csv"`);
    return '﻿' + csv; // BOM付きでExcelの文字化け回避
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
