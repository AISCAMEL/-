'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { api, STATUS_LABEL } from '@/lib/api';
import { Card, StatusBadge, CategoryTag, formatDateTime, formatDuration } from '@/components/ui';

export default function CallDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [call, setCall] = useState<any>(null);
  const [note, setNote] = useState('');
  const [msg, setMsg] = useState('');
  const [error, setError] = useState('');
  const [faq, setFaq] = useState<{ question: string; answer: string } | null>(null);

  function load() { api.call(id).then(setCall).catch((e) => setError(String(e))); }
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [id]);

  if (error) return <p className="text-red-600">{error}</p>;
  if (!call) return <p className="text-gray-400">読み込み中…</p>;

  async function changeStatus(status: string) {
    await api.setCallStatus(id, status);
    load();
  }
  async function addNote(e: React.FormEvent) {
    e.preventDefault();
    if (!note.trim()) return;
    await api.addNote(id, note);
    setNote('');
    load();
  }
  async function resummarize() {
    setMsg('要約を再生成中…');
    await api.resummarize(id);
    setMsg('要約を再生成しました。');
    load();
  }
  async function renotify() {
    setMsg('通知を送信中…');
    const r = await api.renotify(id);
    setMsg(r.ok ? `通知を送信しました（${r.destination}）` : `送信失敗: ${r.error}`);
  }
  function openFaq() {
    // 通話内容からFAQ候補をプリフィル
    setFaq({ question: call.request_detail || call.summary || '', answer: '' });
  }
  async function saveFaq(e: React.FormEvent) {
    e.preventDefault();
    if (!faq?.question || !faq?.answer) return;
    await api.createFaq({ question: faq.question, answer: faq.answer });
    setFaq(null);
    setMsg('FAQに追加しました。今後はAIが自動で回答します。');
  }

  return (
    <div>
      <Link href="/calls" className="text-sm text-brand hover:underline">← 通話履歴へ戻る</Link>
      <div className="mb-6 mt-2 flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold">{call.customer_name ?? call.from_number}</h1>
          <StatusBadge status={call.status} />
        </div>
        <div className="flex flex-wrap gap-2">
          {call.status !== 'completed' && (
            <button onClick={() => changeStatus('completed')} className="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">対応済みにする</button>
          )}
          <button onClick={openFaq} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-50">FAQに追加</button>
          <button onClick={resummarize} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-50">要約を再生成</button>
          <button onClick={renotify} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-50">通知を再送</button>
        </div>
      </div>
      {msg && <p className="mb-4 text-sm text-brand">{msg}</p>}

      {/* この通話からFAQを追加 */}
      {faq && (
        <Card className="mb-4">
          <h2 className="mb-2 text-sm font-semibold text-gray-500">この用件をFAQに追加</h2>
          <form onSubmit={saveFaq} className="space-y-2">
            <input value={faq.question} onChange={(e) => setFaq({ ...faq, question: e.target.value })}
              placeholder="質問" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <textarea value={faq.answer} onChange={(e) => setFaq({ ...faq, answer: e.target.value })} rows={2}
              placeholder="回答（AIがこの内容で答えます）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <div className="flex gap-2">
              <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">FAQに保存</button>
              <button type="button" onClick={() => setFaq(null)} className="rounded-lg border px-4 py-2 text-sm hover:bg-gray-50">キャンセル</button>
            </div>
          </form>
        </Card>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        {/* 左: 要約・顧客情報 */}
        <div className="space-y-6 lg:col-span-1">
          <Card>
            <h2 className="text-sm font-semibold text-gray-500">通話要約</h2>
            <p className="mt-2 text-sm leading-relaxed">{call.summary ?? '—'}</p>
          </Card>
          <Card>
            <h2 className="mb-3 text-sm font-semibold text-gray-500">顧客情報・要件</h2>
            <dl className="space-y-2 text-sm">
              <Row label="顧客名" value={call.customer_name} />
              <Row label="会社名" value={call.company_name} />
              <Row label="電話番号" value={call.from_number} />
              <Row label="要件分類" value={null}><CategoryTag category={call.category} /></Row>
              <Row label="希望日時" value={call.requested_datetime} />
              <Row label="内容" value={call.request_detail} />
              <Row label="次の対応" value={call.next_action} />
              <Row label="緊急度" value={call.urgency} />
              <Row label="通話日時" value={formatDateTime(call.started_at)} />
              <Row label="通話時間" value={formatDuration(call.duration_sec)} />
            </dl>
          </Card>
          <Card>
            <h2 className="mb-2 text-sm font-semibold text-gray-500">ステータス変更</h2>
            <select value={call.status} onChange={(e) => changeStatus(e.target.value)}
              className="w-full rounded-lg border px-3 py-2 text-sm">
              {Object.entries(STATUS_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </Card>
        </div>

        {/* 右: 文字起こし + メモ */}
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <h2 className="mb-4 text-sm font-semibold text-gray-500">文字起こし全文</h2>
            <div className="space-y-3">
              {call.transcripts?.map((t: any, i: number) => (
                <div key={i} className={`flex ${t.speaker === 'customer' ? 'justify-start' : 'justify-end'}`}>
                  <div className={`max-w-[80%] rounded-2xl px-4 py-2 text-sm ${
                    t.speaker === 'customer' ? 'bg-gray-100' : 'bg-brand-light text-gray-800'}`}>
                    <div className="mb-0.5 text-xs text-gray-400">
                      {t.speaker === 'customer' ? 'お客様' : t.speaker === 'ai' ? 'AI' : t.speaker}
                    </div>
                    {t.message}
                  </div>
                </div>
              ))}
              {(!call.transcripts || call.transcripts.length === 0) && (
                <p className="text-sm text-gray-400">文字起こしがありません。</p>
              )}
            </div>
          </Card>

          <Card>
            <h2 className="mb-3 text-sm font-semibold text-gray-500">社内メモ</h2>
            <div className="space-y-2">
              {call.notes?.map((n: any) => (
                <div key={n.id} className="rounded-lg bg-gray-50 px-3 py-2 text-sm">
                  <div className="text-xs text-gray-400">{formatDateTime(n.created_at)}</div>
                  {n.note}
                </div>
              ))}
              {(!call.notes || call.notes.length === 0) && <p className="text-sm text-gray-400">メモはありません。</p>}
            </div>
            <form onSubmit={addNote} className="mt-3 flex gap-2">
              <input value={note} onChange={(e) => setNote(e.target.value)} placeholder="メモを追加"
                className="flex-1 rounded-lg border px-3 py-2 text-sm" />
              <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">追加</button>
            </form>
          </Card>
        </div>
      </div>
    </div>
  );
}

function Row({ label, value, children }: { label: string; value?: string | null; children?: React.ReactNode }) {
  return (
    <div className="flex justify-between gap-4">
      <dt className="shrink-0 text-gray-500">{label}</dt>
      <dd className="text-right">{children ?? (value || '—')}</dd>
    </div>
  );
}
