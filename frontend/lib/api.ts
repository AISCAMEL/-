'use client';

import { getSession } from './auth';

const BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8080';

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const s = getSession();
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(init.headers as Record<string, string>),
  };
  if (s) {
    headers['Authorization'] = `Bearer ${s.token}`;
    // デモ/開発モード用ヒント（本番JWTでは無視される）。
    headers['x-role'] = s.role;
    if (s.tenantId) headers['x-tenant-id'] = s.tenantId;
  }
  const res = await fetch(`${BASE}${path}`, { ...init, headers });
  if (!res.ok) {
    const text = await res.text().catch(() => '');
    throw new Error(`${res.status} ${res.statusText}: ${text}`);
  }
  if (res.status === 204) return undefined as T;
  return (await res.json()) as T;
}

export const api = {
  me: () => request<any>('/api/me'),
  dashboard: () => request<any>('/api/dashboard'),
  calls: (qs = '') => request<any[]>(`/api/calls${qs}`),
  call: (id: string) => request<any>(`/api/calls/${id}`),
  setCallStatus: (id: string, status: string) =>
    request<any>(`/api/calls/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) }),
  setCallTags: (id: string, tags: string[]) =>
    request<{ id: string; tags: string[] }>(`/api/calls/${id}/tags`, { method: 'PATCH', body: JSON.stringify({ tags }) }),
  addNote: (id: string, note: string) =>
    request<any>(`/api/calls/${id}/notes`, { method: 'POST', body: JSON.stringify({ note }) }),
  resummarize: (id: string) => request<any>(`/api/calls/${id}/summarize`, { method: 'POST' }),
  renotify: (id: string) => request<any>(`/api/calls/${id}/notify`, { method: 'POST' }),

  faqs: () => request<any[]>('/api/faqs'),
  createFaq: (body: any) => request<any>('/api/faqs', { method: 'POST', body: JSON.stringify(body) }),
  updateFaq: (id: string, body: any) => request<any>(`/api/faqs/${id}`, { method: 'PUT', body: JSON.stringify(body) }),
  deleteFaq: (id: string) => request<any>(`/api/faqs/${id}`, { method: 'DELETE' }),

  aiSettings: () => request<any>('/api/settings/ai'),
  saveAiSettings: (body: any) => request<any>('/api/settings/ai', { method: 'PUT', body: JSON.stringify(body) }),
  notificationSettings: () => request<any>('/api/settings/notification'),
  notifications: () => request<any[]>('/api/notifications'),
  saveNotificationSettings: (body: any) =>
    request<any>('/api/settings/notification', { method: 'PUT', body: JSON.stringify(body) }),

  phoneNumbers: () => request<any[]>('/api/phone-numbers'),
  updatePhoneNumber: (id: string, body: any) =>
    request<any>(`/api/phone-numbers/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),

  users: () => request<any[]>('/api/users'),
  createUser: (body: any) => request<any>('/api/users', { method: 'POST', body: JSON.stringify(body) }),
  updateUser: (id: string, body: any) => request<any>(`/api/users/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),
  deleteUser: (id: string) => request<any>(`/api/users/${id}`, { method: 'DELETE' }),

  weeklyDigest: () => request<{ ok: boolean; destination: string; summary: any; error?: string }>(
    '/api/digest/weekly', { method: 'POST' }),

  aiTest: (message: string, history: { role: 'customer' | 'ai'; content: string }[]) =>
    request<{ reply: string; intent: string; should_transfer: boolean; should_end_call: boolean }>(
      '/api/ai-test', { method: 'POST', body: JSON.stringify({ message, history }) }),

  usage: (month?: string) => request<any>(`/api/usage${month ? `?month=${month}` : ''}`),
  adminUsage: (month?: string) => request<any>(`/api/admin/usage${month ? `?month=${month}` : ''}`),
  invoice: (month?: string) => request<any>(`/api/usage/invoice${month ? `?month=${month}` : ''}`),

  // リード管理（運営・super_admin）
  overview: () => request<any>('/api/admin/overview'),
  tenant: (id: string) => request<any>(`/api/admin/tenants/${id}`),
  updateTenant: (id: string, body: any) => request<any>(`/api/admin/tenants/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),
  leads: (qs = '') => request<any[]>(`/api/admin/leads${qs}`),
  lead: (id: string) => request<any>(`/api/admin/leads/${id}`),
  updateLead: (id: string, body: any) => request<any>(`/api/admin/leads/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),
  addLeadNote: (id: string, note: string) => request<any>(`/api/admin/leads/${id}/notes`, { method: 'POST', body: JSON.stringify({ note }) }),
  createMeeting: (id: string, body: any) => request<any>(`/api/admin/leads/${id}/meetings`, { method: 'POST', body: JSON.stringify(body) }),
  updateMeeting: (id: string, body: any) => request<any>(`/api/admin/meetings/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),
  sendLeadEmail: (id: string, subject: string, body: string) => request<any>(`/api/admin/leads/${id}/email`, { method: 'POST', body: JSON.stringify({ subject, body }) }),
};

