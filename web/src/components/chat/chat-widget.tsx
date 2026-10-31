"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState, type ReactNode } from "react";
import { Namisuke, type NamisukeMood } from "@/components/chat/namisuke";

type Msg = { role: "user" | "assistant"; content: string };

const GREETING: Msg = {
  role: "assistant",
  content:
    "やあ！波の妖精「なみすけ」だよ🏄\n今日の波や潮回り、ポイント選び、はじめてのサーフィンのことなど、気軽に聞いてね！",
};

const SUGGESTIONS = [
  "今日の岩沢海岸の波は？",
  "初心者におすすめのポイントは？",
  "湘南と九十九里、明日はどっちがいい？",
  "はじめての海、持ち物は？",
];

const STORAGE_KEY = "iwasawa-chat-v1";

/** 本文中の "/spots/xxx" などのサイト内パスをリンクにする */
const PATH_RE =
  /(\/(?:spots(?:\/area)?(?:\/[a-z0-9_-]+)?|waves|partners|instructors|courses|rules|faq|contact|about|community|skills|articles))(?![\w/-])/g;

function linkify(text: string, onNavigate: () => void): ReactNode[] {
  const out: ReactNode[] = [];
  let last = 0;
  for (const m of text.matchAll(PATH_RE)) {
    const i = m.index ?? 0;
    if (i > last) out.push(text.slice(last, i));
    out.push(
      <Link key={i} href={m[1]} onClick={onNavigate} className="font-medium text-ocean underline underline-offset-2">
        {m[1]}
      </Link>,
    );
    last = i + m[0].length;
  }
  if (last < text.length) out.push(text.slice(last));
  return out;
}

