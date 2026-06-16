'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

export default function AiSettingsPage() {
  const [s, setS] = useState<any>(null);
  const [msg, setMsg] = useState('');

  useEffect(() => { api.aiSettings().then(setS); }, []);
  if (!s) return <p className="text-gray-400">読み込み中…</p>;

  function field(key: string, value: any) { setS({ ...s, [key]: value }); }

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setMsg('保存中…');
    try {
      await api.saveAiSettings({
        greeting_message: s.greeting_message, ai_tone: s.ai_tone,
        transfer_phone_number: s.transfer_phone_number, human_transfer_enabled: s.human_transfer_enabled,
        recording_enabled: s.recording_enabled, fallback_message: s.fallback_message,
      });
      setMsg('保存しました。');
    } catch (err: any) {
      setMsg(`エラー: ${parseErr(err)}`);
    }
  }

  return (
    <div>
      <PageTitle title="AI設定" sub="AIの応答内容や転送先を設定します。" />
      <Card>
        <form onSubmit={save} className="space-y-5">
          <Text label="AI挨拶文（最初の発話）" value={s.greeting_message ?? ''} onChange={(v) => field('greeting_message', v)} />
          <div>
            <label className="block text-sm font-medium text-gray-700">AIの話し方</label>
            <select value={s.ai_tone} onChange={(e) => field('ai_tone', e.target.value)}
              className="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
              <option value="polite">丁寧（標準）</option>
              <option value="friendly">親しみやすい</option>
              <option value="formal">フォーマル</option>
            </select>
          </div>
          <Text label="転送先電話番号" value={s.transfer_phone_number ?? ''} onChange={(v) => field('transfer_phone_number', v)} placeholder="+8190..." />
          <Text label="フォールバック文（転送できない時）" value={s.fallback_message ?? ''} onChange={(v) => field('fallback_message', v)} />
          <Toggle label="人間転送を有効にする" checked={!!s.human_transfer_enabled} onChange={(v) => field('human_transfer_enabled', v)} />
          <Toggle label="通話録音を有効にする" checked={!!s.recording_enabled} onChange={(v) => field('recording_enabled', v)} />

          <div className="flex items-center gap-3 pt-2">
            <button className="rounded-lg bg-brand px-5 py-2 text-sm font-medium text-white hover:bg-brand-dark">保存</button>
            {msg && <span className="text-sm text-brand">{msg}</span>}
          </div>
        </form>
      </Card>
    </div>
  );
}

function Text({ label, value, onChange, placeholder }: { label: string; value: string; onChange: (v: string) => void; placeholder?: string }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700">{label}</label>
      <input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder}
        className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
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

// エラーメッセージからサーバーの error 文言を抽出
function parseErr(err: any): string {
  const m = String(err?.message ?? err);
  const i = m.indexOf('{');
  if (i >= 0) { try { return JSON.parse(m.slice(i)).error ?? m; } catch { /* noop */ } }
  return m;
}
