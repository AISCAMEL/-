'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, yen, LEAD_STATUS_LABEL, LEAD_CATEGORY_LABEL, PLAN_LABEL, downloadRevenueCsv } from '@/lib/api';
import { Card, PageTitle, formatDateTime } from '@/components/ui';

export default function OverviewPage() {
  const [d, setD] = useState<any>(null);
  const [error, setError] = useState('');

  useEffect(() => { api.overview().then(setD).catch((e) => setError(String(e))); }, []);

  if (error) return <p className="text-red-600">{error}</p>;
  if (!d) return <p className="text-gray-400">読み込み中…</p>;

  return (
    <div>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <PageTitle title="運営ダッシュボード" sub={`${d.month} の事業状況（AIオペレーター24 運営）`} />
        <button onClick={() => downloadRevenueCsv()} className="h-10 rounded-lg border px-4 text-sm text-gray-600 hover:bg-gray-50">売上CSV出力</button>
      </div>

      {/* 契約アラート */}
      {d.alerts?.total > 0 && (
        <Card className="mb-6 border-amber-300 bg-amber-50">
          <h2 className="mb-2 text-sm font-semibold text-amber-800">⚠️ 要対応の契約アラート（{d.alerts.total}件）</h2>
          <div className="space-y-1 text-sm">
            {d.alerts.trial_expired?.map((t: any) => (
              <div key={t.id} className="flex items-center justify-between">
                <span><span className="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-700">試用期限切れ</span> {t.company_name}（{Math.abs(t.days)}日経過）</span>
                <Link href={`/admin/tenants/${t.id}`} className="text-xs text-brand hover:underline">対応 →</Link>
              </div>
            ))}
            {d.alerts.trial_ending?.map((t: any) => (
              <div key={t.id} className="flex items-center justify-between">
                <span><span className="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">試用終了まで{t.days}日</span> {t.company_name}</span>
                <Link href={`/admin/tenants/${t.id}`} className="text-xs text-brand hover:underline">対応 →</Link>
              </div>
            ))}
            {d.alerts.overdue?.map((t: any) => (
              <div key={t.id} className="flex items-center justify-between">
                <span><span className="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-700">入金滞納</span> {t.company_name}</span>
                <Link href={`/admin/tenants/${t.id}`} className="text-xs text-brand hover:underline">対応 →</Link>
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* 売上・テナント KPI */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Kpi label="確定MRR（月額合計）" value={yen(d.committed_mrr_jpy)} sub={`ARR ${yen(d.arr_jpy)}`} accent />
        <Kpi label="当月見込み売上（利用込）" value={yen(d.mrr_jpy)} sub={`粗利 ${yen(d.margin_jpy)}`} />
        <Kpi label="契約テナント" value={`${d.tenants.total}社`} sub={`稼働${d.tenants.active} / 試用${d.tenants.trial}`} />
        <Kpi label="当月通話数" value={`${d.calls_this_month}件`} />
      </div>

      {/* プラン別MRR内訳 */}
      <Card className="mt-4">
        <h2 className="mb-3 text-sm font-semibold text-gray-500">プラン別 確定MRR内訳</h2>
        {(() => {
          const plans = Object.entries(d.mrr_by_plan ?? {}) as [string, any][];
          const max = Math.max(1, ...plans.map(([, v]) => v.mrr_jpy));
          const active = plans.filter(([, v]) => v.count > 0);
          if (active.length === 0) return <p className="text-sm text-gray-400">稼働中の契約がありません。</p>;
          return (
            <div className="space-y-2">
              {active.map(([k, v]) => (
                <div key={k} className="flex items-center gap-2 text-sm">
                  <span className="w-24 shrink-0 text-gray-600">{PLAN_LABEL[k] ?? k}</span>
                  <div className="h-4 flex-1 overflow-hidden rounded bg-gray-100">
                    <div className="h-full rounded bg-brand" style={{ width: `${(v.mrr_jpy / max) * 100}%` }} />
                  </div>
                  <span className="w-12 text-right text-gray-500">{v.count}社</span>
                  <span className="w-24 text-right font-medium">{yen(v.mrr_jpy)}</span>
                </div>
              ))}
            </div>
          );
        })()}
      </Card>

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
