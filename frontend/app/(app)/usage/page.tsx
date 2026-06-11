'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, yen, downloadUsageCsv } from '@/lib/api';
import { getSession } from '@/lib/auth';
import { Card, PageTitle } from '@/components/ui';

export default function UsagePage() {
  const [data, setData] = useState<any>(null);
  const [admin, setAdmin] = useState<any>(null);
  const [error, setError] = useState('');
  const isSuper = getSession()?.role === 'super_admin';

  useEffect(() => {
    if (isSuper) {
      api.adminUsage().then(setAdmin).catch((e) => setError(String(e)));
    } else {
      api.usage().then(setData).catch((e) => setError(String(e)));
    }
  }, [isSuper]);

  if (error) return <p className="text-red-600">{error}</p>;

  // ---- 運営者向け: 全テナント横断 ----
  if (isSuper) {
    if (!admin) return <p className="text-gray-400">読み込み中…</p>;
    return (
      <div>
        <PageTitle title="利用状況・原価（全テナント）" sub={`${admin.month} の集計`} />
        <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <Stat label="通話数" value={admin.totals.calls.toLocaleString()} />
          <Stat label="課金対象分" value={`${admin.totals.billable_minutes.toLocaleString()}分`} />
          <Stat label="推定売上" value={yen(admin.totals.revenue_jpy)} />
          <Stat label="推定原価" value={yen(admin.totals.cost_jpy)} accent />
        </div>
        <Card className="p-0">
          <table className="w-full text-sm">
            <thead className="border-b text-left text-xs text-gray-500">
              <tr><th className="px-4 py-3">テナント</th><th className="px-4 py-3">プラン</th><th className="px-4 py-3 text-right">通話</th><th className="px-4 py-3 text-right">分数</th><th className="px-4 py-3 text-right">売上</th><th className="px-4 py-3 text-right">原価</th><th className="px-4 py-3 text-right">粗利</th></tr>
            </thead>
            <tbody>
              {admin.tenants.map((t: any) => (
                <tr key={t.tenant_id} className="border-b last:border-0">
                  <td className="px-4 py-3 font-medium">{t.company_name}</td>
                  <td className="px-4 py-3 text-gray-600">{t.plan.label}</td>
                  <td className="px-4 py-3 text-right">{t.calls}</td>
                  <td className="px-4 py-3 text-right">{t.billable_minutes}</td>
                  <td className="px-4 py-3 text-right">{yen(t.revenue_jpy)}</td>
                  <td className="px-4 py-3 text-right text-gray-600">{yen(t.cost.total_jpy)}</td>
                  <td className="px-4 py-3 text-right font-medium text-green-700">{yen(t.margin_jpy)}（{t.margin_rate}%）</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      </div>
    );
  }

  // ---- テナント向け ----
  if (!data) return <p className="text-gray-400">読み込み中…</p>;
  const used = data.billable_minutes;
  const allowance = data.plan.allowance_min;
  const pct = Math.min(100, Math.round((used / allowance) * 100));

  return (
    <div>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <PageTitle title="利用状況・原価" sub={`${data.month} の集計（${data.plan.label}プラン）`} />
        <div className="flex gap-2">
          <button onClick={() => downloadUsageCsv()}
            className="h-10 rounded-lg border px-4 text-sm hover:bg-gray-50">明細CSV</button>
          <Link href="/usage/invoice"
            className="flex h-10 items-center rounded-lg bg-brand px-4 text-sm font-medium text-white hover:bg-brand-dark">請求書を表示</Link>
        </div>
      </div>

      <Card className="mb-6">
        <div className="flex items-end justify-between">
          <div>
            <div className="text-xs text-gray-500">今月の利用分数</div>
            <div className="mt-1 text-3xl font-bold">{used}<span className="text-base font-normal text-gray-500"> / {allowance}分</span></div>
          </div>
          <div className="text-right text-sm text-gray-500">
            超過 {data.overage_minutes}分{data.overage_minutes > 0 && `（@¥${data.plan.overage_jpy_per_min}/分）`}
          </div>
        </div>
        <div className="mt-3 h-3 w-full overflow-hidden rounded-full bg-gray-100">
          <div className={`h-full ${pct >= 100 ? 'bg-red-500' : pct >= 80 ? 'bg-amber-500' : 'bg-brand'}`} style={{ width: `${pct}%` }} />
        </div>
      </Card>

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Stat label="通話数" value={data.calls.toLocaleString()} />
        <Stat label="推定請求額" value={yen(data.revenue_jpy)} accent />
        <Stat label="推定原価" value={yen(data.cost.total_jpy)} />
        <Stat label="うち転送原価" value={yen(data.cost.transfer_jpy)} />
      </div>

      <Card className="mt-6">
        <h2 className="mb-3 text-sm font-semibold text-gray-500">内訳</h2>
        <dl className="space-y-2 text-sm">
          <Row label="基本料金" value={yen(data.plan.base_jpy)} />
          <Row label="超過分" value={data.overage_minutes > 0 ? `${data.overage_minutes}分 × ¥${data.plan.overage_jpy_per_min} = ${yen(data.overage_minutes * data.plan.overage_jpy_per_min)}` : '—'} />
          <Row label="AI通話原価" value={`${yen(data.cost.ai_jpy)}（約¥${data.ai_cost_per_min_jpy}/分）`} />
          <Row label="転送追加原価" value={yen(data.cost.transfer_jpy)} />
          <Row label="推定粗利" value={`${yen(data.margin_jpy)}（${data.margin_rate}%）`} strong />
        </dl>
        <p className="mt-4 text-xs text-gray-400">
          ※ 原価は為替 ¥{data.usd_jpy}/USD・標準音声を前提とした概算です。実際の請求はTwilio/OpenAIの実費に基づきます。
        </p>
      </Card>
    </div>
  );
}

function Stat({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
  return (
    <Card className={accent ? 'ring-1 ring-brand' : ''}>
      <div className="text-xs text-gray-500">{label}</div>
      <div className={`mt-1 text-2xl font-bold ${accent ? 'text-brand' : ''}`}>{value}</div>
    </Card>
  );
}

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className={`flex justify-between gap-4 ${strong ? 'border-t pt-2 font-semibold' : ''}`}>
      <dt className="text-gray-500">{label}</dt>
      <dd className="text-right">{value}</dd>
    </div>
  );
}
