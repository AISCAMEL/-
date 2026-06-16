'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, CATEGORY_LABEL } from '@/lib/api';
import { Card, PageTitle, StatusBadge, CategoryTag, formatDateTime, formatDuration } from '@/components/ui';

export default function DashboardPage() {
  const [data, setData] = useState<any>(null);
  const [error, setError] = useState('');
  const [digestMsg, setDigestMsg] = useState('');

  useEffect(() => {
    api.dashboard().then(setData).catch((e) => setError(String(e)));
  }, []);

  async function sendWeekly() {
    setDigestMsg('送信中…');
    try {
      const r = await api.weeklyDigest();
      setDigestMsg(r.ok ? `今週のサマリーを送信しました（${r.destination}）` : `送信失敗: ${r.error}`);
    } catch (e: any) {
      setDigestMsg(`送信失敗: ${e.message ?? e}`);
    }
  }

  if (error) return <p className="text-red-600">{error}</p>;
  if (!data) return <p className="text-gray-400">読み込み中…</p>;

  const stats = [
    { label: '本日の着信', value: data.calls_today, accent: true },
    { label: '今月の通話', value: data.calls_this_month },
    { label: 'AI対応完了', value: data.completed_count },
    { label: '折り返し', value: data.callback_count },
    { label: '転送', value: data.transfer_count },
    { label: '未対応', value: data.unhandled_count, warn: data.unhandled_count > 0 },
    { label: 'AI対応率', value: `${data.calls_today > 0 ? Math.round((data.completed_count / data.calls_today) * 100) : 0}%`, text: true },
    { label: '平均通話時間', value: formatDuration(data.avg_duration_sec), text: true },
  ];

  return (
    <div>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <PageTitle title="ダッシュボード" sub="電話受付の状況をひと目で。" />
        <button onClick={sendWeekly} className="h-10 rounded-lg border px-4 text-sm text-gray-600 hover:bg-gray-50">
          今週のサマリーをメール送信
        </button>
      </div>
      {digestMsg && <p className="mb-3 text-sm text-brand">{digestMsg}</p>}

      {/* 初期設定への導線 */}
      <Link href="/onboarding" className="mb-6 flex items-center justify-between rounded-xl border border-brand/30 bg-brand-light px-4 py-3 text-sm hover:bg-brand-light/70">
        <span className="font-medium text-brand">🚀 はじめての方へ：かんたん初期設定（挨拶文・転送先・FAQ）</span>
        <span className="text-brand">設定する →</span>
      </Link>

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

      {/* グラフ */}
      <div className="mt-8 grid gap-6 lg:grid-cols-2">
        <Card>
          <h2 className="mb-3 text-sm font-semibold text-gray-500">要件の内訳（今月）</h2>
          <CategoryChart data={data.by_category ?? {}} />
        </Card>
        <Card>
          <h2 className="mb-3 text-sm font-semibold text-gray-500">時間帯別の着信（今月）</h2>
          <HourChart data={data.by_hour ?? []} />
        </Card>
      </div>

      {/* タグ別（今月） */}
      {Object.keys(data.by_tag ?? {}).length > 0 && (
        <Card className="mt-6">
          <h2 className="mb-3 text-sm font-semibold text-gray-500">タグ別（今月）</h2>
          <div className="flex flex-wrap gap-2">
            {Object.entries(data.by_tag).sort((a: any, b: any) => b[1] - a[1]).map(([tag, n]: any) => (
              <Link key={tag} href={`/calls?tag=${encodeURIComponent(tag)}`}
                className="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm hover:bg-gray-50">
                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{tag}</span>
                <span className="font-semibold">{n}件</span>
              </Link>
            ))}
          </div>
        </Card>
      )}

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

// 要件カテゴリの横棒グラフ
function CategoryChart({ data }: { data: Record<string, number> }) {
  const entries = Object.entries(data).sort((a, b) => b[1] - a[1]);
  const max = Math.max(1, ...entries.map(([, n]) => n));
  if (entries.length === 0) return <p className="text-sm text-gray-400">データがありません。</p>;
  return (
    <div className="space-y-2">
      {entries.map(([cat, n]) => (
        <div key={cat} className="flex items-center gap-2 text-sm">
          <span className="w-20 shrink-0 text-gray-600">{CATEGORY_LABEL[cat] ?? cat}</span>
          <div className="h-4 flex-1 overflow-hidden rounded bg-gray-100">
            <div className="h-full rounded bg-brand" style={{ width: `${(n / max) * 100}%` }} />
          </div>
          <span className="w-6 text-right text-gray-500">{n}</span>
        </div>
      ))}
    </div>
  );
}

// 時間帯別の縦棒グラフ（0-23時）
function HourChart({ data }: { data: number[] }) {
  const hours = data.length === 24 ? data : Array.from({ length: 24 }, () => 0);
  const max = Math.max(1, ...hours);
  const total = hours.reduce((s, n) => s + n, 0);
  if (total === 0) return <p className="text-sm text-gray-400">データがありません。</p>;
  return (
    <div>
      <div className="flex h-32 items-end gap-0.5">
        {hours.map((n, h) => (
          <div key={h} className="flex flex-1 flex-col items-center justify-end" title={`${h}時: ${n}件`}>
            <div className="w-full rounded-t bg-brand" style={{ height: `${(n / max) * 100}%`, minHeight: n > 0 ? 3 : 0 }} />
          </div>
        ))}
      </div>
      <div className="mt-1 flex justify-between text-[10px] text-gray-400">
        <span>0時</span><span>6時</span><span>12時</span><span>18時</span><span>23時</span>
      </div>
    </div>
  );
}
