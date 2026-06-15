'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { Card } from '@/components/ui';

// 新規導入時の初期設定ウィザード。挨拶文→転送→通知→最初のFAQ を順に設定。
const STEPS = ['AIの挨拶', '担当者への転送', '通知先', '最初のFAQ', '完了'];

export default function OnboardingPage() {
  const router = useRouter();
  const [step, setStep] = useState(0);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState<any>({
    greeting_message: '', ai_tone: 'polite',
    human_transfer_enabled: true, transfer_phone_number: '',
    notification_email: '',
    faq_question: '', faq_answer: '',
  });

  useEffect(() => {
    api.aiSettings().then((s) => setForm((f: any) => ({
      ...f,
      greeting_message: s.greeting_message ?? 'お電話ありがとうございます。AI受付です。ご用件をお話しください。',
      ai_tone: s.ai_tone ?? 'polite',
      human_transfer_enabled: s.human_transfer_enabled ?? true,
      transfer_phone_number: s.transfer_phone_number ?? '',
      notification_email: s.notification_email ?? '',
    }))).catch(() => {});
  }, []);

  function set(k: string, v: any) { setForm({ ...form, [k]: v }); }

  async function finish() {
    setSaving(true);
    try {
      await api.saveAiSettings({
        greeting_message: form.greeting_message, ai_tone: form.ai_tone,
        human_transfer_enabled: form.human_transfer_enabled, transfer_phone_number: form.transfer_phone_number,
      });
      await api.saveNotificationSettings({ notification_email: form.notification_email });
      if (form.faq_question && form.faq_answer) {
        await api.createFaq({ question: form.faq_question, answer: form.faq_answer });
      }
      setStep(4);
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="mx-auto max-w-2xl">
      <h1 className="text-2xl font-bold">初期設定</h1>
      <p className="mt-1 text-sm text-gray-500">数ステップでAI電話受付を使い始められます。</p>

      {/* ステップ表示 */}
      <div className="mb-6 mt-6 flex items-center gap-2">
        {STEPS.map((label, i) => (
          <div key={label} className="flex flex-1 items-center gap-2">
            <div className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
              i <= step ? 'bg-brand text-white' : 'bg-gray-200 text-gray-500'}`}>{i + 1}</div>
            {i < STEPS.length - 1 && <div className={`h-0.5 flex-1 ${i < step ? 'bg-brand' : 'bg-gray-200'}`} />}
          </div>
        ))}
      </div>

      <Card>
        {step === 0 && (
          <Section title="AIの挨拶文" desc="電話を受けたとき、最初にAIが話す言葉です。">
            <textarea value={form.greeting_message} onChange={(e) => set('greeting_message', e.target.value)} rows={3}
              className="w-full rounded-lg border px-3 py-2 text-sm" />
            <label className="mt-3 block text-sm font-medium text-gray-700">話し方</label>
            <select value={form.ai_tone} onChange={(e) => set('ai_tone', e.target.value)}
              className="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
              <option value="polite">丁寧（標準）</option>
              <option value="friendly">親しみやすい</option>
              <option value="formal">フォーマル</option>
            </select>
          </Section>
        )}
        {step === 1 && (
          <Section title="担当者への転送" desc="お客様が「担当者と話したい」と言った時の転送先です。">
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={form.human_transfer_enabled} onChange={(e) => set('human_transfer_enabled', e.target.checked)} className="h-4 w-4" />
              人間転送を有効にする
            </label>
            <label className="mt-3 block text-sm font-medium text-gray-700">転送先電話番号</label>
            <input value={form.transfer_phone_number} onChange={(e) => set('transfer_phone_number', e.target.value)}
              placeholder="+8190..." className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
          </Section>
        )}
        {step === 2 && (
          <Section title="通知先メール" desc="通話の要件をどのメールアドレスに届けるか設定します。">
            <input type="email" value={form.notification_email} onChange={(e) => set('notification_email', e.target.value)}
              placeholder="owner@example.com" className="w-full rounded-lg border px-3 py-2 text-sm" />
          </Section>
        )}
        {step === 3 && (
          <Section title="最初のFAQ（任意）" desc="よくある質問を1つ登録すると、AIが自動で答えられます。後から追加できます。">
            <input value={form.faq_question} onChange={(e) => set('faq_question', e.target.value)}
              placeholder="質問：例）営業時間を教えてください" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <textarea value={form.faq_answer} onChange={(e) => set('faq_answer', e.target.value)} rows={2}
              placeholder="回答：例）平日10時から18時までです。" className="mt-2 w-full rounded-lg border px-3 py-2 text-sm" />
          </Section>
        )}
        {step === 4 && (
          <div className="py-6 text-center">
            <div className="text-4xl">🎉</div>
            <h2 className="mt-3 text-xl font-bold">初期設定が完了しました</h2>
            <p className="mt-2 text-sm text-gray-600">「AI応対テスト」で実際の受け答えを確認できます。</p>
            <div className="mt-6 flex justify-center gap-3">
              <button onClick={() => router.push('/ai-test')} className="rounded-lg bg-brand px-5 py-2 text-sm font-medium text-white hover:bg-brand-dark">AI応対テストへ</button>
              <button onClick={() => router.push('/dashboard')} className="rounded-lg border px-5 py-2 text-sm hover:bg-gray-50">ダッシュボードへ</button>
            </div>
          </div>
        )}

        {/* ナビゲーション */}
        {step < 4 && (
          <div className="mt-6 flex justify-between">
            <button onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0}
              className="rounded-lg border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-40">戻る</button>
            {step < 3
              ? <button onClick={() => setStep((s) => s + 1)} className="rounded-lg bg-brand px-5 py-2 text-sm font-medium text-white hover:bg-brand-dark">次へ</button>
              : <button onClick={finish} disabled={saving} className="rounded-lg bg-brand px-5 py-2 text-sm font-medium text-white hover:bg-brand-dark disabled:opacity-50">{saving ? '保存中…' : '設定を保存して完了'}</button>}
          </div>
        )}
      </Card>
    </div>
  );
}

function Section({ title, desc, children }: { title: string; desc: string; children: React.ReactNode }) {
  return (
    <div>
      <h2 className="text-lg font-semibold">{title}</h2>
      <p className="mb-4 mt-1 text-sm text-gray-500">{desc}</p>
      {children}
    </div>
  );
}
