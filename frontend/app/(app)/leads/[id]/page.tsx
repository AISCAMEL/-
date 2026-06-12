'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { api, LEAD_STATUS_LABEL, LEAD_CATEGORY_LABEL } from '@/lib/api';
import { Card, formatDateTime } from '@/components/ui';

const MAIL_STATUS_LABEL: Record<string, string> = { pending: '送信予定', sent: '送信済', failed: '失敗', canceled: '取消' };

export default function LeadDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [lead, setLead] = useState<any>(null);
  const [note, setNote] = useState('');
  const [msg, setMsg] = useState('');
  const [mtg, setMtg] = useState({ title: '商談・ご相談', scheduled_at: '', meeting_url: '' });
  const [mail, setMail] = useState({ subject: '', body: '' });

  function load() { api.lead(id).then(setLead).catch((e) => setMsg(String(e))); }
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [id]);

  if (!lead) return <p className="text-gray-400">読み込み中…</p>;

  async function setStatus(status: string) { await api.updateLead(id, { status }); load(); }
  async function setCategory(category: string) { await api.updateLead(id, { category }); load(); }
  async function addNote(e: React.FormEvent) { e.preventDefault(); if (!note.trim()) return; await api.addLeadNote(id, note); setNote(''); load(); }
  async function addMeeting(e: React.FormEvent) {
    e.preventDefault();
    await api.createMeeting(id, { title: mtg.title, scheduled_at: mtg.scheduled_at || undefined, meeting_url: mtg.meeting_url || undefined });
    setMtg({ title: '商談・ご相談', scheduled_at: '', meeting_url: '' });
    load();
  }
  async function sendMail(e: React.FormEvent) {
    e.preventDefault();
    if (!mail.subject || !mail.body) return;
    setMsg('送信中…');
    const r = await api.sendLeadEmail(id, mail.subject, mail.body);
    setMsg(r.ok ? 'メールを送信しました。' : `送信失敗: ${r.error}`);
    setMail({ subject: '', body: '' });
    load();
  }

  return (
    <div>
      <Link href="/leads" className="text-sm text-brand hover:underline">← 問い合わせ一覧へ戻る</Link>
      <div className="mb-6 mt-2 flex flex-wrap items-center gap-3">
        <h1 className="text-2xl font-bold">{lead.name || '（無名）'}</h1>
        {lead.company && <span className="text-gray-500">{lead.company}</span>}
      </div>
      {msg && <p className="mb-4 text-sm text-brand">{msg}</p>}

      <div className="grid gap-6 lg:grid-cols-3">
        {/* 左: 基本情報・ステータス */}
        <div className="space-y-6 lg:col-span-1">
          <Card>
            <h2 className="mb-3 text-sm font-semibold text-gray-500">連絡先・内容</h2>
            <dl className="space-y-2 text-sm">
              <Row label="メール" value={lead.email} />
              <Row label="電話" value={lead.phone} />
              <Row label="業種" value={lead.industry} />
              <Row label="流入元" value={lead.source} />
              <Row label="受信日時" value={formatDateTime(lead.created_at)} />
            </dl>
            <div className="mt-3 rounded-lg bg-gray-50 p-3 text-sm">{lead.message || '（本文なし）'}</div>
          </Card>

          <Card>
            <h2 className="mb-2 text-sm font-semibold text-gray-500">ステータス</h2>
            <select value={lead.status} onChange={(e) => setStatus(e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm">
              {Object.entries(LEAD_STATUS_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
            <h2 className="mb-2 mt-4 text-sm font-semibold text-gray-500">種別</h2>
            <select value={lead.category} onChange={(e) => setCategory(e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm">
              {Object.entries(LEAD_CATEGORY_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
            <p className="mt-3 text-xs text-gray-400">受注/失注/クローズにすると、未送信のステップメールは自動で停止します。</p>
          </Card>
        </div>

        {/* 中: 商談・メモ */}
        <div className="space-y-6 lg:col-span-1">
          <Card>
            <h2 className="mb-3 text-sm font-semibold text-gray-500">商談・ミーティング</h2>
            <div className="space-y-2">
              {lead.meetings?.map((m: any) => (
                <div key={m.id} className="rounded-lg border p-2 text-sm">
                  <div className="font-medium">{m.title}</div>
                  <div className="text-xs text-gray-500">{m.scheduled_at ? formatDateTime(m.scheduled_at) : '日時調整中'} ・ {m.status}</div>
                  {m.meeting_url && <a href={m.meeting_url} className="text-xs text-brand hover:underline">{m.meeting_url}</a>}
                </div>
              ))}
              {(!lead.meetings || lead.meetings.length === 0) && <p className="text-sm text-gray-400">予定はありません。</p>}
            </div>
            <form onSubmit={addMeeting} className="mt-3 space-y-2">
              <input value={mtg.title} onChange={(e) => setMtg({ ...mtg, title: e.target.value })} placeholder="件名"
                className="w-full rounded-lg border px-3 py-2 text-sm" />
              <input type="datetime-local" value={mtg.scheduled_at} onChange={(e) => setMtg({ ...mtg, scheduled_at: e.target.value })}
                className="w-full rounded-lg border px-3 py-2 text-sm" />
              <input value={mtg.meeting_url} onChange={(e) => setMtg({ ...mtg, meeting_url: e.target.value })} placeholder="会議URL（任意）"
                className="w-full rounded-lg border px-3 py-2 text-sm" />
              <button className="w-full rounded-lg bg-brand py-2 text-sm font-medium text-white hover:bg-brand-dark">商談を追加</button>
            </form>
          </Card>

          <Card>
            <h2 className="mb-3 text-sm font-semibold text-gray-500">対応メモ</h2>
            <div className="space-y-2">
              {lead.notes?.map((n: any) => (
                <div key={n.id} className="rounded-lg bg-gray-50 px-3 py-2 text-sm">
                  <div className="text-xs text-gray-400">{formatDateTime(n.created_at)}</div>{n.note}
                </div>
              ))}
              {(!lead.notes || lead.notes.length === 0) && <p className="text-sm text-gray-400">メモはありません。</p>}
            </div>
            <form onSubmit={addNote} className="mt-3 flex gap-2">
              <input value={note} onChange={(e) => setNote(e.target.value)} placeholder="メモを追加" className="flex-1 rounded-lg border px-3 py-2 text-sm" />
              <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">追加</button>
            </form>
          </Card>
        </div>

        {/* 右: ステップメール・手動送信 */}
        <div className="space-y-6 lg:col-span-1">
          <Card>
            <h2 className="mb-3 text-sm font-semibold text-gray-500">ステップメール</h2>
            <div className="space-y-2">
              {lead.emails?.map((e: any) => (
                <div key={e.id} className="flex items-start justify-between gap-2 rounded-lg border p-2 text-sm">
                  <div>
                    <div className="font-medium">{e.step_no === 0 ? '手動送信' : `STEP ${e.step_no}`}</div>
                    <div className="text-xs text-gray-500">{e.subject}</div>
                    <div className="text-xs text-gray-400">{e.status === 'sent' ? `送信: ${formatDateTime(e.sent_at)}` : `予定: ${formatDateTime(e.scheduled_at)}`}</div>
                  </div>
                  <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs ${
                    e.status === 'sent' ? 'bg-green-100 text-green-800' : e.status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500'}`}>
                    {MAIL_STATUS_LABEL[e.status] ?? e.status}
                  </span>
                </div>
              ))}
              {(!lead.emails || lead.emails.length === 0) && <p className="text-sm text-gray-400">メール予約はありません。</p>}
            </div>
          </Card>

          <Card>
            <h2 className="mb-3 text-sm font-semibold text-gray-500">個別メール送信</h2>
            <form onSubmit={sendMail} className="space-y-2">
              <input value={mail.subject} onChange={(e) => setMail({ ...mail, subject: e.target.value })} placeholder="件名"
                className="w-full rounded-lg border px-3 py-2 text-sm" />
              <textarea value={mail.body} onChange={(e) => setMail({ ...mail, body: e.target.value })} rows={4} placeholder="本文"
                className="w-full rounded-lg border px-3 py-2 text-sm" />
              <button className="w-full rounded-lg bg-brand py-2 text-sm font-medium text-white hover:bg-brand-dark">送信</button>
            </form>
          </Card>
        </div>
      </div>
    </div>
  );
}

function Row({ label, value }: { label: string; value?: string | null }) {
  return (
    <div className="flex justify-between gap-4">
      <dt className="shrink-0 text-gray-500">{label}</dt>
      <dd className="break-all text-right">{value || '—'}</dd>
    </div>
  );
}
