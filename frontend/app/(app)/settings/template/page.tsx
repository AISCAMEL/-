'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

export default function TemplatePage() {
  const [list, setList] = useState<any[]>([]);
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState('');
  const [replace, setReplace] = useState(false);
  const [current, setCurrent] = useState<string | null>(null);

  useEffect(() => {
    api.industryTemplates().then(setList);
    api.setupStatus().then((s) => setCurrent(s?.config?.industry ?? null)).catch(() => {});
  }, []);

  async function apply(key: string, label: string) {
    const mode = replace ? '\n\n【切り替えモード】既存のFAQと下書きの架電シナリオを削除してから入れ替えます。' : '\n\n既存のFAQは残したまま追加します。';
    if (!confirm(`「${label}」のテンプレートを適用します。挨拶文・FAQ・架電シナリオが入ります。${mode}\n\nよろしいですか？`)) return;
    setBusy(key); setMsg('');
    try {
      const r = await api.applyTemplate(key, { replace });
      const cleared = r.applied.cleared_faqs ? `（既存FAQ${r.applied.cleared_faqs}件を置換）` : '';
      setMsg(`「${label}」を適用しました。FAQ ${r.applied.faqs}件・架電シナリオ ${r.applied.campaigns}件を投入、挨拶文を更新${cleared}。`);
      api.setupStatus().then((s) => setCurrent(s?.config?.industry ?? null)).catch(() => {});
    } catch (e: any) {
      setMsg(`エラー: ${e.message ?? e}`);
    } finally { setBusy(''); }
  }

  return (
    <div>
      <PageTitle title="業種テンプレート" sub="このシステムはどの業種でも使えます。業種を選ぶと、挨拶文・FAQ・AI架電シナリオの初期セットが一括で入ります。後から自由に編集できます。" />

      {current && (
        <div className="mb-4 inline-block rounded-lg bg-gray-100 px-3 py-1.5 text-sm text-gray-600">
          現在の業種：<span className="font-semibold text-gray-800">{current}</span>
        </div>
      )}
      {msg && <p className="mb-4 rounded-lg bg-brand-light px-4 py-2 text-sm text-brand">{msg}</p>}

      {/* 切り替えモード */}
      <label className="mb-4 flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" checked={replace} onChange={(e) => setReplace(e.target.checked)} className="h-4 w-4" />
        業種を<strong>切り替える</strong>（既存のFAQ・下書きシナリオを消して入れ替え。別業種にまるごと変えるとき用）
      </label>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {list.map((t) => (
          <Card key={t.key} className="flex flex-col">
            <h2 className="text-lg font-bold">{t.label}</h2>
            <p className="mt-1 flex-1 text-sm text-gray-600">{t.summary}</p>
            <div className="mt-3 text-xs text-gray-400">FAQ {t.faq_count}件 ・ 架電シナリオ {t.campaign_count}件</div>
            <button onClick={() => apply(t.key, t.label)} disabled={busy === t.key}
              className="mt-3 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark disabled:opacity-50">
              {busy === t.key ? '適用中…' : replace ? 'この業種に切り替える' : 'このテンプレートを適用'}
            </button>
          </Card>
        ))}
      </div>

      <p className="mt-4 text-xs text-gray-400">
        ※ 通常は「追加」（既存FAQは残す）。まるごと別業種に変えるときは上の「業種を切り替える」にチェック。挨拶文・話し方はいずれも上書きされます（AI設定で変更可）。
        自社は「車買取専門（バイモ）」、販売先には各業種テンプレをそのままお使いいただけます。
      </p>
    </div>
  );
}
