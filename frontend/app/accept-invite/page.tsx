'use client';

import { Suspense, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { api } from '@/lib/api';
import { setSession } from '@/lib/auth';

function AcceptInviteInner() {
  const router = useRouter();
  const params = useSearchParams();
  const token = params.get('token') ?? '';
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    if (password.length < 8) { setError('パスワードは8文字以上にしてください。'); return; }
    if (password !== confirm) { setError('確認用パスワードが一致しません。'); return; }
    setBusy(true);
    try {
      const r = await api.acceptInvite(token, password);
      if (r.token) {
        setSession({ token: r.token, role: r.role, tenantId: r.tenant_id ?? undefined, email: r.email });
        router.push(r.role === 'super_admin' ? '/overview' : '/dashboard');
      } else {
        router.push('/login');
      }
    } catch (err: any) {
      setError(parseErr(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-gray-50 px-6">
      <div className="w-full max-w-sm rounded-2xl border bg-white p-8 shadow-sm">
        <div className="text-center text-lg font-bold text-brand">AIオペレーター24</div>
        <h1 className="mt-6 text-center text-xl font-semibold">パスワードの設定</h1>
        <p className="mt-2 text-center text-xs text-gray-500">管理画面にログインするためのパスワードを決めてください。</p>

        {!token ? (
          <p className="mt-6 text-sm text-red-600">招待リンクが正しくありません。運営者に再発行を依頼してください。</p>
        ) : (
          <form onSubmit={submit} className="mt-6 space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">新しいパスワード（8文字以上）</label>
              <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="new-password"
                className="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-brand focus:outline-none" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">パスワード（確認）</label>
              <input type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} autoComplete="new-password"
                className="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-brand focus:outline-none" />
            </div>
            {error && <p className="text-sm text-red-600">{error}</p>}
            <button type="submit" disabled={busy || !password || !confirm}
              className="w-full rounded-lg bg-brand py-2.5 font-semibold text-white hover:bg-brand-dark disabled:opacity-50">
              {busy ? '設定中…' : 'パスワードを設定してログイン'}
            </button>
          </form>
        )}
      </div>
    </main>
  );
}

export default function AcceptInvitePage() {
  return (
    <Suspense fallback={<main className="flex min-h-screen items-center justify-center text-gray-400">読み込み中…</main>}>
      <AcceptInviteInner />
    </Suspense>
  );
}

function parseErr(err: any): string {
  const m = String(err?.message ?? err);
  const i = m.indexOf('{');
  if (i >= 0) { try { return JSON.parse(m.slice(i)).error ?? m; } catch { /* noop */ } }
  return m;
}
