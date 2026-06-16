'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle, formatDateTime } from '@/components/ui';

export default function NotificationSettingsPage() {
  const [s, setS] = useState<any>(null);
  const [msg, setMsg] = useState('');
  const [logs, setLogs] = useState<any[]>([]);

  useEffect(() => {
    api.notificationSettings().then(setS);
    api.notifications().then(setLogs).catch(() => {});
  }, []);
  if (!s) return <p className="text-gray-400">読み込み中…</p>;

  function field(key: string, value: any) { setS({ ...s, [key]: value }); }

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setMsg('保存中…');
    try {
      await api.saveNotificationSettings({
        notification_email: s.notification_email, slack_webhook_url: s.slack_webhook_url,
        notify_on_call_end: s.notify_on_call_end, notify_on_callback: s.notify_on_callback,
        notify_on_transfer: s.notify_on_transfer,
      });
      setMsg('保存しました。');
    } catch (err: any) {
      const m = String(err?.message ?? err);
      const i = m.indexOf('{');
      setMsg(`エラー: ${i >= 0 ? (() => { try { return JSON.parse(m.slice(i)).error ?? m; } catch { return m; } })() : m}`);
    }
  }

  return (
    <div>
      <PageTitle title="通知設定" sub="通話終了後の通知先とタイミングを設定します。" />
      <Card>
        <form onSubmit={save} className="space-y-5">
          <div>
            <label className="block text-sm font-medium text-gray-700">通知先メールアドレス</label>
            <input value={s.notification_email ?? ''} onChange={(e) => field('notification_email', e.target.value)}
              className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Slack Webhook URL（任意）</label>
            <input value={s.slack_webhook_url ?? ''} onChange={(e) => field('slack_webhook_url', e.target.value)}
              placeholder="https://hooks.slack.com/services/..." className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
          </div>

          <div className="space-y-2 pt-2">
            <p className="text-sm font-medium text-gray-700">通知するタイミング</p>
            <Toggle label="通話終了時" checked={!!s.notify_on_call_end} onChange={(v) => field('notify_on_call_end', v)} />
            <Toggle label="折り返し希望があった時" checked={!!s.notify_on_callback} onChange={(v) => field('notify_on_callback', v)} />
            <Toggle label="担当者転送があった時" checked={!!s.notify_on_transfer} onChange={(v) => field('notify_on_transfer', v)} />
          </div>

          <div className="flex items-center gap-3 pt-2">
            <button className="rounded-lg bg-brand px-5 py-2 text-sm font-medium text-white hover:bg-brand-dark">保存</button>
            {msg && <span className="text-sm text-brand">{msg}</span>}
          </div>
        </form>
      </Card>

      {/* 送信ログ */}
      <h2 className="mb-3 mt-8 text-lg font-semibold">送信ログ</h2>
      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr>
              <th className="px-4 py-3">日時</th>
              <th className="px-4 py-3">種別</th>
              <th className="px-4 py-3">宛先</th>
              <th className="px-4 py-3">結果</th>
            </tr>
          </thead>
          <tbody>
            {logs.length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">送信ログはまだありません。</td></tr>}
            {logs.map((n) => (
              <tr key={n.id} className="border-b last:border-0">
                <td className="whitespace-nowrap px-4 py-3 text-gray-600">{formatDateTime(n.created_at)}</td>
                <td className="px-4 py-3">{n.type === 'email' ? 'メール' : n.type === 'slack' ? 'Slack' : n.type}</td>
                <td className="px-4 py-3 text-gray-600">{n.destination ?? '—'}</td>
                <td className="px-4 py-3">
                  {n.status === 'sent'
                    ? <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">送信済</span>
                    : n.status === 'failed'
                      ? <span title={n.error_message ?? ''} className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">失敗</span>
                      : <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{n.status}</span>}
                  {n.status === 'failed' && n.error_message && <div className="mt-0.5 truncate text-[11px] text-red-500">{n.error_message}</div>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (v: boolean) => void }) {
  return (
    <label className="flex items-center gap-3 text-sm">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4" />
      {label}
    </label>
  );
}
