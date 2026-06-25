'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { Card, PageTitle, formatDateTime } from '@/components/ui';

const PURPOSE_LABEL: Record<string, string> = { sales: 'AI営業', reminder: '催促・確認', survey: 'アンケート', followup: 'フォロー', other: 'その他' };
const STATUS_LABEL: Record<string, string> = { draft: '下書き', running: '実行中', paused: '一時停止', done: '完了' };

export default function CampaignsPage() {
  const [list, setList] = useState<any[]>([]);
  const [form, setForm] = useState({ name: '', purpose: 'sales', opening: '', goal_prompt: '' });
  const [open, setOpen] = useState(false);

  function load() { api.campaigns().then(setList); }
  useEffect(() => { load(); }, []);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    if (!form.name) return;
    await api.createCampaign(form);
    setForm({ name: '', purpose: 'sales', opening: '', goal_prompt: '' });
    setOpen(false);
    load();
  }

  return (
    <div>
      <div className="flex items-center justify-between">
        <PageTitle title="AI営業・架電" sub="AIが対象リストに電話をかけ、商品案内・打合せ打診・担当者への取次を行います。" />
        <button onClick={() => setOpen((v) => !v)} className="h-10 rounded-lg bg-brand px-4 text-sm font-medium text-white hover:bg-brand-dark">＋ キャンペーン作成</button>
      </div>

      {open && (
        <Card className="mb-6">
          <form onSubmit={create} className="space-y-3">
            <div>
              <label className="block text-xs text-gray-500">キャンペーン名</label>
              <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="例：新サービス案内" className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
            <div>
              <label className="block text-xs text-gray-500">目的</label>
              <select value={form.purpose} onChange={(e) => setForm({ ...form, purpose: e.target.value })} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                {Object.entries(PURPOSE_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs text-gray-500">最初に話す文（opening）</label>
              <input value={form.opening} onChange={(e) => setForm({ ...form, opening: e.target.value })} placeholder="例：お世話になっております。〇〇のAI担当です。新サービスのご案内です。" className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
            <div>
              <label className="block text-xs text-gray-500">AIへの指示（目的・ゴール）</label>
              <textarea value={form.goal_prompt} onChange={(e) => setForm({ ...form, goal_prompt: e.target.value })} rows={2} placeholder="例：新しい予約システムを紹介し、興味があれば担当者との打合せを打診する。" className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
            <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">作成</button>
          </form>
        </Card>
      )}

      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr><th className="px-4 py-3">キャンペーン</th><th className="px-4 py-3">目的</th><th className="px-4 py-3">対象</th><th className="px-4 py-3">状態</th><th className="px-4 py-3">作成日</th></tr>
          </thead>
          <tbody>
            {list.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">キャンペーンがありません。「＋ キャンペーン作成」から。</td></tr>}
            {list.map((c) => (
              <tr key={c.id} className="border-b last:border-0 hover:bg-gray-50">
                <td className="px-4 py-3"><Link href={`/campaigns/${c.id}`} className="font-medium text-brand hover:underline">{c.name}</Link></td>
                <td className="px-4 py-3 text-gray-600">{PURPOSE_LABEL[c.purpose] ?? c.purpose}</td>
                <td className="px-4 py-3 text-gray-600">{c.target_count ?? 0}件</td>
                <td className="px-4 py-3"><span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{STATUS_LABEL[c.status] ?? c.status}</span></td>
                <td className="px-4 py-3 text-gray-500">{formatDateTime(c.created_at)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <p className="mt-4 text-xs text-gray-400">※ 実際の発信はTwilio接続後に行われます。未接続時は結果をシミュレートします。架電は法令（時間帯・頻度・本人同意等）に配慮してご利用ください。</p>
    </div>
  );
}
