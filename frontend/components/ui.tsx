'use client';

import { STATUS_COLOR, STATUS_LABEL, CATEGORY_LABEL } from '@/lib/api';

export function PageTitle({ title, sub }: { title: string; sub?: string }) {
  return (
    <div className="mb-6">
      <h1 className="text-2xl font-bold">{title}</h1>
      {sub && <p className="mt-1 text-sm text-gray-500">{sub}</p>}
    </div>
  );
}

export function StatusBadge({ status }: { status: string }) {
  return (
    <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${
      STATUS_COLOR[status] ?? 'bg-gray-100 text-gray-700'}`}>
      {STATUS_LABEL[status] ?? status}
    </span>
  );
}

export function CategoryTag({ category }: { category: string | null }) {
  if (!category) return <span className="text-gray-400">—</span>;
  return <span className="text-sm text-gray-700">{CATEGORY_LABEL[category] ?? category}</span>;
}

export function Card({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return <div className={`rounded-xl border bg-white p-5 shadow-sm ${className}`}>{children}</div>;
}

export function formatDateTime(iso?: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleString('ja-JP', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

export function formatDuration(sec?: number | null): string {
  if (!sec && sec !== 0) return '—';
  const m = Math.floor((sec ?? 0) / 60);
  const s = (sec ?? 0) % 60;
  return `${m}分${s}秒`;
}
