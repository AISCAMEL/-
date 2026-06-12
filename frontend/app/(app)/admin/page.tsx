'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { getSession } from '@/lib/auth';
import { Card, PageTitle } from '@/components/ui';

// Super Admin: 全テナント一覧と新規追加（MVP）。
export default function AdminPage() {
  const [tenants, setTenants] = useState<any[]>([]);
  const [name, setName] = useState('');
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
    if (!name.trim()) return;
    const s = getSession();
    await fetch(`${process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8080'}/api/admin/tenants`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${s?.token}`, 'x-role': 'super_admin' },
      body: JSON.stringify({ company_name: name }),
    });
    setName('');
    load();
  }

  return (
    <div>
      <PageTitle title="Super Admin" sub="全テナントの管理（運営者専用）。" />
      {error && <p className="mb-4 text-sm text-red-600">権限がないか、エラーが発生しました（{error}）。</p>}

      <Card className="mb-6">
        <form onSubmit={create} className="flex gap-2">
          <input value={name} onChange={(e) => setName(e.target.value)} placeholder="新規テナント名（会社名）"
            className="flex-1 rounded-lg border px-3 py-2 text-sm" />
          <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">追加</button>
        </form>
      </Card>

      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr><th className="px-4 py-3">会社名</th><th className="px-4 py-3">業種</th><th className="px-4 py-3">プラン</th><th className="px-4 py-3">状態</th></tr>
          </thead>
          <tbody>
            {tenants.map((t) => (
              <tr key={t.id} className="border-b last:border-0 hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">
                  <Link href={`/admin/tenants/${t.id}`} className="text-brand hover:underline">{t.company_name}</Link>
                </td>
                <td className="px-4 py-3 text-gray-600">{t.industry ?? '—'}</td>
                <td className="px-4 py-3 text-gray-600">{t.plan}</td>
                <td className="px-4 py-3 text-gray-600">{t.status}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
