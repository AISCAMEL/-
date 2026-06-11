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
  saveNotificationSettings: (body: any) =>
    request<any>('/api/settings/notification', { method: 'PUT', body: JSON.stringify(body) }),

  phoneNumbers: () => request<any[]>('/api/phone-numbers'),
  updatePhoneNumber: (id: string, body: any) =>
    request<any>(`/api/phone-numbers/${id}`, { method: 'PATCH', body: JSON.stringify(body) }),
};

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
