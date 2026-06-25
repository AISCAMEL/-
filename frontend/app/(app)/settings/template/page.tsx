'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

export default function TemplatePage() {
  const [list, setList] = useState<any[]>([]);
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState('');

  useEffect(() => { api.industryTemplates().then(setList); }, []);

  async function apply(key: string, label: string) {
    if (!confirm(`「${label}」のテンプレートを適用します。\n挨拶文の更新・FAQ・架電シナリオが追加されます。よろしいですか？`)) return;
    setBusy(key); setMsg('');
    try {
      const r = await api.applyTemplate(key);
      setMsg(`「${label}」を適用しました（FAQ ${r.applied.faqs}件・架電シナリオ ${r.applied.campaigns}件を追加、挨拶文を更新）。`);
    } catch (e: any) {
      setMsg(`エラー: ${e.message ?? e}`);
    } finally { setBusy(''); }
  }

  return (
    <div>
      <PageTitle title="業種テンプレート" sub="業種を選ぶと、挨拶文・FAQ・AI架電シナリオの初期セットが一括で入ります。後から編集できます。" />
      {msg && <p className="mb-4 rounded-lg bg-brand-light px-4 py-2 text-sm text-brand">{msg}</p>}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {list.map((t) => (
          <Card key={t.key} className="flex flex-col">
            <h2 className="text-lg font-bold">{t.label}</h2>
            <p className="mt-1 flex-1 text-sm text-gray-600">{t.summary}</p>
            <div className="mt-3 text-xs text-gray-400">FAQ {t.faq_count}件 ・ 架電シナリオ {t.campaign_count}件</div>
            <button onClick={() => apply(t.key, t.label)} disabled={busy === t.key}
              className="mt-3 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark disabled:opacity-50">
              {busy === t.key ? '適用中…' : 'このテンプレートを適用'}
            </button>
          </Card>
        ))}
      </div>

      <p className="mt-4 text-xs text-gray-400">※ 適用してもFAQは追加されるだけで既存は消えません。挨拶文・話し方は上書きされます（AI設定で変更可）。</p>
    </div>
  );
}
