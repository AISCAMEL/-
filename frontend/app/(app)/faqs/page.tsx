'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

interface Faq { id: string; question: string; answer: string; category: string | null; is_active: boolean; }

export default function FaqsPage() {
  const [faqs, setFaqs] = useState<Faq[]>([]);
  const [editing, setEditing] = useState<Partial<Faq> | null>(null);

  function load() { api.faqs().then(setFaqs); }
  useEffect(() => { load(); }, []);

  async function save(e: React.FormEvent) {
    e.preventDefault();
    if (!editing?.question || !editing?.answer) return;
    if (editing.id) await api.updateFaq(editing.id, editing);
    else await api.createFaq(editing);
    setEditing(null);
    load();
  }
  async function toggle(f: Faq) { await api.updateFaq(f.id, { is_active: !f.is_active }); load(); }
  async function remove(id: string) {
    if (!confirm('このFAQを削除しますか？')) return;
    await api.deleteFaq(id); load();
  }

  return (
    <div>
      <div className="flex items-center justify-between">
        <PageTitle title="FAQ管理" sub="AIが回答に使うFAQを管理します。FAQにない内容はAIが推測せず折り返しに切り替えます。" />
        <button onClick={() => setEditing({ is_active: true })}
          className="h-10 rounded-lg bg-brand px-4 text-sm font-medium text-white hover:bg-brand-dark">＋ FAQ追加</button>
      </div>

      {editing && (
        <Card className="mb-6">
          <form onSubmit={save} className="space-y-3">
            <h2 className="font-semibold">{editing.id ? 'FAQを編集' : 'FAQを追加'}</h2>
            <input value={editing.question ?? ''} onChange={(e) => setEditing({ ...editing, question: e.target.value })}
              placeholder="質問" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <textarea value={editing.answer ?? ''} onChange={(e) => setEditing({ ...editing, answer: e.target.value })}
              placeholder="回答" rows={3} className="w-full rounded-lg border px-3 py-2 text-sm" />
            <input value={editing.category ?? ''} onChange={(e) => setEditing({ ...editing, category: e.target.value })}
              placeholder="カテゴリ（任意）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <div className="flex gap-2">
              <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">保存</button>
              <button type="button" onClick={() => setEditing(null)}
                className="rounded-lg border px-4 py-2 text-sm hover:bg-gray-50">キャンセル</button>
            </div>
          </form>
        </Card>
      )}

      <div className="space-y-3">
        {faqs.map((f) => (
          <Card key={f.id} className={f.is_active ? '' : 'opacity-50'}>
            <div className="flex items-start justify-between gap-4">
              <div>
                {f.category && <span className="mb-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{f.category}</span>}
                <p className="font-medium">Q. {f.question}</p>
                <p className="mt-1 text-sm text-gray-600">A. {f.answer}</p>
              </div>
              <div className="flex shrink-0 gap-3 text-sm">
                <button onClick={() => toggle(f)} className="text-gray-500 hover:underline">{f.is_active ? '無効化' : '有効化'}</button>
                <button onClick={() => setEditing(f)} className="text-brand hover:underline">編集</button>
                <button onClick={() => remove(f.id)} className="text-red-500 hover:underline">削除</button>
              </div>
            </div>
          </Card>
        ))}
        {faqs.length === 0 && <p className="text-sm text-gray-400">FAQがまだありません。「＋ FAQ追加」から登録してください。</p>}
      </div>
    </div>
  );
}
