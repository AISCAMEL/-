'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { Card, PageTitle, StatusBadge, CategoryTag, formatDateTime, formatDuration } from '@/components/ui';

export default function DashboardPage() {
  const [data, setData] = useState<any>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.dashboard().then(setData).catch((e) => setError(String(e)));
  }, []);

  if (error) return <p className="text-red-600">{error}</p>;
  if (!data) return <p className="text-gray-400">読み込み中…</p>;

  const stats = [
    { label: '本日の着信', value: data.calls_today, accent: true },
    { label: '今月の通話', value: data.calls_this_month },
    { label: 'AI対応完了', value: data.completed_count },
    { label: '折り返し', value: data.callback_count },
    { label: '転送', value: data.transfer_count },
    { label: '未対応', value: data.unhandled_count, warn: data.unhandled_count > 0 },
    { label: '平均通話時間', value: formatDuration(data.avg_duration_sec), text: true },
  ];

  return (
    <div>
      <PageTitle title="ダッシュボード" sub="電話受付の状況をひと目で。" />

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {stats.map((s) => (
          <Card key={s.label} className={s.accent ? 'ring-1 ring-brand' : ''}>
            <div className="text-xs text-gray-500">{s.label}</div>
            <div className={`mt-1 text-2xl font-bold ${s.warn ? 'text-red-600' : s.accent ? 'text-brand' : ''} ${s.text ? 'text-lg' : ''}`}>
              {s.value}
            </div>
          </Card>
        ))}
      </div>

      <h2 className="mb-3 mt-8 text-lg font-semibold">最近の通話</h2>
      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr>
              <th className="px-4 py-3">日時</th>
              <th className="px-4 py-3">発信者</th>
              <th className="px-4 py-3">要件</th>
              <th className="px-4 py-3">ステータス</th>
              <th className="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {data.recent.map((c: any) => (
              <tr key={c.id} className="border-b last:border-0 hover:bg-gray-50">
                <td className="px-4 py-3 text-gray-600">{formatDateTime(c.started_at)}</td>
                <td className="px-4 py-3">{c.customer_name ?? c.from_number}</td>
                <td className="px-4 py-3"><CategoryTag category={c.category} /></td>
                <td className="px-4 py-3"><StatusBadge status={c.status} /></td>
                <td className="px-4 py-3 text-right">
                  <Link href={`/calls/${c.id}`} className="text-brand hover:underline">詳細</Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
