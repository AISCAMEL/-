'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api';
import { setSession } from '@/lib/auth';

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  // 初回パスワード設定（bootstrap）
  const [setupOpen, setSetupOpen] = useState(false);
  const [setupToken, setSetupToken] = useState('');
  const [setupPw, setSetupPw] = useState('');
  const [setupMsg, setSetupMsg] = useState('');

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setError('');
    try {
      const r = await api.login(email.trim(), password);
      setSession({ token: r.token, role: r.role, tenantId: r.tenant_id ?? undefined, email: r.email });
      router.push(r.role === 'super_admin' ? '/overview' : '/dashboard');
    } catch (err: any) {
      setError(parseErr(err));
    } finally {
      setBusy(false);
    }
  }

  async function doBootstrap(e: React.FormEvent) {
    e.preventDefault();
    setSetupMsg('設定中…');
    try {
      await api.bootstrapPassword(email.trim(), setupPw, setupToken.trim());
      setSetupMsg('パスワードを設定しました。上のフォームからログインしてください。');
      setPassword(setupPw);
    } catch (err: any) {
      setSetupMsg(`エラー: ${parseErr(err)}`);
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-gray-50 px-6">
      <div className="w-full max-w-sm rounded-2xl border bg-white p-8 shadow-sm">
        <Link href="/" className="block text-center text-lg font-bold text-brand">AIオペレーター24</Link>
        <h1 className="mt-6 text-center text-xl font-semibold">管理画面ログイン</h1>

        <form onSubmit={submit} className="mt-6 space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">メールアドレス</label>
            <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="username"
              placeholder="you@example.com"
              className="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-brand focus:outline-none" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">パスワード</label>
            <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password"
              className="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-brand focus:outline-none" />
          </div>
          {error && <p className="text-sm text-red-600">{error}</p>}
          <button type="submit" disabled={busy || !email || !password}
            className="w-full rounded-lg bg-brand py-2.5 font-semibold text-white hover:bg-brand-dark disabled:opacity-50">
            {busy ? 'ログイン中…' : 'ログイン'}
          </button>
        </form>

        {/* 初回パスワード設定 */}
        <div className="mt-6 border-t pt-4">
          <button onClick={() => setSetupOpen((v) => !v)} className="text-xs text-brand hover:underline">
            初めての方・パスワード未設定の方はこちら（初回設定）
          </button>
          {setupOpen && (
            <form onSubmit={doBootstrap} className="mt-3 space-y-2 rounded-lg bg-gray-50 p-3">
              <p className="text-xs text-gray-500">
                上の「メールアドレス」（seedで登録したオーナーのメール）を使います。運営から渡された<b>セットアップトークン</b>と、新しいパスワードを入力してください。
              </p>
              <input value={setupToken} onChange={(e) => setSetupToken(e.target.value)} placeholder="セットアップトークン"
                className="w-full rounded-lg border px-3 py-2 text-sm" />
              <input type="password" value={setupPw} onChange={(e) => setSetupPw(e.target.value)} placeholder="新しいパスワード（8文字以上）"
                className="w-full rounded-lg border px-3 py-2 text-sm" />
              <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">パスワードを設定</button>
              {setupMsg && <p className="text-xs text-brand">{setupMsg}</p>}
            </form>
          )}
        </div>
      </div>
    </main>
  );
}

function parseErr(err: any): string {
  const m = String(err?.message ?? err);
  const i = m.indexOf('{');
  if (i >= 0) { try { return JSON.parse(m.slice(i)).error ?? m; } catch { /* noop */ } }
  return m;
}
