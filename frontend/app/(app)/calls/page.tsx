'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, CATEGORY_LABEL, STATUS_LABEL, downloadCallsCsv } from '@/lib/api';
import { Card, PageTitle, StatusBadge, CategoryTag, formatDateTime, formatDuration } from '@/components/ui';

export default function CallsPage() {
  const [calls, setCalls] = useState<any[]>([]);
  const [status, setStatus] = useState('');
  const [category, setCategory] = useState('');
  const [q, setQ] = useState('');
  const [loading, setLoading] = useState(true);

  function load() {
    setLoading(true);
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (category) params.set('category', category);
    if (q) params.set('q', q);
    const qs = params.toString();
    api.calls(qs ? `?${qs}` : '').then(setCalls).finally(() => setLoading(false));
  }

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [status, category]);

  return (
    <div>
      <PageTitle title="通話履歴" sub="AIが受けた電話の一覧です。" />

      <div className="mb-4 flex flex-wrap gap-3">
        <select value={status} onChange={(e) => setStatus(e.target.value)}
          className="rounded-lg border px-3 py-2 text-sm">
          <option value="">すべてのステータス</option>
          {Object.entries(STATUS_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <select value={category} onChange={(e) => setCategory(e.target.value)}
          className="rounded-lg border px-3 py-2 text-sm">
          <option value="">すべての要件</option>
          {Object.entries(CATEGORY_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <form onSubmit={(e) => { e.preventDefault(); load(); }} className="flex gap-2">
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="氏名・番号・要約で検索"
            className="rounded-lg border px-3 py-2 text-sm" />
          <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">検索</button>
        </form>
        <button type="button" onClick={() => {
          const p = new URLSearchParams();
          if (status) p.set('status', status);
          if (category) p.set('category', category);
          if (q) p.set('q', q);
          const s = p.toString();
          downloadCallsCsv(s ? `?${s}` : '');
        }} className="ml-auto rounded-lg border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">CSVエクスポート</button>
      </div>

      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr>
              <th className="px-4 py-3">日時</th>
              <th className="px-4 py-3">発信者</th>
              <th className="px-4 py-3">顧客名/会社</th>
              <th className="px-4 py-3">要件</th>
              <th className="px-4 py-3">要約</th>
              <th className="px-4 py-3">時間</th>
              <th className="px-4 py-3">ステータス</th>
            </tr>
          </thead>
          <tbody>
            {loading && <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">読み込み中…</td></tr>}
            {!loading && calls.length === 0 && (
              <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">該当する通話がありません。</td></tr>
            )}
            {calls.map((c) => (
              <tr key={c.id} className="border-b last:border-0 hover:bg-gray-50">
                <td className="whitespace-nowrap px-4 py-3 text-gray-600">{formatDateTime(c.started_at)}</td>
                <td className="whitespace-nowrap px-4 py-3">{c.from_number}</td>
                <td className="px-4 py-3">{c.customer_name ?? '—'}{c.company_name ? ` / ${c.company_name}` : ''}</td>
                <td className="px-4 py-3"><CategoryTag category={c.category} /></td>
                <td className="max-w-xs truncate px-4 py-3 text-gray-600">{c.summary ?? '—'}</td>
                <td className="whitespace-nowrap px-4 py-3 text-gray-600">{formatDuration(c.duration_sec)}</td>
                <td className="px-4 py-3">
                  <Link href={`/calls/${c.id}`} className="inline-block"><StatusBadge status={c.status} /></Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
