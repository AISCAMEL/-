'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { api, CATEGORY_LABEL, STATUS_LABEL } from '@/lib/api';
import { formatDateTime, formatDuration } from '@/components/ui';

// 通話1件の印刷用レイアウト。ブラウザの「印刷 → PDFに保存」で日本語のままPDF化できる。
export default function CallPrintPage() {
  const { id } = useParams<{ id: string }>();
  const [call, setCall] = useState<any>(null);
  const [error, setError] = useState('');

  useEffect(() => { api.call(id).then(setCall).catch((e) => setError(String(e))); }, [id]);

  if (error) return <p className="p-6 text-red-600">{error}</p>;
  if (!call) return <p className="p-6 text-gray-400">読み込み中…</p>;

  const v = (x: string | null) => x || '—';

  return (
    <div>
      <div className="mb-4 flex items-center justify-between print:hidden">
        <Link href={`/calls/${id}`} className="text-sm text-brand hover:underline">← 通話詳細へ戻る</Link>
        <button onClick={() => window.print()}
          className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">印刷 / PDF保存</button>
      </div>

      <div className="mx-auto max-w-2xl bg-white p-8 text-sm text-gray-800 shadow-sm print:shadow-none">
        <div className="flex items-start justify-between border-b pb-4">
          <div>
            <h1 className="text-xl font-bold">通話記録</h1>
            <p className="mt-1 text-xs text-gray-500">AIオペレーター24</p>
          </div>
          <div className="text-right text-xs text-gray-600">
            <div>通話日時：{formatDateTime(call.started_at)}</div>
            <div>通話時間：{formatDuration(call.duration_sec)}</div>
            <div>ステータス：{STATUS_LABEL[call.status] ?? call.status}</div>
          </div>
        </div>

        <h2 className="mt-6 text-sm font-semibold text-gray-500">通話要約</h2>
        <p className="mt-1 leading-relaxed">{v(call.summary)}</p>

        <h2 className="mt-6 text-sm font-semibold text-gray-500">顧客情報・要件</h2>
        <table className="mt-1 w-full border-collapse">
          <tbody>
            {[
              ['顧客名', v(call.customer_name)],
              ['会社名', v(call.company_name)],
              ['電話番号', v(call.from_number)],
              ['要件分類', CATEGORY_LABEL[call.category] ?? '—'],
              ['希望日時', v(call.requested_datetime)],
              ['内容', v(call.request_detail)],
              ['次の対応', v(call.next_action)],
              ['タグ', (call.tags ?? []).join('、') || '—'],
            ].map(([k, val]) => (
              <tr key={k} className="border-b align-top">
                <th className="w-28 bg-gray-50 px-2 py-1.5 text-left font-medium text-gray-600">{k}</th>
                <td className="px-2 py-1.5">{val}</td>
              </tr>
            ))}
          </tbody>
        </table>

        <h2 className="mt-6 text-sm font-semibold text-gray-500">文字起こし</h2>
        <div className="mt-1 space-y-1">
          {call.transcripts?.length > 0 ? call.transcripts.map((t: any, i: number) => (
            <div key={i}>
              <span className="text-gray-400">{t.speaker === 'customer' ? 'お客様' : t.speaker === 'ai' ? 'AI' : t.speaker}：</span>
              {t.message}
            </div>
          )) : <p className="text-gray-400">なし</p>}
        </div>

        {call.notes?.length > 0 && (
          <>
            <h2 className="mt-6 text-sm font-semibold text-gray-500">社内メモ</h2>
            <ul className="mt-1 list-inside list-disc">
              {call.notes.map((n: any) => <li key={n.id}>{n.note}（{formatDateTime(n.created_at)}）</li>)}
            </ul>
          </>
        )}

        <p className="mt-8 text-right text-xs text-gray-400">出力日：{new Date().toLocaleString('ja-JP')}</p>
      </div>
    </div>
  );
}
