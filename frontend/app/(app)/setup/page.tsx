'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

export default function SetupPage() {
  const [d, setD] = useState<any>(null);
  const [error, setError] = useState('');

  useEffect(() => { api.setupStatus().then(setD).catch((e) => setError(String(e))); }, []);

  if (error) return <p className="text-red-600">{error}</p>;
  if (!d) return <p className="text-gray-400">読み込み中…</p>;

  const i = d.integrations, c = d.config;
  // 開業までのステップ（自社運用の順序）
  const steps = [
    { done: i.ai.connected, title: 'AIを接続する', now: i.ai.connected ? `接続中（${i.ai.provider} / ${i.ai.model}）` : '未接続', how: 'RenderにOPENAI_API_KEY等を設定（docs/quickstart-openrouter-resend.md）', link: '/ai-test', linkLabel: 'AI応対テストで確認' },
    { done: i.email.connected, title: 'メール通知を接続する', now: i.email.connected ? `接続中（${i.email.from}）` : '未接続', how: 'RenderにRESEND_API_KEYを設定。通知先を自社アドレスに', link: '/settings/notification', linkLabel: '通知設定へ' },
    { done: Boolean(c.industry), title: '業種テンプレートを適用', now: c.industry ? `適用済み（${c.industry}）` : '未適用', how: '自動車販売・買取テンプレで挨拶文・FAQ・査定シナリオを一括投入', link: '/settings/template', linkLabel: 'テンプレートを適用' },
    { done: c.greeting_set && c.business_hours_set, title: '挨拶文・営業時間を設定', now: c.greeting_set && c.business_hours_set ? '設定済み' : '未設定', how: '自社の名乗り・受付時間を設定', link: '/settings/ai', linkLabel: 'AI設定へ' },
    { done: c.faq_count >= 3, title: 'FAQを用意（3件以上）', now: `${c.faq_count}件`, how: '「買取査定は？」「出張査定できる？」などをFAQに', link: '/faqs', linkLabel: 'FAQ管理へ' },
    { done: c.notification_email_set, title: '通知先メールを設定', now: c.notification_email_set ? '設定済み' : '未設定', how: '着信をどこに通知するか', link: '/settings/notification', linkLabel: '通知設定へ' },
    { done: i.phone.connected, title: '電話番号をつなぐ（本番着信）', now: i.phone.connected ? `接続中（番号${i.phone.numbers}件）` : '未接続（唯一の実費）', how: 'Twilio接続（docs/twilio-setup.md）。ここまでで実際に電話が鳴る', link: '/phone-numbers', linkLabel: '電話番号設定へ' },
    { done: i.calendar.connected, title: '（任意）Googleカレンダー連携', now: i.calendar.connected ? '連携中' : '未連携', how: '査定予約をスタッフ予定と突合し重複防止（docs/google-calendar-setup.md）', link: '/appointments', linkLabel: '予約管理へ' },
  ];
  const required = steps.slice(0, 7); // 最後のカレンダーは任意
  const doneCount = required.filter((s) => s.done).length;
  const pct = Math.round((doneCount / required.length) * 100);
  const liveReady = i.ai.connected && i.email.connected && i.phone.connected;

  return (
    <div>
      <PageTitle title="開業セットアップ" sub="自社で使い始めるまでのチェックリスト。上から順に進めれば本番稼働できます。" />

      {/* 進捗 */}
      <Card className="mb-6">
        <div className="flex items-center justify-between">
          <div>
            <div className="text-sm text-gray-500">セットアップ進捗</div>
            <div className="mt-1 text-2xl font-bold">{doneCount} / {required.length} 完了</div>
          </div>
          <div className={`rounded-full px-4 py-2 text-sm font-medium ${liveReady ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'}`}>
            {liveReady ? '✅ 本番稼働できます' : '⏳ 本番稼働まであと少し'}
          </div>
        </div>
        <div className="mt-3 h-3 overflow-hidden rounded-full bg-gray-100">
          <div className="h-full rounded-full bg-brand transition-all" style={{ width: `${pct}%` }} />
        </div>
        {d.demo_mode && <p className="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-700">現在デモモード（DB未接続）です。すべて自由に触れますが、データは再起動でリセットされます。本番運用時はDATABASE_URLを設定してください。</p>}
      </Card>

      {/* ステップ一覧 */}
      <div className="space-y-3">
        {steps.map((s, idx) => (
          <Card key={idx} className={s.done ? 'border-green-200 bg-green-50/40' : ''}>
            <div className="flex items-start gap-3">
              <div className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-sm ${s.done ? 'bg-green-600 text-white' : 'border-2 border-gray-300 text-gray-400'}`}>
                {s.done ? '✓' : idx + 1}
              </div>
              <div className="flex-1">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <h3 className="font-semibold">{s.title}</h3>
                  <span className={`text-xs ${s.done ? 'text-green-700' : 'text-gray-400'}`}>{s.now}</span>
                </div>
                <p className="mt-1 text-sm text-gray-500">{s.how}</p>
                <Link href={s.link} className="mt-2 inline-block text-sm text-brand hover:underline">{s.linkLabel} →</Link>
              </div>
            </div>
          </Card>
        ))}
      </div>

      <p className="mt-6 text-xs text-gray-400">
        ※ AI（1〜2）とメールは実費ほぼ0で本番化できます。電話（7）だけ通話料の実費が発生します。
        自社で1〜2週間運用して手応えを確かめてから、多店舗展開・販売フェーズへ進めます。
      </p>
    </div>
  );
}
