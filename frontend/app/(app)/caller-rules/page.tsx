'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle, formatDateTime } from '@/components/ui';

export default function CallerRulesPage() {
  const [rules, setRules] = useState<any[]>([]);
  const [form, setForm] = useState({ phone_number: '', action: 'block', message: '', label: '' });
  const [msg, setMsg] = useState('');

  function load() { api.callerRules().then(setRules); }
  useEffect(() => { load(); }, []);

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setMsg('');
    if (!form.phone_number) { setMsg('電話番号を入力してください'); return; }
    try {
      await api.createCallerRule(form);
      setForm({ phone_number: '', action: 'block', message: '', label: '' });
      load();
    } catch (err: any) {
      const m = String(err?.message ?? err); const i = m.indexOf('{');
      setMsg(`エラー: ${i >= 0 ? (() => { try { return JSON.parse(m.slice(i)).error; } catch { return m; } })() : m}`);
    }
  }
  async function remove(id: string) {
    if (!confirm('このルールを削除しますか？')) return;
    await api.deleteCallerRule(id); load();
  }

  return (
    <div>
      <PageTitle title="発信者ルール" sub="特定の番号からの着信を「ブロック」または「専用アナウンス」に切り替えます。" />

      <Card className="mb-6">
        <h2 className="mb-3 font-semibold">ルールを追加</h2>
        {msg && <p className="mb-2 text-sm text-red-600">{msg}</p>}
        <form onSubmit={add} className="space-y-3">
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="block text-xs text-gray-500">発信者番号（E.164推奨）</label>
              <input value={form.phone_number} onChange={(e) => setForm({ ...form, phone_number: e.target.value })}
                placeholder="+819012345678" className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
            <div>
              <label className="block text-xs text-gray-500">動作</label>
              <select value={form.action} onChange={(e) => setForm({ ...form, action: e.target.value })}
                className="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                <option value="block">ブロック（着信を拒否して文言を再生）</option>
                <option value="greeting">専用アナウンス（この番号だけ別の挨拶）</option>
              </select>
            </div>
          </div>
          <div>
            <label className="block text-xs text-gray-500">読み上げる文言（任意）</label>
            <input value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })}
              placeholder={form.action === 'block' ? '例：申し訳ありませんが、このお電話はお受けできません。' : '例：いつもありがとうございます。担当者へおつなぎします。'}
              className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-xs text-gray-500">管理用ラベル（任意）</label>
            <input value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })}
              placeholder="例：迷惑電話 / VIP" className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
          </div>
          <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">追加</button>
        </form>
      </Card>

      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr>
              <th className="px-4 py-3">番号</th>
              <th className="px-4 py-3">動作</th>
              <th className="px-4 py-3">文言 / ラベル</th>
              <th className="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {rules.length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">ルールはありません。</td></tr>}
            {rules.map((r) => (
              <tr key={r.id} className="border-b last:border-0">
                <td className="px-4 py-3 font-medium">{r.phone_number}</td>
                <td className="px-4 py-3">
                  {r.action === 'block'
                    ? <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">ブロック</span>
                    : <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">専用アナウンス</span>}
                </td>
                <td className="px-4 py-3 text-gray-600">
                  {r.message || '—'}
                  {r.label && <span className="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{r.label}</span>}
                </td>
                <td className="px-4 py-3 text-right">
                  <button onClick={() => remove(r.id)} className="text-red-500 hover:underline">削除</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <p className="mt-4 text-xs text-gray-400">※ この設定は実際の着信時（Twilio接続後）に適用されます。デモでは登録・管理のみ確認できます。</p>
    </div>
  );
}
