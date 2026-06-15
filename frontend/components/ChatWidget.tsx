'use client';

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { sendChat, type ChatTurn } from '@/lib/api';

// 動画アバターのURL（任意）。未設定ならアニメーションのフォールバックを表示。
const AVATAR_VIDEO = process.env.NEXT_PUBLIC_CHATBOT_VIDEO ?? '';
const GREETING = 'こんにちは！AIオペレーター24のAIアシスタントです🤖 料金・機能・無料デモなど、お気軽にお尋ねください。';

export default function ChatWidget() {
  const [open, setOpen] = useState(false);
  const [input, setInput] = useState('');
  const [sending, setSending] = useState(false);
  const [messages, setMessages] = useState<ChatTurn[]>([{ role: 'assistant', content: GREETING }]);
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
  }, [messages, open]);

  async function send(e: React.FormEvent) {
    e.preventDefault();
    const text = input.trim();
    if (!text || sending) return;
    const history = messages.filter((m) => m.role === 'user' || m.role === 'assistant');
    setMessages((prev) => [...prev, { role: 'user', content: text }]);
    setInput('');
    setSending(true);
    try {
      const reply = await sendChat(text, history);
      setMessages((prev) => [...prev, { role: 'assistant', content: reply }]);
    } catch {
      setMessages((prev) => [...prev, { role: 'assistant', content: '通信エラーが発生しました。少し後に再度お試しください。' }]);
    } finally {
      setSending(false);
    }
  }

  return (
    <>
      {/* 起動ボタン */}
      {!open && (
        <button
          onClick={() => setOpen(true)}
          aria-label="AIチャットを開く"
          className="fixed bottom-5 right-5 z-50 flex items-center gap-2 rounded-full bg-brand px-5 py-3 text-white shadow-xl transition hover:bg-brand-dark"
        >
          <Avatar size={28} />
          <span className="text-sm font-semibold">AIに質問</span>
        </button>
      )}

      {/* チャットパネル */}
      {open && (
        <div className="fixed bottom-5 right-5 z-50 flex h-[32rem] w-[92vw] max-w-sm flex-col overflow-hidden rounded-2xl border bg-white shadow-2xl">
          {/* ヘッダ（動画アバター） */}
          <div className="flex items-center gap-3 bg-brand px-4 py-3 text-white">
            <Avatar size={44} speaking={sending} />
            <div className="flex-1">
              <div className="text-sm font-bold">AIオペレーター24 アシスタント</div>
              <div className="text-xs text-brand-light">{sending ? '入力中…' : 'オンライン'}</div>
            </div>
            <button onClick={() => setOpen(false)} aria-label="閉じる" className="text-white/80 hover:text-white">✕</button>
          </div>

          {/* メッセージ */}
          <div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto bg-gray-50 p-3">
            {messages.map((m, i) => (
              <div key={i} className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                <div className={`max-w-[85%] whitespace-pre-wrap rounded-2xl px-3 py-2 text-sm ${
                  m.role === 'user' ? 'bg-brand text-white' : 'border bg-white text-gray-800'}`}>
                  {m.content}
                </div>
              </div>
            ))}
            {sending && (
              <div className="flex justify-start">
                <div className="rounded-2xl border bg-white px-3 py-2 text-sm text-gray-400">●●●</div>
              </div>
            )}
          </div>

          {/* 入力 */}
          <form onSubmit={send} className="flex items-center gap-2 border-t bg-white p-2">
            <input
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="メッセージを入力…"
              className="flex-1 rounded-full border px-4 py-2 text-sm focus:border-brand focus:outline-none"
            />
            <button disabled={sending || !input.trim()}
              className="rounded-full bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-40">
              送信
            </button>
          </form>
          <div className="bg-white pb-2 text-center text-[11px] text-gray-400">
            詳しいご相談は <Link href="/contact" className="text-brand hover:underline">お問い合わせフォーム</Link> へ
          </div>
        </div>
      )}
    </>
  );
}

// 動画アバター（未設定時はアニメーションのフォールバック）。
function Avatar({ size, speaking }: { size: number; speaking?: boolean }) {
  return (
    <div
      className={`relative shrink-0 overflow-hidden rounded-full ring-2 ring-white/70 ${speaking ? 'animate-pulse' : ''}`}
      style={{ width: size, height: size }}
    >
      {AVATAR_VIDEO ? (
        <video src={AVATAR_VIDEO} autoPlay loop muted playsInline className="h-full w-full object-cover" />
      ) : (
        <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-sky-400 via-brand to-indigo-500">
          <span style={{ fontSize: size * 0.5 }}>🤖</span>
        </div>
      )}
    </div>
  );
}