export function ChatWidget() {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Msg[]>([GREETING]);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [status, setStatus] = useState<string | null>(null);
  const [cheer, setCheer] = useState(false);
  const [hoverLauncher, setHoverLauncher] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);
  const abortRef = useRef<AbortController | null>(null);

  // 会話はタブを閉じるまで保持（ベストエフォート）。初めて開いた時に復元する
  const restoredRef = useRef(false);
  function openChat() {
    if (!restoredRef.current) {
      restoredRef.current = true;
      try {
        const saved = sessionStorage.getItem(STORAGE_KEY);
        const parsed = saved ? (JSON.parse(saved) as Msg[]) : null;
        if (Array.isArray(parsed) && parsed.length > 0) setMessages(parsed);
      } catch {
        /* 保存できない環境では無視 */
      }
    }
    setOpen(true);
  }
  useEffect(() => {
    if (!restoredRef.current) return;
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages.slice(-20)));
    } catch {
      /* 無視 */
    }
  }, [messages]);

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight });
  }, [messages, status, open]);

  useEffect(() => () => abortRef.current?.abort(), []);

  if (pathname?.startsWith("/admin")) return null;

  async function send(text: string) {
    const content = text.trim();
    if (!content || busy) return;
    const history: Msg[] = [...messages, { role: "user", content }];
    setMessages([...history, { role: "assistant", content: "" }]);
    setInput("");
    setBusy(true);
    setStatus(null);

    const ac = new AbortController();
    abortRef.current = ac;
    const append = (delta: string) =>
      setMessages((prev) => {
        const next = prev.slice();
        const lastMsg = next[next.length - 1];
        next[next.length - 1] = { ...lastMsg, content: lastMsg.content + delta };
        return next;
      });

    try {
      const res = await fetch("/api/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ messages: history.slice(-20) }),
        signal: ac.signal,
      });
      if (!res.ok || !res.body) {
        const err = (await res.json().catch(() => null)) as { error?: string } | null;
        append(err?.error ?? "うまく応答できませんでした。もう一度お試しください。");
        return;
      }
      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let buf = "";
      for (;;) {
        const { value, done } = await reader.read();
        if (done) break;
        buf += decoder.decode(value, { stream: true });
        let nl: number;
        while ((nl = buf.indexOf("\n")) >= 0) {
          const raw = buf.slice(0, nl).trim();
          buf = buf.slice(nl + 1);
          if (!raw) continue;
          try {
            const ev = JSON.parse(raw) as { t: string; v: string };
            if (ev.t === "text") {
              setStatus(null);
              append(ev.v);
            } else if (ev.t === "status") {
              setStatus(ev.v);
            } else if (ev.t === "error") {
              append(ev.v);
            }
          } catch {
            /* 壊れた行は無視 */
          }
        }
      }
    } catch {
      if (!ac.signal.aborted) append("通信が切れました。もう一度お試しください。");
    } finally {
      setBusy(false);
      setStatus(null);
      // 答え終わったら少しのあいだ、ごきげん
      setCheer(true);
      window.setTimeout(() => setCheer(false), 1800);
    }
  }

  // スマホでは全画面に近いので、ページ移動したら閉じる
  function closeOnSmallScreen() {
    if (window.matchMedia("(max-width: 639px)").matches) setOpen(false);
  }

  function reset() {
    abortRef.current?.abort();
    setMessages([GREETING]);
    setBusy(false);
    setStatus(null);
  }

  const showSuggestions = messages.length === 1 && !busy;
  const lastMsg = messages[messages.length - 1];
  const waiting = busy && lastMsg?.role === "assistant" && lastMsg.content === "";
  const mood: NamisukeMood = waiting ? "thinking" : busy ? "talking" : cheer ? "happy" : "idle";

  return (
    <>
      {!open ? (
        <div
          className="fixed right-3 z-40 flex flex-col items-end"
          style={{ bottom: "calc(0.75rem + env(safe-area-inset-bottom))" }}
        >
          <div className="launcher-bubble pointer-events-none mb-1 mr-2 rounded-2xl rounded-br-sm bg-white px-3 py-1.5 text-xs font-medium text-navy shadow-md">
            波のこと、聞いて！🌊
          </div>
          <button
            type="button"
            onClick={openChat}
            onMouseEnter={() => setHoverLauncher(true)}
            onMouseLeave={() => setHoverLauncher(false)}
            onFocus={() => setHoverLauncher(true)}
            onBlur={() => setHoverLauncher(false)}
            className="group flex items-center gap-1 rounded-full bg-white/90 py-1 pl-1 pr-4 shadow-lg ring-1 ring-ocean/20 backdrop-blur transition hover:scale-105 hover:ring-ocean/50"
            aria-label="AIサーフアシスタント「なみすけ」に質問する"
          >
            <Namisuke mood={hoverLauncher ? "wave" : "idle"} size={56} />
            <span className="text-sm font-semibold text-ocean">AIに聞く</span>
          </button>
        </div>
      ) : null}

      {open ? (
        <div
          role="dialog"
          aria-label="AIサーフアシスタント"
          className="chat-pop fixed inset-x-0 bottom-0 z-50 flex h-[85dvh] flex-col overflow-hidden rounded-t-2xl border border-navy/10 bg-white shadow-2xl sm:inset-x-auto sm:right-4 sm:bottom-4 sm:h-[600px] sm:w-[380px] sm:rounded-2xl"
        >
          {/* ヘッダー */}
          <div className="flex items-center justify-between bg-ocean-gradient px-4 py-3 text-foam">
            <div className="flex items-center gap-2">
              <div className="rounded-full bg-white/15 p-0.5">
                <Namisuke mood={mood} size={44} />
              </div>
              <div>
                <p className="text-sm font-semibold">なみすけ</p>
                <p className="text-[0.65rem] text-sand/85">
                  {mood === "thinking"
                    ? status ?? "波を読んでいます…"
                    : mood === "talking"
                      ? "お話し中…"
                      : "波・潮・警報を見て答えるよ"}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-1">
              <button
                type="button"
                onClick={reset}
                className="whitespace-nowrap rounded-full px-2.5 py-1 text-xs text-sand/90 hover:bg-white/15"
              >
                リセット
              </button>
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="rounded-full px-2.5 py-1 text-lg leading-none hover:bg-white/15"
                aria-label="閉じる"
              >
                ×
              </button>
            </div>
          </div>

          {/* メッセージ */}
          <div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto bg-foam px-3 py-4">
            {messages.map((m, i) =>
              m.role === "assistant" && m.content === "" ? null : (
                <div
                  key={i}
                  className={`msg-in flex items-end gap-1.5 ${m.role === "user" ? "justify-end" : "justify-start"}`}
                >
                  {m.role === "assistant" ? (
                    <Namisuke
                      mood={i === messages.length - 1 && busy ? "talking" : "idle"}
                      size={30}
                      className="shrink-0"
                    />
                  ) : null}
                  <div
                    className={`max-w-[85%] whitespace-pre-wrap rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed ${
                      m.role === "user"
                        ? "rounded-br-sm bg-ocean text-foam"
                        : "rounded-bl-sm border border-navy/10 bg-white text-navy/85"
                    }`}
                  >
                    {m.role === "assistant" ? linkify(m.content, closeOnSmallScreen) : m.content}
                  </div>
                </div>
              ),
            )}
            {busy && messages[messages.length - 1]?.content === "" ? (
              <div className="msg-in flex items-end gap-1.5">
                <Namisuke mood="thinking" size={30} className="shrink-0" />
                <div className="rounded-2xl rounded-bl-sm border border-navy/10 bg-white px-3.5 py-2.5 text-sm text-navy/55">
                  {status ?? "考えています"}
                  <span className="dot-wave ml-1 text-ocean" aria-hidden="true">
                    <span>●</span>
                    <span>●</span>
                    <span>●</span>
                  </span>
                </div>
              </div>
            ) : status ? (
              <p className="text-center text-xs text-navy/50">{status}</p>
            ) : null}

            {showSuggestions ? (
              <div className="msg-in flex flex-wrap gap-2 pl-9 pt-1">
                {SUGGESTIONS.map((s) => (
                  <button
                    key={s}
                    type="button"
                    onClick={() => send(s)}
                    className="rounded-full border border-ocean/30 bg-white px-3 py-1.5 text-xs text-ocean transition hover:bg-ocean/10"
                  >
                    {s}
                  </button>
                ))}
              </div>
            ) : null}
          </div>

          {/* 入力 */}
          <form
            onSubmit={(e) => {
              e.preventDefault();
              send(input);
            }}
            className="border-t border-navy/10 bg-white p-3"
            style={{ paddingBottom: "calc(0.75rem + env(safe-area-inset-bottom))" }}
          >
            <div className="flex items-end gap-2">
              <textarea
                value={input}
                onChange={(e) => setInput(e.target.value.slice(0, 1000))}
                onKeyDown={(e) => {
                  if (e.key === "Enter" && !e.shiftKey && !e.nativeEvent.isComposing) {
                    e.preventDefault();
                    send(input);
                  }
                }}
                rows={1}
                placeholder="波やポイントのことを質問…"
                className="max-h-28 min-h-[2.5rem] flex-1 resize-none rounded-xl border border-navy/15 px-3 py-2 text-base text-navy outline-none focus:border-ocean sm:text-sm"
              />
              <button
                type="submit"
                disabled={busy || !input.trim()}
                className="rounded-xl bg-ocean px-4 py-2 text-sm font-semibold text-foam transition hover:bg-navy disabled:opacity-40"
              >
                送信
              </button>
            </div>
            <p className="mt-1.5 text-[0.6rem] text-navy/40">
              AI（Claude）の回答です。予報は目安なので、海の状況は現地で必ず確認してください。
            </p>
          </form>
        </div>
      ) : null}
    </>
  );
}
