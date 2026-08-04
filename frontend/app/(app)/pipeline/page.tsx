'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, CONTACT_STATUS_COLOR } from '@/lib/api';
import { PageTitle } from '@/components/ui';

// 案件（お問い合わせ）のパイプライン列。連絡先の status をそのまま使う。
const COLUMNS = [
  { key: 'active', label: '見込み', accent: 'border-t-gray-400' },
  { key: 'in_progress', label: '商談中', accent: 'border-t-blue-500' },
  { key: 'won', label: '成約', accent: 'border-t-green-500' },
  { key: 'lost', label: '見送り', accent: 'border-t-gray-300' },
];

export default function PipelinePage() {
  const [rows, setRows] = useState<any[]>([]);
  const [q, setQ] = useState('');
  const [dragId, setDragId] = useState<string | null>(null);
  const [overCol, setOverCol] = useState<string | null>(null);
  const [msg, setMsg] = useState('');

  function load() {
    const s = q ? `?q=${encodeURIComponent(q)}` : '';
    api.contacts(s).then(setRows);
  }
  useEffect(() => { load(); /* eslint-disable-next-line */ }, []);

  async function moveTo(id: string, status: string) {
    const cur = rows.find((r) => r.id === id);
    if (!cur || (cur.status || 'active') === status) return;
    // 楽観的更新
    setRows((rs) => rs.map((r) => (r.id === id ? { ...r, status } : r)));
    try {
      await api.updateContact(id, { status });
      const label = COLUMNS.find((c) => c.key === status)?.label ?? status;
      setMsg(`「${cur.name ?? '案件'}」を「${label}」へ移動しました。`);
    } catch (e: any) {
      setMsg(`エラー: ${e.message ?? e}`); load();
    }
  }

  const byStatus = (key: string) => rows.filter((r) => (r.status || 'active') === key);
  const wonCount = byStatus('won').length;
  const total = rows.filter((r) => (r.status || 'active') !== 'do_not_contact').length;

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <PageTitle title="案件ボード" sub="お問い合わせ・査定案件をカンバンで管理。カードをドラッグして進捗を動かせます。" />
        <div className="flex items-center gap-3">
          <span className="text-sm text-gray-500">成約 {wonCount} / 全 {total} 件</span>
          <form onSubmit={(e) => { e.preventDefault(); load(); }} className="flex gap-2">
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="名前・会社・番号で検索" className="rounded-lg border px-3 py-2 text-sm" />
            <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">検索</button>
          </form>
        </div>
      </div>
      {msg && <p className="mb-3 text-sm text-brand">{msg}</p>}

      <div className="grid gap-4 md:grid-cols-4">
        {COLUMNS.map((col) => {
          const items = byStatus(col.key);
          const isOver = overCol === col.key;
          return (
            <div key={col.key}
              onDragOver={(e) => { e.preventDefault(); setOverCol(col.key); }}
              onDragLeave={() => setOverCol((c) => (c === col.key ? null : c))}
              onDrop={(e) => { e.preventDefault(); if (dragId) moveTo(dragId, col.key); setDragId(null); setOverCol(null); }}
              className={`rounded-xl border-t-4 ${col.accent} bg-gray-50 p-2 transition-colors ${isOver ? 'bg-brand-light ring-2 ring-brand/40' : ''}`}>
              <div className="flex items-center justify-between px-2 py-2">
                <h2 className="text-sm font-semibold">{col.label}</h2>
                <span className="rounded-full bg-white px-2 py-0.5 text-xs text-gray-500">{items.length}</span>
              </div>
              <div className="flex min-h-[120px] flex-col gap-2">
                {items.map((c) => (
                  <div key={c.id} draggable
                    onDragStart={() => setDragId(c.id)}
                    onDragEnd={() => { setDragId(null); setOverCol(null); }}
                    className={`cursor-grab rounded-lg border bg-white p-3 shadow-sm active:cursor-grabbing ${dragId === c.id ? 'opacity-50' : ''}`}>
                    <div className="flex items-start justify-between gap-2">
                      <div className="font-medium">{c.name ?? c.phone_number ?? '（無名）'}</div>
                      {c.category && <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] ${CONTACT_STATUS_COLOR[c.status] ?? 'bg-gray-100'}`}>{c.category}</span>}
                    </div>
                    {c.company && <div className="text-xs text-gray-500">{c.company}</div>}
                    {c.phone_number && <div className="text-xs text-gray-400">{c.phone_number}</div>}
                    {c.note && <div className="mt-1 line-clamp-2 text-xs text-gray-500">{c.note}</div>}
                    <div className="mt-2 flex flex-wrap gap-1">
                      {COLUMNS.filter((x) => x.key !== (c.status || 'active')).map((x) => (
                        <button key={x.key} onClick={() => moveTo(c.id, x.key)}
                          className="rounded border px-1.5 py-0.5 text-[10px] text-gray-500 hover:bg-gray-50">→{x.label}</button>
                      ))}
                    </div>
                  </div>
                ))}
                {items.length === 0 && <p className="px-2 py-6 text-center text-xs text-gray-400">なし</p>}
              </div>
            </div>
          );
        })}
      </div>

      <p className="mt-4 text-xs text-gray-400">
        ※ カードはドラッグ＆ドロップ、またはカード下のボタンで移動できます。移動は連絡先リストのステータスと連動し、活動履歴に記録されます。
        着信からの案件は <Link href="/contacts" className="text-brand hover:underline">連絡先リスト</Link> や通話詳細の「連絡先に追加」から入ります。
      </p>
    </div>
  );
}
