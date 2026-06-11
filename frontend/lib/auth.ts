'use client';

// MVPの認証状態はクライアントに保持。
// 本番(Supabase Auth)では access_token を、デモでは 'dev' を token として保存する。
export interface Session {
  token: string;
  role: 'owner' | 'admin' | 'staff' | 'super_admin';
  tenantId?: string;
  email?: string;
}

const KEY = 'aio24_session';

export function getSession(): Session | null {
  if (typeof window === 'undefined') return null;
  const raw = window.localStorage.getItem(KEY);
  return raw ? (JSON.parse(raw) as Session) : null;
}

export function setSession(s: Session): void {
  window.localStorage.setItem(KEY, JSON.stringify(s));
}

export function clearSession(): void {
  window.localStorage.removeItem(KEY);
}
