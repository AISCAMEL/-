'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { getSession } from '@/lib/auth';
import { Card, PageTitle } from '@/components/ui';
import { PLAN_LABEL, TENANT_STATUS_LABEL, PAYMENT_STATUS_LABEL } from '@/lib/api';

const STATUS_COLOR: Record<string, string> = {
  active: 'bg-green-100 text-green-800', trial: 'bg-amber-100 text-amber-800',
  inactive: 'bg-gray-100 text-gray-500', suspended: 'bg-red-100 text-red-700', closed: 'bg-gray-100 text-gray-400',
};
function trialBadge(t: any) {
  if (t.status !== 'trial' || !t.trial_ends_at) return null;
  const days = Math.ceil((new Date(t.trial_ends_at + 'T23:59:59+09:00').getTime() - Date.now()) / 86400000);
  const cls = days < 0 ? 'text-red-600' : days <= 7 ? 'text-amber-600' : 'text-gray-400';
  return <span className={`ml-2 text-xs ${cls}`}>{days < 0 ? `期限切れ${Math.abs(days)}日` : `あと${days}日`}</span>;
}

// Super Admin: 全テナント一覧と新規追加（MVP）。
export default function AdminPage() {
  const [tenants, setTenants] = useState<any[]>([]);
  const [form, setForm] = useState({ company_name: '', industry: '', plan: 'business', owner_email: '', owner_name: '' });
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function load() {
    const s = getSession();
    const res = await fetch(`${process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8080'}/api/admin/tenants`, {
      headers: { Authorization: `Bearer ${s?.token}`, 'x-role': 'super_admin' },
    });
    if (!res.ok) { setError(`${res.status}`); return; }
    setTenants(await res.json());
  }
  useEffect(() => { load().catch((e) => setError(String(e))); }, []);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    if (!form.company_name.trim()) return;
    setBusy(true); setMsg('');
    const s = getSession();
    try {
      const res = await fetch(`${process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8080'}/api/admin/tenants`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${s?.token}`, 'x-role': 'super_admin' },
        body: JSON.stringify(form),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) { setMsg(`エラー: ${data.error ?? res.status}`); }
      else if (form.owner_email) {
        setMsg(data?.invite?.sent
          ? `✅ 「${form.company_name}」を追加し、${form.owner_email} に招待メールを送信しました。`
          : `店舗は追加しましたが、招待メール送信に失敗しました（${data?.invite?.error ?? '原因不明'}）。`);
      } else {
        setMsg(`✅ 「${form.company_name}」を追加しました（オーナー招待は未送信）。`);
      }
      setForm({ company_name: '', industry: '', plan: 'business', owner_email: '', owner_name: '' });
      load();
    } catch (err: any) {
      setMsg(`エラー: ${err.message ?? err}`);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <PageTitle title="Super Admin" sub="全テナントの管理（運営者専用）。" />
      {error && <p className="mb-4 text-sm text-red-600">権限がないか、エラーが発生しました（{error}）。</p>}

      <Card className="mb-6">
        <h2 className="mb-3 text-sm font-semibold text-gray-600">新規店舗を追加（オーナーへ招待メール送信）</h2>
        <form onSubmit={create} className="space-y-2">
          <div className="flex flex-wrap gap-2">
            <input value={form.company_name} onChange={(e) => setForm({ ...form, company_name: e.target.value })} placeholder="会社名・店舗名 *"
              className="min-w-[180px] flex-1 rounded-lg border px-3 py-2 text-sm" />
            <input value={form.industry} onChange={(e) => setForm({ ...form, industry: e.target.value })} placeholder="業種（例：車買取）"
              className="w-40 rounded-lg border px-3 py-2 text-sm" />
            <select value={form.plan} onChange={(e) => setForm({ ...form, plan: e.target.value })} className="rounded-lg border px-3 py-2 text-sm">
              <option value="starter">受付プラン</option>
              <option value="business">統合プラン</option>
              <option value="pro">プロプラン</option>
            </select>
          </div>
          <div className="flex flex-wrap gap-2">
            <input value={form.owner_name} onChange={(e) => setForm({ ...form, owner_name: e.target.value })} placeholder="オーナー氏名"
              className="w-44 rounded-lg border px-3 py-2 text-sm" />
            <input type="email" value={form.owner_email} onChange={(e) => setForm({ ...form, owner_email: e.target.value })} placeholder="オーナーのメール（招待送信先）"
              className="min-w-[220px] flex-1 rounded-lg border px-3 py-2 text-sm" />
            <button disabled={busy || !form.company_name.trim()} className="rounded-lg bg-brand px-5 py-2 text-sm font-medium text-white hover:bg-brand-dark disabled:opacity-50">
              {busy ? '追加中…' : '追加して招待'}
            </button>
          </div>
          <p className="text-xs text-gray-400">メールを入れると、オーナーに「パスワード設定リンク」を自動送信します（空なら店舗だけ作成）。</p>
          {msg && <p className="text-sm text-brand">{msg}</p>}
        </form>
      </Card>

      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr><th className="px-4 py-3">会社名</th><th className="px-4 py-3">業種</th><th className="px-4 py-3">プラン</th><th className="px-4 py-3">状態</th><th className="px-4 py-3">入金</th></tr>
          </thead>
          <tbody>
            {tenants.map((t) => (
              <tr key={t.id} className="border-b last:border-0 hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">
                  <Link href={`/admin/tenants/${t.id}`} className="text-brand hover:underline">{t.company_name}</Link>
                  {trialBadge(t)}
                </td>
                <td className="px-4 py-3 text-gray-600">{t.industry ?? '—'}</td>
                <td className="px-4 py-3 text-gray-600">{PLAN_LABEL[t.plan] ?? t.plan}</td>
                <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs ${STATUS_COLOR[t.status] ?? 'bg-gray-100'}`}>{TENANT_STATUS_LABEL[t.status] ?? t.status}</span></td>
                <td className="px-4 py-3 text-xs">{t.payment_status === 'overdue' ? <span className="rounded bg-red-100 px-1.5 py-0.5 text-red-700">滞納</span> : <span className="text-gray-500">{PAYMENT_STATUS_LABEL[t.payment_status] ?? '—'}</span>}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
