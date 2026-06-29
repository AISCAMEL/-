'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { api, yen } from '@/lib/api';
import { Card, formatDateTime } from '@/components/ui';

const PLANS = [
  { value: 'starter', label: 'Starter（月100分）' },
  { value: 'business', label: 'Business（月500分）' },
  { value: 'pro', label: 'Pro（月1,500分）' },
  { value: 'enterprise', label: 'Enterprise' },
];
const STATUSES = [
  { value: 'trial', label: '試用中' },
  { value: 'active', label: '稼働中' },
  { value: 'suspended', label: '停止中' },
  { value: 'inactive', label: '無効' },
  { value: 'closed', label: '解約' },
];

export default function TenantDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [d, setD] = useState<any>(null);
  const [form, setForm] = useState<any>(null);
  const [msg, setMsg] = useState('');

  function load() {
    api.tenant(id).then((res) => { setD(res); setForm({ ...res.tenant }); }).catch((e) => setMsg(String(e)));
  }
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [id]);

  if (!d || !form) return <p className="text-gray-400">読み込み中…</p>;

  function set(k: string, v: string) { setForm({ ...form, [k]: v }); }

  async function save() {
    setMsg('保存中…');
    try {
      await api.updateTenant(id, {
        company_name: form.company_name, industry: form.industry, plan: form.plan, status: form.status,
        billing_email: form.billing_email, phone: form.phone, address: form.address, memo: form.memo,
        trial_ends_at: form.trial_ends_at || null, contract_started_at: form.contract_started_at || null,
        payment_status: form.payment_status || 'none',
      });
      setMsg('保存しました。');
      load();
    } catch (e: any) { setMsg(`エラー: ${e.message ?? e}`); }
  }
  async function quickStatus(status: string) {
    setMsg('更新中…');
    try { await api.updateTenant(id, { status }); setMsg('更新しました。'); load(); }
    catch (e: any) { setMsg(`エラー: ${e.message ?? e}`); }
  }

  const u = d.usage;

  return (
    <div>
      <Link href="/admin" className="text-sm text-brand hover:underline">← テナント一覧へ戻る</Link>
      <div className="mb-6 mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold">{d.tenant.company_name}</h1>
        <div className="flex gap-2">
          {d.tenant.status !== 'suspended'
            ? <button onClick={() => quickStatus('suspended')} className="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50">利用停止</button>
            : <button onClick={() => quickStatus('active')} className="rounded-lg border border-green-200 px-3 py-1.5 text-sm text-green-700 hover:bg-green-50">利用再開</button>}
        </div>
      </div>
      {msg && <p className="mb-4 text-sm text-brand">{msg}</p>}

      {/* 当月利用サマリ */}
      {u && (
        <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <Mini label="当月通話" value={`${u.calls}件`} />
          <Mini label="利用分数" value={`${u.billable_minutes} / ${u.plan.allowance_min}分`} />
          <Mini label="推定請求" value={yen(u.revenue_jpy)} accent />
          <Mini label="推定原価" value={yen(u.cost.total_jpy)} />
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        {/* 契約情報編集 */}
        <Card className="lg:col-span-2">
          <h2 className="mb-4 text-sm font-semibold text-gray-500">契約情報</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="会社名・店舗名" value={form.company_name ?? ''} onChange={(v) => set('company_name', v)} />
            <Field label="業種" value={form.industry ?? ''} onChange={(v) => set('industry', v)} />
            <div>
              <label className="block text-sm font-medium text-gray-700">プラン</label>
              <select value={form.plan} onChange={(e) => set('plan', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                {PLANS.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">契約ステータス</label>
              <select value={form.status} onChange={(e) => set('status', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                {STATUSES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
              </select>
            </div>
            <Field label="請求先メール" value={form.billing_email ?? ''} onChange={(v) => set('billing_email', v)} />
            <Field label="電話番号" value={form.phone ?? ''} onChange={(v) => set('phone', v)} />
            <div>
              <label className="block text-sm font-medium text-gray-700">トライアル終了日</label>
              <input type="date" value={(form.trial_ends_at ?? '').slice(0, 10)} onChange={(e) => set('trial_ends_at', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">契約開始日</label>
              <input type="date" value={(form.contract_started_at ?? '').slice(0, 10)} onChange={(e) => set('contract_started_at', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">入金ステータス</label>
              <select value={form.payment_status ?? 'none'} onChange={(e) => set('payment_status', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                <option value="none">未請求 / —</option>
                <option value="paid">入金済</option>
                <option value="overdue">滞納</option>
              </select>
            </div>
            <div className="sm:col-span-2">
              <Field label="住所" value={form.address ?? ''} onChange={(v) => set('address', v)} />
            </div>
            <div className="sm:col-span-2">
              <label className="block text-sm font-medium text-gray-700">社内メモ</label>
              <textarea value={form.memo ?? ''} onChange={(e) => set('memo', e.target.value)} rows={2}
                className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
          </div>
          <div className="mt-4">
            <button onClick={save} className="rounded-lg bg-brand px-5 py-2 text-sm font-medium text-white hover:bg-brand-dark">保存</button>
          </div>
        </Card>

        {/* サイド情報 */}
        <div className="space-y-6">
          <Card>
            <h2 className="mb-3 text-sm font-semibold text-gray-500">契約概要</h2>
            <dl className="space-y-2 text-sm">
              <Row label="ユーザー数" value={`${d.user_count}名`} />
              <Row label="電話番号" value={`${d.phone_numbers.length}件`} />
              <Row label="登録日" value={formatDateTime(d.tenant.created_at)} />
              {d.tenant.trial_ends_at && <Row label="試用終了日" value={String(d.tenant.trial_ends_at).slice(0, 10)} />}
              {d.tenant.contract_started_at && <Row label="契約開始日" value={String(d.tenant.contract_started_at).slice(0, 10)} />}
              <Row label="入金状況" value={d.tenant.payment_status === 'overdue' ? '滞納' : d.tenant.payment_status === 'paid' ? '入金済' : '—'} />
            </dl>
          </Card>
          <Card>
            <h2 className="mb-3 text-sm font-semibold text-gray-500">電話番号</h2>
            <div className="space-y-2 text-sm">
              {d.phone_numbers.map((p: any) => (
                <div key={p.id} className="flex items-center justify-between">
                  <span className="font-medium">{p.phone_number}</span>
                  <span className={`rounded-full px-2 py-0.5 text-xs ${p.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>{p.status === 'active' ? '有効' : '停止'}</span>
                </div>
              ))}
              {d.phone_numbers.length === 0 && <p className="text-gray-400">番号未割当</p>}
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}

function Field({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700">{label}</label>
      <input value={value} onChange={(e) => onChange(e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
    </div>
  );
}
function Row({ label, value }: { label: string; value: string }) {
  return <div className="flex justify-between gap-4"><dt className="text-gray-500">{label}</dt><dd>{value}</dd></div>;
}
function Mini({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
  return (
    <Card className={accent ? 'ring-1 ring-brand' : ''}>
      <div className="text-xs text-gray-500">{label}</div>
      <div className={`mt-1 text-xl font-bold ${accent ? 'text-brand' : ''}`}>{value}</div>
    </Card>
  );
}
