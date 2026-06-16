'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, yen } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

export default function BillingPage() {
  const [b, setB] = useState<any>(null);
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);

  function load() { api.billing().then(setB).catch((e) => setMsg(String(e))); }
  useEffect(() => { load(); }, []);

  if (!b) return <p className="text-gray-400">読み込み中…</p>;

  async function createOverage() {
    setBusy(true); setMsg('');
    try {
      const r = await api.invoiceOverage();
      setMsg(r.message);
    } catch (e: any) {
      setMsg(`エラー: ${e.message ?? e}`);
    } finally { setBusy(false); }
  }

  return (
    <div>
      <PageTitle title="お支払い" sub="ご契約プランと当月のご請求です。" />

      {!b.enabled && (
        <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          現在は<strong>デモ表示</strong>です。Square を接続すると、カード登録・自動課金・超過請求が有効になります。
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-3">
        <Card>
          <div className="text-xs text-gray-500">ご契約プラン</div>
          <div className="mt-1 text-2xl font-bold">{b.plan.label}</div>
          <div className="mt-0.5 text-xs text-gray-400">月額 {yen(b.plan.base_jpy)}／{b.plan.allowance_min}分まで</div>
        </Card>
        <Card>
          <div className="text-xs text-gray-500">今月の利用</div>
          <div className="mt-1 text-2xl font-bold">{b.usage.billable_minutes}<span className="text-base font-normal text-gray-500">分</span></div>
          <div className="mt-0.5 text-xs text-gray-400">超過 {b.usage.overage_minutes}分（@¥{b.plan.overage_jpy_per_min}/分）</div>
        </Card>
        <Card className="ring-1 ring-brand">
          <div className="text-xs text-gray-500">当月ご請求（税込・見込み）</div>
          <div className="mt-1 text-2xl font-bold text-brand">{yen(b.this_month_total_jpy)}</div>
          <Link href="/usage/invoice" className="mt-0.5 inline-block text-xs text-brand hover:underline">請求書を表示 →</Link>
        </Card>
      </div>

      <Card className="mt-6">
        <h2 className="text-sm font-semibold text-gray-500">お支払い方法</h2>
        <p className="mt-2 text-sm text-gray-600">
          {b.square_customer_id
            ? 'カード登録済み（Square）。'
            : 'カード未登録です。Square接続後、ここからカードを登録できます。'}
        </p>
        <button disabled className="mt-3 cursor-not-allowed rounded-lg border px-4 py-2 text-sm text-gray-400">
          カードを登録（Square接続後に有効）
        </button>
      </Card>

      <Card className="mt-6">
        <h2 className="text-sm font-semibold text-gray-500">超過分の請求書を作成</h2>
        <p className="mt-2 text-sm text-gray-600">当月の超過通話分について、Squareの請求書を作成します。</p>
        <button onClick={createOverage} disabled={busy}
          className="mt-3 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark disabled:opacity-50">
          {busy ? '処理中…' : '超過請求書を作成'}
        </button>
        {msg && <p className="mt-3 text-sm text-brand">{msg}</p>}
      </Card>

      <p className="mt-4 text-xs text-gray-400">
        ※ 月額固定はSquareサブスクリプション、超過分は月末に請求書で精算する設計です（詳細は運営にお問い合わせください）。
      </p>
    </div>
  );
}
