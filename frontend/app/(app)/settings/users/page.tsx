'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { getSession } from '@/lib/auth';
import { Card, PageTitle, formatDateTime } from '@/components/ui';

const ROLE_LABEL: Record<string, string> = { owner: 'オーナー', admin: '管理者', staff: 'スタッフ' };

export default function UsersPage() {
  const [users, setUsers] = useState<any[]>([]);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [role, setRole] = useState('staff');
  const [error, setError] = useState('');
  const myRole = getSession()?.role;

  function load() { api.users().then(setUsers).catch((e) => setError(String(e))); }
  useEffect(() => { load(); }, []);

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    if (!email.trim()) return;
    try {
      await api.createUser({ name, email, role });
      setName(''); setEmail(''); setRole('staff');
      load();
    } catch (err: any) {
      setError(parseErr(err));
    }
  }
  async function changeRole(u: any, newRole: string) {
    setError('');
    try { await api.updateUser(u.id, { role: newRole }); load(); }
    catch (err: any) { setError(parseErr(err)); }
  }
  async function toggleActive(u: any) {
    setError('');
    try { await api.updateUser(u.id, { is_active: !u.is_active }); load(); }
    catch (err: any) { setError(parseErr(err)); }
  }
  async function remove(u: any) {
    if (!confirm(`${u.email} を削除しますか？`)) return;
    setError('');
    try { await api.deleteUser(u.id); load(); }
    catch (err: any) { setError(parseErr(err)); }
  }

  return (
    <div>
      <PageTitle title="ユーザー管理" sub="この店舗・会社の管理画面を使えるメンバーと権限を管理します。" />

      {error && <p className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{error}</p>}

      <Card className="mb-6">
        <h2 className="mb-3 font-semibold">メンバーを追加</h2>
        <form onSubmit={add} className="flex flex-wrap items-end gap-3">
          <div className="flex-1">
            <label className="block text-xs text-gray-500">氏名</label>
            <input value={name} onChange={(e) => setName(e.target.value)} placeholder="山田 太郎"
              className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
          </div>
          <div className="flex-1">
            <label className="block text-xs text-gray-500">メールアドレス</label>
            <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="member@example.com"
              className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-xs text-gray-500">権限</label>
            <select value={role} onChange={(e) => setRole(e.target.value)}
              className="mt-1 rounded-lg border px-3 py-2 text-sm">
              <option value="staff">スタッフ</option>
              <option value="admin">管理者</option>
              <option value="owner">オーナー</option>
            </select>
          </div>
          <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">追加</button>
        </form>
        <p className="mt-3 text-xs text-gray-400">
          ※ 追加したメンバーには、本番環境では Supabase からの招待メールでログインを案内します（デモでは記録のみ）。
        </p>
      </Card>

      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr>
              <th className="px-4 py-3">氏名</th>
              <th className="px-4 py-3">メール</th>
              <th className="px-4 py-3">権限</th>
              <th className="px-4 py-3">状態</th>
              <th className="px-4 py-3">追加日</th>
              <th className="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {users.map((u) => (
              <tr key={u.id} className={`border-b last:border-0 ${u.is_active ? '' : 'opacity-50'}`}>
                <td className="px-4 py-3 font-medium">{u.name || '—'}</td>
                <td className="px-4 py-3 text-gray-600">{u.email}</td>
                <td className="px-4 py-3">
                  <select value={u.role} onChange={(e) => changeRole(u, e.target.value)}
                    className="rounded-lg border px-2 py-1 text-xs">
                    <option value="staff">スタッフ</option>
                    <option value="admin">管理者</option>
                    <option value="owner">オーナー</option>
                  </select>
                </td>
                <td className="px-4 py-3">
                  <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                    u.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>
                    {u.is_active ? '有効' : '無効'}
                  </span>
                </td>
                <td className="px-4 py-3 text-gray-600">{formatDateTime(u.created_at)}</td>
                <td className="px-4 py-3 text-right">
                  <button onClick={() => toggleActive(u)} className="mr-3 text-gray-500 hover:underline">
                    {u.is_active ? '無効化' : '有効化'}
                  </button>
                  <button onClick={() => remove(u)} className="text-red-500 hover:underline">削除</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <div className="mt-6 text-xs text-gray-500">
        <p className="font-medium">権限について</p>
        <ul className="mt-1 list-inside list-disc space-y-0.5">
          <li><b>オーナー</b>：すべての操作（ユーザー管理・設定・請求含む）。最低1人必要。</li>
          <li><b>管理者</b>：ユーザー管理・各種設定・通話対応。</li>
          <li><b>スタッフ</b>：通話履歴の閲覧・対応（ユーザー管理・設定変更は不可）。</li>
        </ul>
        {myRole === 'staff' && <p className="mt-2 text-amber-600">あなたはスタッフ権限のため、変更操作はできません。</p>}
      </div>
    </div>
  );
}

function parseErr(err: any): string {
  const m = String(err?.message ?? err);
  const jsonStart = m.indexOf('{');
  if (jsonStart >= 0) {
    try { return JSON.parse(m.slice(jsonStart)).error ?? m; } catch { /* noop */ }
  }
  if (m.includes('403')) return 'この操作の権限がありません。';
  return m;
}
