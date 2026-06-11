'use client';

import { useEffect, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import Link from 'next/link';
import { clearSession, getSession, type Session } from '@/lib/auth';

const nav = [
  { href: '/dashboard', label: 'ダッシュボード', icon: '📊' },
  { href: '/calls', label: '通話履歴', icon: '📞' },
  { href: '/faqs', label: 'FAQ管理', icon: '❓' },
  { href: '/settings/ai', label: 'AI設定', icon: '🤖' },
  { href: '/settings/notification', label: '通知設定', icon: '✉️' },
  { href: '/phone-numbers', label: '電話番号設定', icon: '☎️' },
];

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const [session, setSessionState] = useState<Session | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const s = getSession();
    if (!s) {
      router.replace('/login');
      return;
    }
    setSessionState(s);
    setReady(true);
  }, [router]);

  if (!ready) return <div className="p-10 text-gray-400">読み込み中…</div>;

  function logout() {
    clearSession();
    router.replace('/login');
  }

  const items = session?.role === 'super_admin'
    ? [...nav, { href: '/admin', label: 'Super Admin', icon: '🛡️' }]
    : nav;

  return (
    <div className="flex min-h-screen">
      <aside className="hidden w-60 shrink-0 border-r bg-white md:flex md:flex-col">
        <div className="px-5 py-5 text-lg font-bold text-brand">AIオペレーター24</div>
        <nav className="flex-1 space-y-1 px-3">
          {items.map((i) => {
            const active = pathname === i.href || pathname.startsWith(i.href + '/');
            return (
              <Link key={i.href} href={i.href}
                className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm ${
                  active ? 'bg-brand-light font-medium text-brand' : 'text-gray-600 hover:bg-gray-50'}`}>
                <span>{i.icon}</span> {i.label}
              </Link>
            );
          })}
        </nav>
        <div className="border-t p-4 text-xs text-gray-500">
          <div className="truncate">{session?.email}</div>
          <button onClick={logout} className="mt-2 text-brand hover:underline">ログアウト</button>
        </div>
      </aside>

      <div className="flex-1">
        <header className="flex items-center justify-between border-b bg-white px-6 py-3 md:hidden">
          <span className="font-bold text-brand">AIオペレーター24</span>
          <button onClick={logout} className="text-sm text-brand">ログアウト</button>
        </header>
        <main className="mx-auto max-w-5xl px-6 py-8">{children}</main>
      </div>
    </div>
  );
}
