'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api';
import { Card, formatDateTime } from '@/components/ui';

const TS_LABEL: Record<string, string> = {
  pending: '未架電', calling: '発信中', answered: '応答', no_answer: '不在', done: '完了', failed: '失敗', do_not_call: '架電不可',
};
const TS_COLOR: Record<string, string> = {
  pending: 'bg-gray-100 text-gray-600', done: 'bg-green-100 text-green-800', no_answer: 'bg-amber-100 text-amber-800',
  failed: 'bg-red-100 text-red-800', calling: 'bg-blue-100 text-blue-800',
};

export default function CampaignDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [c, setC] = useState<any>(null);
  const [bulk, setBulk] = useState('');
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);

  function load() { api.campaign(id).then(setC); }
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [id]);

  if (!c) return <p className="text-gray-400">読み込み中…</p>;

  async function addTargets(e: React.FormEvent) {
    e.preventDefault();
    // 1行=「名前,会社,電話番号」 or 「電話番号」だけでも可
    const targets = bulk.split('\n').map((line) => line.trim()).filter(Boolean).map((line) => {
      const parts = line.split(/[,\t]/).map((s) => s.trim());
      if (parts.length >= 3) return { name: parts[0], company: parts[1], phone_number: parts[2] };
      if (parts.length === 2) return { name: parts[0], phone_number: parts[1] };
      return { phone_number: parts[0] };
    });
    await api.addTargets(id, targets);
    setBulk('');
    load();
  }
  async function run() {
    if (!confirm('未架電の対象に発信します。よろしいですか？')) return;
    setBusy(true); setMsg('');
    try {
      const r = await api.runCampaign(id);
      setMsg(r.simulated ? `（デモ）${r.placed}件の架電をシミュレートしました。Twilio接続で実発信されます。` : `${r.placed}件に発信しました。`);
      load();
    } catch (e: any) { setMsg(`エラー: ${e.message ?? e}`); }
    finally { setBusy(false); }
  }

  const pending = (c.targets ?? []).filter((t: any) => t.status === 'pending').length;

  return (
    <div>
      <Link href="/campaigns" className="text-sm text-brand hover:underline">← キャンペーン一覧へ</Link>
      <div className="mb-4 mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold">{c.name}</h1>
        <button onClick={run} disabled={busy || pending === 0}
          className="rounded-lg bg-brand px-5 py-2 text-sm font-medium text-white hover:bg-brand-dark disabled:opacity-50">
          {busy ? '処理中…' : `未架電 ${pending}件に発信`}
        </button>
      </div>
      {msg && <p className="mb-4 text-sm text-brand">{msg}</p>}

      <Card className="mb-6">
        <h2 className="mb-2 text-sm font-semibold text-gray-500">AIの動き</h2>
        <p className="text-sm"><span className="text-gray-500">最初の一言：</span>{c.opening || '（未設定）'}</p>
        <p className="mt-1 text-sm"><span className="text-gray-500">目的：</span>{c.goal_prompt || '（未設定）'}</p>
      </Card>

      <Card className="mb-6">
        <h2 className="mb-2 text-sm font-semibold text-gray-500">対象を追加（1行ずつ：名前,会社,電話番号 ／ 電話番号だけでも可）</h2>
        <form onSubmit={addTargets} className="space-y-2">
          <textarea value={bulk} onChange={(e) => setBulk(e.target.value)} rows={4}
            placeholder={'田中様,田中商店,+819012340001\n鈴木様,,+819012340002\n+819012340003'}
            className="w-full rounded-lg border px-3 py-2 font-mono text-sm" />
          <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">追加</button>
        </form>
      </Card>

      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr><th className="px-4 py-3">名前 / 会社</th><th className="px-4 py-3">電話番号</th><th className="px-4 py-3">状態</th><th className="px-4 py-3">結果</th></tr>
          </thead>
          <tbody>
            {(c.targets ?? []).length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">対象がありません。</td></tr>}
            {(c.targets ?? []).map((t: any) => (
              <tr key={t.id} className="border-b last:border-0">
                <td className="px-4 py-3">{t.name ?? '—'}{t.company ? ` / ${t.company}` : ''}</td>
                <td className="px-4 py-3 text-gray-600">{t.phone_number}</td>
                <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs ${TS_COLOR[t.status] ?? 'bg-gray-100'}`}>{TS_LABEL[t.status] ?? t.status}</span></td>
                <td className="px-4 py-3 text-gray-600">{t.outcome ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
