'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, LEAD_STATUS_LABEL, LEAD_CATEGORY_LABEL } from '@/lib/api';
import { Card, PageTitle, formatDateTime } from '@/components/ui';

const STATUS_COLOR: Record<string, string> = {
  new: 'bg-amber-100 text-amber-800', contacted: 'bg-blue-100 text-blue-800',
  in_progress: 'bg-gray-100 text-gray-700', meeting_scheduled: 'bg-purple-100 text-purple-800',
  won: 'bg-green-100 text-green-800', lost: 'bg-red-100 text-red-800', closed: 'bg-gray-100 text-gray-500',
};

export default function LeadsPage() {
  const [leads, setLeads] = useState<any[]>([]);
  const [status, setStatus] = useState('');
  const [q, setQ] = useState('');
  const [loading, setLoading] = useState(true);

  function load() {
    setLoading(true);
    const p = new URLSearchParams();
    if (status) p.set('status', status);
    if (q) p.set('q', q);
    const qs = p.toString();
    api.leads(qs ? `?${qs}` : '').then(setLeads).finally(() => setLoading(false));
  }
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [status]);

  return (
    <div>
      <PageTitle title="問い合わせ管理" sub="LP・資料請求・デモ希望などのリードを一元管理します。" />

      <div className="mb-4 flex flex-wrap gap-3">
        <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded-lg border px-3 py-2 text-sm">
          <option value="">すべてのステータス</option>
          {Object.entries(LEAD_STATUS_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <form onSubmit={(e) => { e.preventDefault(); load(); }} className="flex gap-2">
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="氏名・会社・メールで検索"
            className="rounded-lg border px-3 py-2 text-sm" />
          <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">検索</button>
        </form>
      </div>

      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr>
              <th className="px-4 py-3">受信日時</th>
              <th className="px-4 py-3">氏名 / 会社</th>
              <th className="px-4 py-3">種別</th>
              <th className="px-4 py-3">連絡先</th>
              <th className="px-4 py-3">内容</th>
              <th className="px-4 py-3">ステータス</th>
            </tr>
          </thead>
          <tbody>
            {loading && <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">読み込み中…</td></tr>}
            {!loading && leads.length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">問い合わせがありません。</td></tr>}
            {leads.map((l) => (
              <tr key={l.id} className="border-b last:border-0 hover:bg-gray-50">
                <td className="whitespace-nowrap px-4 py-3 text-gray-600">{formatDateTime(l.created_at)}</td>
                <td className="px-4 py-3"><Link href={`/leads/${l.id}`} className="font-medium text-brand hover:underline">{l.name || '（無名）'}</Link>{l.company ? ` / ${l.company}` : ''}</td>
                <td className="px-4 py-3 text-gray-600">{LEAD_CATEGORY_LABEL[l.category] ?? l.category}</td>
                <td className="px-4 py-3 text-gray-600">{l.email || l.phone || '—'}</td>
                <td className="max-w-xs truncate px-4 py-3 text-gray-600">{l.message ?? '—'}</td>
                <td className="px-4 py-3">
                  <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_COLOR[l.status] ?? 'bg-gray-100'}`}>
                    {LEAD_STATUS_LABEL[l.status] ?? l.status}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