const PUBLIC_BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8080';

/** LP公開フォームからの問い合わせ送信（認証なし）。 */
export async function submitLead(body: Record<string, unknown>): Promise<{ ok: boolean; id?: string }> {
  const res = await fetch(`${PUBLIC_BASE}/api/public/leads`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  if (!res.ok) {
    const t = await res.json().catch(() => ({}));
    throw new Error((t as any).error ?? `${res.status}`);
  }
  return res.json();
}

export interface ChatTurn { role: 'user' | 'assistant'; content: string; }

/** LPチャットボットへメッセージを送る（認証なし）。 */
export async function sendChat(message: string, history: ChatTurn[]): Promise<string> {
  const res = await fetch(`${PUBLIC_BASE}/api/public/chat`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message, history }),
  });
  const data = await res.json().catch(() => ({}));
  return (data as any).reply ?? '申し訳ありません。うまく応答できませんでした。';
}

export const TAG_PRESETS = ['VIP', '要注意', '重要', '新規', 'クレーム', '常連'];

export const LEAD_STATUS_LABEL: Record<string, string> = {
  new: '新規', contacted: '連絡済', in_progress: '対応中',
  meeting_scheduled: '商談設定', won: '受注', lost: '失注', closed: 'クローズ',
};
export const LEAD_CATEGORY_LABEL: Record<string, string> = {
  inquiry: 'お問い合わせ', consultation: 'ご相談', demo: 'デモ希望',
  document: '資料請求', order_followup: '受注後連絡', other: 'その他',
};

const BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8080';

/** 認証付きでCSVを取得しダウンロードを発火する汎用関数。 */
export async function downloadCsv(path: string, filename: string): Promise<void> {
  const { getSession } = await import('./auth');
  const s = getSession();
  const res = await fetch(`${BASE_URL}${path}`, {
    headers: s ? { Authorization: `Bearer ${s.token}`, 'x-role': s.role, ...(s.tenantId ? { 'x-tenant-id': s.tenantId } : {}) } : {},
  });
  if (!res.ok) throw new Error(`${res.status}`);
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

/** 通話明細CSV（利用・原価）。 */
export function downloadUsageCsv(month?: string): Promise<void> {
  return downloadCsv(`/api/usage/export${month ? `?month=${month}` : ''}`, `usage_${month ?? 'current'}.csv`);
}

/** 通話履歴CSV（フィルタ適用）。 */
export function downloadCallsCsv(qs = ''): Promise<void> {
  return downloadCsv(`/api/calls/export${qs}`, 'calls.csv');
}

export function yen(n?: number | null): string {
  if (n === null || n === undefined) return '—';
  return '¥' + Math.round(n).toLocaleString('ja-JP');
}

// 表示用ラベル
export const CATEGORY_LABEL: Record<string, string> = {
  reservation: '予約', inquiry: '問い合わせ', pricing: '料金', callback: '折り返し',
  transfer: '転送', complaint: 'クレーム', other: 'その他',
};
export const STATUS_LABEL: Record<string, string> = {
  new: '未対応', in_progress: '対応中', completed: '完了', need_human: '要対応',
  transferred: '転送済', callback_requested: '折り返し希望', failed: '失敗', closed: 'クローズ',
};
export const STATUS_COLOR: Record<string, string> = {
  new: 'bg-amber-100 text-amber-800', need_human: 'bg-red-100 text-red-800',
  completed: 'bg-green-100 text-green-800', callback_requested: 'bg-blue-100 text-blue-800',
  transferred: 'bg-purple-100 text-purple-800', in_progress: 'bg-gray-100 text-gray-800',
  failed: 'bg-red-100 text-red-800', closed: 'bg-gray-100 text-gray-600',
};
