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
        business_hours: s.business_hours ?? {}, holiday_settings: s.holiday_settings ?? {},
        ai_instructions: s.ai_instructions ?? '', reception_types: s.reception_types ?? '',
        sales_call_reply: s.sales_call_reply ?? '', online_booking_url: s.online_booking_url ?? '',
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

          <BusinessHoursEditor
            hours={s.business_hours ?? {}}
            holiday={s.holiday_settings ?? {}}
            onChange={(bh, hol) => { field('business_hours', bh); field('holiday_settings', hol); }}
          />

          {/* AIの教育（追加指示・対応タイプ・営業電話） */}
          <div className="rounded-xl border border-brand/30 bg-brand-light/40 p-4">
            <h2 className="mb-3 text-sm font-semibold text-brand">🎓 AIの教育（受け答えの調整）</h2>

            <Area
              label="AIへの追加指示（状況・受け答えの方針）"
              value={s.ai_instructions ?? ''}
              onChange={(v) => field('ai_instructions', v)}
              placeholder="例：遠方（対応エリア外）のお客様には出張ではなくオンライン査定を案内する。査定額は電話で確約しない。特定の車種は高価買取をアピール。など、AIに守らせたい方針を自由に書けます。"
              hint="ここに書いた内容をAIが最優先で守ります。状況ごとの受け答えを自由に教育できます。"
            />

            <div className="mt-4">
              <Text
                label="対応タイプ（予約・受付の種別／カンマ区切り）"
                value={s.reception_types ?? ''}
                onChange={(v) => field('reception_types', v)}
                placeholder="画像査定,オンライン査定,出張査定,持込査定"
              />
              <p className="mt-1 text-xs text-gray-500">業種に合わせて自由に増減できます。先頭を基本の対応にすると案内しやすいです（例：画像査定を基本に）。</p>
            </div>

            <div className="mt-4">
              <Text
                label="オンライン査定の予約ページURL（希望者にAIが案内）"
                value={s.online_booking_url ?? ''}
                onChange={(v) => field('online_booking_url', v)}
                placeholder="https://calendar.app.google/xxxxxxxx"
              />
              <p className="mt-1 text-xs text-gray-500">ビデオ査定を希望されたお客様にだけ、このGoogle予約ページを案内します（Googleが日時枠とMeetを自動発行）。</p>
            </div>

            <div className="mt-4">
              <Area
                label="営業・勧誘電話への返し方"
                value={s.sales_call_reply ?? ''}
                onChange={(v) => field('sales_call_reply', v)}
                placeholder="恐れ入りますが、営業・勧誘のお電話はお取り次ぎしておりません。ご用件があれば会社名とお名前を伺い、担当者より折り返しご連絡いたします。"
                hint="営業電話とAIが判断したら、この方針でかわします（査定・予約フローには進めません）。"
              />
            </div>
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

function Text({ label, value, onChange, placeholder }: { label: string; value: string; onChange: (v: string) => void; placeholder?: string }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700">{label}</label>
      <input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder}
        className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
    </div>
  );
}

function Area({ label, value, onChange, placeholder, hint }: { label: string; value: string; onChange: (v: string) => void; placeholder?: string; hint?: string }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700">{label}</label>
      <textarea value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} rows={4}
        className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
      {hint && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
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

const DAYS: [string, string][] = [
  ['mon', '月'], ['tue', '火'], ['wed', '水'], ['thu', '木'], ['fri', '金'], ['sat', '土'], ['sun', '日'],
];

// 営業時間エディタ。business_hours = {mon:[["10:00","18:00"]],...}、休業日は holiday.weekly に格納。
function BusinessHoursEditor({ hours, holiday, onChange }: {
  hours: Record<string, [string, string][]>;
  holiday: { weekly?: string[]; dates?: string[] };
  onChange: (hours: any, holiday: any) => void;
}) {
  function update(day: string, enabled: boolean, open: string, close: string) {
    const nextHours: Record<string, any> = { ...hours };
    const weekly = new Set(holiday.weekly ?? []);
    if (enabled) {
      nextHours[day] = [[open, close]];
      weekly.delete(day);
    } else {
      delete nextHours[day];
      weekly.add(day);
    }
    onChange(nextHours, { ...holiday, weekly: Array.from(weekly) });
  }

  return (
    <div>
      <label className="block text-sm font-medium text-gray-700">営業時間</label>
      <p className="mb-2 mt-0.5 text-xs text-gray-400">営業日と時間帯を設定します（AIが営業時間外の案内に利用）。</p>
      <div className="space-y-1.5">
        {DAYS.map(([key, label]) => {
          const range = hours[key];
          const enabled = Array.isArray(range) && range.length > 0;
          const open = enabled ? range[0][0] : '10:00';
          const close = enabled ? range[0][1] : '18:00';
          return (
            <div key={key} className="flex items-center gap-3 text-sm">
              <label className="flex w-16 items-center gap-1.5">
                <input type="checkbox" checked={enabled} onChange={(e) => update(key, e.target.checked, open, close)} className="h-4 w-4" />
                {label}
              </label>
              {enabled ? (
                <>
                  <input type="time" value={open} onChange={(e) => update(key, true, e.target.value, close)} className="rounded border px-2 py-1" />
                  <span className="text-gray-400">〜</span>
                  <input type="time" value={close} onChange={(e) => update(key, true, open, e.target.value)} className="rounded border px-2 py-1" />
                </>
              ) : <span className="text-gray-400">休業</span>}
            </div>
          );
        })}
      </div>
    </div>
  );
}
