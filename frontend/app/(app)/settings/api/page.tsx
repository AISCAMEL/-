'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle, formatDateTime } from '@/components/ui';

const API_BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'https://<あなたのAPI>.onrender.com';

export default function ApiSettingsPage() {
  const [keys, setKeys] = useState<any[]>([]);
  const [name, setName] = useState('');
  const [issued, setIssued] = useState<string | null>(null); // 発行直後の平文キー（一度きり）
  const [copied, setCopied] = useState(false);
  const [msg, setMsg] = useState('');

  function load() { api.apiKeys().then(setKeys); }
  useEffect(() => { load(); }, []);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    if (!name.trim()) return;
    const r = await api.createApiKey(name.trim());
    setIssued(r.key); setCopied(false); setName(''); setMsg('');
    load();
  }
  async function revoke(id: string) {
    if (!confirm('このキーを失効します。使用中の外部連携が止まります。よろしいですか？')) return;
    await api.revokeApiKey(id); load();
  }
  function copy(text: string) {
    navigator.clipboard?.writeText(text).then(() => { setCopied(true); setTimeout(() => setCopied(false), 2000); });
  }

  const curlInquiry = `curl -X POST ${API_BASE}/api/v1/inquiries \\
  -H "X-API-Key: aiop_live_あなたのキー" \\
  -H "Content-Type: application/json" \\
  -d '{"name":"山田太郎","phone":"09012345678","email":"taro@example.com","car":"プリウス 2019 4万km","message":"出張査定希望"}'`;

  return (
    <div>
      <PageTitle title="API連携" sub="APIキーを発行して、御社サイトの査定フォームやLINE・他システムから査定依頼を送れます。" />

      {/* 発行直後のキー表示（一度きり） */}
      {issued && (
        <Card className="mb-6 border-brand ring-1 ring-brand">
          <h2 className="text-sm font-semibold text-brand">APIキーを発行しました（この画面でしか表示されません）</h2>
          <p className="mt-1 text-xs text-gray-500">安全な場所に保管してください。閉じると二度と表示できません（紛失時は再発行）。</p>
          <div className="mt-3 flex items-center gap-2">
            <code className="flex-1 overflow-x-auto rounded-lg bg-gray-900 px-3 py-2 text-sm text-green-300">{issued}</code>
            <button onClick={() => copy(issued)} className="shrink-0 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">{copied ? 'コピー済' : 'コピー'}</button>
          </div>
          <button onClick={() => setIssued(null)} className="mt-3 text-sm text-gray-500 hover:underline">閉じる</button>
        </Card>
      )}
      {msg && <p className="mb-3 text-sm text-brand">{msg}</p>}

      {/* 発行フォーム */}
      <Card className="mb-6">
        <h2 className="mb-2 text-sm font-semibold text-gray-500">新しいAPIキーを発行</h2>
        <form onSubmit={create} className="flex flex-wrap gap-2">
          <input value={name} onChange={(e) => setName(e.target.value)} placeholder="用途がわかる名前（例：HP査定フォーム）" className="flex-1 rounded-lg border px-3 py-2 text-sm" />
          <button className="rounded-lg bg-brand px-5 py-2 text-sm font-medium text-white hover:bg-brand-dark">発行</button>
        </form>
      </Card>

      {/* キー一覧 */}
      <Card className="mb-6 p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr><th className="px-4 py-3">名前</th><th className="px-4 py-3">キー（先頭）</th><th className="px-4 py-3">最終利用</th><th className="px-4 py-3">状態</th><th className="px-4 py-3"></th></tr>
          </thead>
          <tbody>
            {keys.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">APIキーはまだありません。</td></tr>}
            {keys.map((k) => (
              <tr key={k.id} className="border-b last:border-0">
                <td className="px-4 py-3">{k.name}</td>
                <td className="px-4 py-3"><code className="text-xs text-gray-500">{k.key_prefix}…</code></td>
                <td className="px-4 py-3 text-xs text-gray-500">{k.last_used_at ? formatDateTime(k.last_used_at) : '未使用'}</td>
                <td className="px-4 py-3">{k.revoked ? <span className="rounded bg-red-100 px-2 py-0.5 text-xs text-red-600">失効</span> : <span className="rounded bg-green-100 px-2 py-0.5 text-xs text-green-700">有効</span>}</td>
                <td className="px-4 py-3 text-right">{!k.revoked && <button onClick={() => revoke(k.id)} className="text-red-500 hover:underline">失効</button>}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      {/* 使い方 */}
      <Card>
        <h2 className="mb-2 text-sm font-semibold text-gray-500">使い方（査定依頼の送信）</h2>
        <p className="text-sm text-gray-600">御社サイトのフォーム送信時に、以下のように呼び出すと連絡先（査定見込み）に自動登録され、担当へメール通知が飛びます。</p>
        <pre className="mt-3 overflow-x-auto rounded-lg bg-gray-900 px-4 py-3 text-xs text-gray-100"><code>{curlInquiry}</code></pre>
        <div className="mt-4 space-y-1 text-xs text-gray-500">
          <p><strong>エンドポイント</strong></p>
          <p>・<code>POST /api/v1/inquiries</code> … 査定依頼を登録（name/phone/email/car/message など）</p>
          <p>・<code>POST /api/v1/contacts</code> … 連絡先を作成</p>
          <p>・<code>GET /api/v1/contacts</code> … 連絡先を取得</p>
          <p className="mt-2">認証は <code>X-API-Key: （発行したキー）</code> または <code>Authorization: Bearer （キー）</code>。詳細は <code>docs/api-integration.md</code>。</p>
        </div>
      </Card>
    </div>
  );
}
