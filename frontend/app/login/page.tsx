'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { setSession } from '@/lib/auth';

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('owner@example.com');
  const [role, setRole] = useState<'owner' | 'super_admin'>('owner');

  // MVPデモ: 認証基盤未接続のため、デモトークンでログインする。
  // 本番では Supabase Auth のサインインに差し替え、access_token を保存する。
  function submit(e: React.FormEvent) {
    e.preventDefault();
    setSession({ token: 'dev', role, email });
    router.push('/dashboard');
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-gray-50 px-6">
      <div className="w-full max-w-sm rounded-2xl border bg-white p-8 shadow-sm">
        <Link href="/" className="block text-center text-lg font-bold text-brand">AIオペレーター24</Link>
        <h1 className="mt-6 text-center text-xl font-semibold">管理画面ログイン</h1>

        <form onSubmit={submit} className="mt-6 space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">メールアドレス</label>
            <input type="email" value={email} onChange={(e) => setEmail(e.target.value)}
              className="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-brand focus:outline-none" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">ロール（デモ用）</label>
            <select value={role} onChange={(e) => setRole(e.target.value as any)}
              className="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-brand focus:outline-none">
              <option value="owner">店舗オーナー</option>
              <option value="super_admin">スーパー管理者</option>
            </select>
          </div>
          <button type="submit"
            className="w-full rounded-lg bg-brand py-2.5 font-semibold text-white hover:bg-brand-dark">
            ログイン
          </button>
        </form>

        <p className="mt-4 text-center text-xs text-gray-400">
          デモ環境です。任意の内容でログインできます。
        </p>
      </div>
    </main>
  );
}
