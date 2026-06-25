'use client';

import { useEffect, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import Link from 'next/link';
import { clearSession, getSession, type Session } from '@/lib/auth';

// テナント（店舗・企業）向けナビ
const tenantNav = [
  { href: '/dashboard', label: 'ダッシュボード', icon: '📊' },
  { href: '/calls', label: '通話履歴', icon: '📞' },
  { href: '/ai-test', label: 'AI応対テスト', icon: '🎙️' },
  { href: '/campaigns', label: 'AI営業・架電', icon: '📣' },
  { href: '/usage', label: '利用状況・原価', icon: '💰' },
  { href: '/billing', label: 'お支払い', icon: '💳' },
  { href: '/faqs', label: 'FAQ管理', icon: '❓' },
  { href: '/settings/template', label: '業種テンプレート', icon: '🏷️' },
  { href: '/settings/ai', label: 'AI設定', icon: '🤖' },
  { href: '/settings/notification', label: '通知設定', icon: '✉️' },
  { href: '/phone-numbers', label: '電話番号設定', icon: '☎️' },
  { href: '/caller-rules', label: '発信者ルール', icon: '🚫' },
];

// 運営者（自社・super_admin）向けナビ
const operatorNav = [
  { href: '/overview', label: '運営ダッシュボード', icon: '📈' },
  { href: '/leads', label: '問い合わせ管理', icon: '📥' },
  { href: '/usage', label: '利用・売上', icon: '💰' },
  { href: '/admin', label: 'テナント管理', icon: '🏢' },
];

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const [session, setSessionState] = useState<Session | null>(null);
  const [ready, setReady] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);

  useEffect(() => {
    const s = getSession();
    if (!s) {
      router.replace('/login');
      return;
    }
    setSessionState(s);
    setReady(true);
  }, [router]);

  // ページ遷移したらモバイルメニューを閉じる
  useEffect(() => { setMenuOpen(false); }, [pathname]);

  if (!ready) return <div className="p-10 text-gray-400">読み込み中…</div>;

  function logout() {
    clearSession();
    router.replace('/login');
  }

  const isSuper = session?.role === 'super_admin';
  const canManageUsers = ['owner', 'admin'].includes(session?.role ?? '');
  const items = isSuper
    ? operatorNav
    : [
        ...tenantNav,
        ...(canManageUsers ? [{ href: '/settings/users', label: 'ユーザー管理', icon: '👥' }] : []),
      ];

  const navLinks = (
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
  );

  return (
    <div className="flex min-h-screen">
      {/* PC: 固定サイドバー */}
      <aside className="hidden w-60 shrink-0 border-r bg-white md:flex md:flex-col">
        <div className="px-5 py-5 text-lg font-bold text-brand">AIオペレーター24</div>
        {navLinks}
        <div className="border-t p-4 text-xs text-gray-500">
          <div className="truncate">{session?.email}</div>
          <button onClick={logout} className="mt-2 text-brand hover:underline">ログアウト</button>
        </div>
      </aside>

      {/* モバイル: ドロワー */}
      {menuOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div className="absolute inset-0 bg-black/40" onClick={() => setMenuOpen(false)} />
          <aside className="absolute left-0 top-0 flex h-full w-64 flex-col bg-white shadow-xl">
            <div className="flex items-center justify-between px-5 py-4">
              <span className="text-lg font-bold text-brand">AIオペレーター24</span>
              <button onClick={() => setMenuOpen(false)} aria-label="閉じる" className="text-gray-400">✕</button>
            </div>
            {navLinks}
            <div className="border-t p-4 text-xs text-gray-500">
              <div className="truncate">{session?.email}</div>
              <button onClick={logout} className="mt-2 text-brand hover:underline">ログアウト</button>
            </div>
          </aside>
        </div>
      )}

      <div className="flex-1">
        {/* モバイルヘッダ（ハンバーガー） */}
        <header className="flex items-center justify-between border-b bg-white px-4 py-3 md:hidden">
          <button onClick={() => setMenuOpen(true)} aria-label="メニュー" className="flex flex-col gap-1 p-1">
            <span className="block h-0.5 w-6 bg-gray-700" />
            <span className="block h-0.5 w-6 bg-gray-700" />
            <span className="block h-0.5 w-6 bg-gray-700" />
          </button>
          <span className="font-bold text-brand">AIオペレーター24</span>
          <button onClick={logout} className="text-xs text-brand">ログアウト</button>
        </header>
        <main className="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">{children}</main>
      </div>
    </div>
  );
}
