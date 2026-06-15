'use client';

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { api, CATEGORY_LABEL } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

interface Turn { role: 'customer' | 'ai'; content: string; intent?: string; transfer?: boolean; }

const SUGGESTIONS = ['営業時間を教えてください', '予約を取りたいです', '料金について聞きたい', '担当者につないでください', '折り返してほしい'];

export default function AiTestPage() {
  const [messages, setMessages] = useState<Turn[]>([
    { role: 'ai', content: 'お電話ありがとうございます。AI受付です。ご用件をお話しください。（これはテスト画面です）' },
  ]);
  const [input, setInput] = useState('');
  const [sending, setSending] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
  }, [messages]);

  async function say(text: string) {
    const msg = text.trim();
    if (!msg || sending) return;
    const history = messages.map((m) => ({ role: m.role, content: m.content }));
    setMessages((prev) => [...prev, { role: 'customer', content: msg }]);
    setInput('');
    setSending(true);
    try {
      const r = await api.aiTest(msg, history);
      setMessages((prev) => [...prev, { role: 'ai', content: r.reply, intent: r.intent, transfer: r.should_transfer }]);
    } catch (e: any) {
      setMessages((prev) => [...prev, { role: 'ai', content: `エラー: ${e.message ?? e}` }]);
    } finally {
      setSending(false);
    }
  }

  function reset() {
    setMessages([{ role: 'ai', content: 'お電話ありがとうございます。AI受付です。ご用件をお話しください。（これはテスト画面です）' }]);
  }

  return (
    <div>
      <PageTitle title="AI応対テスト" sub="お客様になったつもりで話しかけ、AIの受け答えを確認できます。FAQ・AI設定の内容がそのまま反映されます。" />

      <Card className="flex h-[60vh] flex-col p-0">
        <div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto p-4">
          {messages.map((m, i) => (
            <div key={i} className={`flex ${m.role === 'customer' ? 'justify-end' : 'justify-start'}`}>
              <div className={`max-w-[80%] rounded-2xl px-4 py-2 text-sm ${
                m.role === 'customer' ? 'bg-brand text-white' : 'border bg-white text-gray-800'}`}>
                <div className="mb-0.5 text-xs opacity-60">{m.role === 'customer' ? 'お客様（あなた）' : 'AI受付'}</div>
                <div className="whitespace-pre-wrap">{m.content}</div>
                {m.role === 'ai' && (m.intent || m.transfer) && (
                  <div className="mt-1 flex gap-1">
                    {m.intent && <span className="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">分類: {CATEGORY_LABEL[m.intent] ?? m.intent}</span>}
                    {m.transfer && <span className="rounded bg-purple-100 px-1.5 py-0.5 text-[10px] text-purple-700">転送判定</span>}
                  </div>
                )}
              </div>
            </div>
          ))}
          {sending && <div className="flex justify-start"><div className="rounded-2xl border bg-white px-4 py-2 text-sm text-gray-400">●●●</div></div>}
        </div>

        {/* 例文 */}
        <div className="flex flex-wrap gap-2 border-t p-3">
          {SUGGESTIONS.map((s) => (
            <button key={s} onClick={() => say(s)} disabled={sending}
              className="rounded-full border px-3 py-1 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-40">{s}</button>
          ))}
        </div>

        <form onSubmit={(e) => { e.preventDefault(); say(input); }} className="flex items-center gap-2 border-t p-3">
          <input value={input} onChange={(e) => setInput(e.target.value)} placeholder="お客様として話しかける…"
            className="flex-1 rounded-full border px-4 py-2 text-sm focus:border-brand focus:outline-none" />
          <button disabled={sending || !input.trim()}
            className="rounded-full bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-40">送信</button>
          <button type="button" onClick={reset} className="rounded-full border px-3 py-2 text-sm text-gray-500 hover:bg-gray-50">リセット</button>
        </form>
      </Card>

      <p className="mt-4 text-sm text-gray-500">
        応対内容を変えたい場合は <Link href="/faqs" className="text-brand hover:underline">FAQ管理</Link> や
        <Link href="/settings/ai" className="text-brand hover:underline"> AI設定</Link> を編集してください。
        ここでの会話は記録されません（通話履歴には残りません）。
      </p>
    </div>
  );
}
