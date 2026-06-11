'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

export default function NotificationSettingsPage() {
  const [s, setS] = useState<any>(null);
  const [msg, setMsg] = useState('');

  useEffect(() => { api.notificationSettings().then(setS); }, []);
  if (!s) return <p className="text-gray-400">読み込み中…</p>;

  function field(key: string, value: any) { setS({ ...s, [key]: value }); }

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setMsg('保存中…');
    await api.saveNotificationSettings({
      notification_email: s.notification_email, slack_webhook_url: s.slack_webhook_url,
      notify_on_call_end: s.notify_on_call_end, notify_on_callback: s.notify_on_callback,
      notify_on_transfer: s.notify_on_transfer,
    });
    setMsg('保存しました。');
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
