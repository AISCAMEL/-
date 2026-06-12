'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, yen, LEAD_STATUS_LABEL, LEAD_CATEGORY_LABEL } from '@/lib/api';
import { Card, PageTitle, formatDateTime } from '@/components/ui';

export default function OverviewPage() {
  const [d, setD] = useState<any>(null);
  const [error, setError] = useState('');

  useEffect(() => { api.overview().then(setD).catch((e) => setError(String(e))); }, []);

  if (error) return <p className="text-red-600">{error}</p>;
  if (!d) return <p className="text-gray-400">読み込み中…</p>;

  return (
    <div>
      <PageTitle title="運営ダッシュボード" sub={`${d.month} の事業状況（AIオペレーター24 運営）`} />

      {/* 売上・テナント KPI */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Kpi label="MRR（当月見込み）" value={yen(d.mrr_jpy)} accent />
        <Kpi label="粗利（当月）" value={yen(d.margin_jpy)} sub={`原価 ${yen(d.cost_jpy)}`} />
        <Kpi label="契約テナント" value={`${d.tenants.total}社`} sub={`稼働${d.tenants.active} / 試用${d.tenants.trial}`} />
        <Kpi label="当月通話数" value={`${d.calls_this_month}件`} />
      </div>

      {/* リード KPI */}
      <h2 className="mb-3 mt-8 text-lg font-semibold">問い合わせ・商談</h2>
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Kpi label="リード総数" value={`${d.leads.total}件`} />
        <Kpi label="今月の新規リード" value={`${d.leads.this_month}件`} accent />
        <Kpi label="未対応リード" value={`${d.leads.new}件`} warn={d.leads.new > 0} />
        <Kpi label="成約率" value={`${d.conversion_rate}%`} sub={`受注${d.leads.won} / 失注${d.leads.lost}`} />
      </div>

      <div className="mt-8 grid gap-6 lg:grid-cols-2">
        {/* 最近のリード */}
        <div>
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-lg font-semibold">最近の問い合わせ</h2>
            <Link href="/leads" className="text-sm text-brand hover:underline">すべて見る →</Link>
          </div>
          <Card className="p-0">
            <table className="w-full text-sm">
              <tbody>
                {d.recent_leads.map((l: any) => (
                  <tr key={l.id} className="border-b last:border-0 hover:bg-gray-50">
                    <td className="px-4 py-3">
                      <Link href={`/leads/${l.id}`} className="font-medium text-brand hover:underline">{l.name || '（無名）'}</Link>
                      <div className="text-xs text-gray-500">{l.company || '—'} ・ {LEAD_CATEGORY_LABEL[l.category] ?? l.category}</div>
                    </td>
                    <td className="px-4 py-3 text-right text-xs text-gray-500">{formatDateTime(l.created_at)}</td>
                    <td className="px-4 py-3 text-right"><span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{LEAD_STATUS_LABEL[l.status] ?? l.status}</span></td>
                  </tr>
                ))}
                {d.recent_leads.length === 0 && <tr><td className="px-4 py-6 text-center text-gray-400">まだありません。</td></tr>}
              </tbody>
            </table>
          </Card>
        </div>

        {/* 今後の商談 */}
        <div>
          <h2 className="mb-3 text-lg font-semibold">今後の商談・ミーティング</h2>
          <Card className="p-0">
            <table className="w-full text-sm">
              <tbody>
                {d.upcoming_meetings.map((m: any) => (
                  <tr key={m.id} className="border-b last:border-0 hover:bg-gray-50">
                    <td className="px-4 py-3">
                      <Link href={`/leads/${m.lead_id}`} className="font-medium text-brand hover:underline">{m.title}</Link>
                      <div className="text-xs text-gray-500">{m.lead_name || '—'}{m.company ? ` / ${m.company}` : ''}</div>
                    </td>
                    <td className="px-4 py-3 text-right text-xs text-gray-600">{formatDateTime(m.scheduled_at)}</td>
                  </tr>
                ))}
                {d.upcoming_meetings.length === 0 && <tr><td className="px-4 py-6 text-center text-gray-400">予定された商談はありません。</td></tr>}
              </tbody>
            </table>
          </Card>
        </div>
      </div>
    </div>
  );
}

function Kpi({ label, value, sub, accent, warn }: { label: string; value: string; sub?: string; accent?: boolean; warn?: boolean }) {
  return (
    <Card className={accent ? 'ring-1 ring-brand' : ''}>
      <div className="text-xs text-gray-500">{label}</div>
      <div className={`mt-1 text-2xl font-bold ${warn ? 'text-red-600' : accent ? 'text-brand' : ''}`}>{value}</div>
      {sub && <div className="mt-0.5 text-xs text-gray-400">{sub}</div>}
    </Card>
  );
}
