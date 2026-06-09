/**
 * chatbot.js
 * ------------------------------------------------------------------
 * OpenRouter 経由のAI会話エンジン（クライアント側）。
 *
 * セキュリティ方針:
 *   - APIキーはクライアントに置かない。サーバープロキシ(/api/chat)を経由する。
 *   - 会話内容に個人情報を保存しない（localStorage等に履歴を残さない）。
 *     (仕様定義書 第2部 17. セキュリティ/プライバシー配慮)
 *
 * フォールバック方針:
 *   - バックエンド未設定・通信失敗時はAI応答を諦め、LINE/電話の有人導線へ誘導。
 *     (18. 障害時のフォールバック)
 *
 * 会話履歴はメモリ上のみ（ページ離脱で消える）。
 */

import { CHAT_CONFIG } from './config.js';
import { track } from './analytics.js';

const cfg = CHAT_CONFIG.chatbot;

let started = false;
let streaming = false;
/** @type {{role:'user'|'assistant', content:string}[]} */
const history = [];

const dom = {
  thread: null,
  suggestions: null,
  form: null,
  text: null,
  send: null
};

/* ---------- 描画ヘルパ ---------- */

function appendMessage(role, text) {
  const el = document.createElement('div');
  el.className = `chat-msg chat-msg--${role === 'user' ? 'user' : 'bot'}`;
  el.textContent = text;
  dom.thread.appendChild(el);
  scrollToBottom();
  return el;
}

function showTyping() {
  const el = document.createElement('div');
  el.className = 'chat-msg chat-msg--bot';
  el.innerHTML =
    '<span class="chat-typing"><span></span><span></span><span></span></span>';
  dom.thread.appendChild(el);
  scrollToBottom();
  return el;
}

function scrollToBottom() {
  dom.thread.scrollTop = dom.thread.scrollHeight;
}

function renderSuggestions() {
  dom.suggestions.innerHTML = '';
  (cfg.suggestions || []).forEach((q) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'chat-suggestion';
    btn.textContent = q;
    btn.addEventListener('click', () => {
      track(cfg.events.suggestionClick, { question: q });
      sendMessage(q);
    });
    dom.suggestions.appendChild(btn);
  });
}

function clearSuggestions() {
  dom.suggestions.innerHTML = '';
}

/* ---------- 送受信 ---------- */

async function sendMessage(rawText) {
  const text = (rawText ?? dom.text.value).trim();
  if (!text || streaming) return;

  clearSuggestions();
  appendMessage('user', text);
  history.push({ role: 'user', content: text });
  dom.text.value = '';
  autoResize();

  track(cfg.events.messageSent);
  streaming = true;
  setInputEnabled(false);

  const typingEl = showTyping();
  let botEl = null;
  let answer = '';

  const onDelta = (delta) => {
    if (!botEl) {
      typingEl.remove();
      botEl = appendMessage('assistant', '');
    }
    answer += delta;
    botEl.textContent = answer;
    scrollToBottom();
  };

  try {
    await streamFromServer(history.slice(-cfg.maxHistory), onDelta);
    if (!answer) throw new Error('empty response');
    history.push({ role: 'assistant', content: answer });
    track(cfg.events.responseReceived);
  } catch (err) {
    if (typingEl.isConnected) typingEl.remove();
    showFallback();
    track(cfg.events.error, { message: String(err && err.message) });
  } finally {
    streaming = false;
    setInputEnabled(true);
    dom.text.focus();
  }
}

/**
 * サーバープロキシから SSE ストリームを読み取り、差分を onDelta に渡す。
 * SSE 形式: `data: {"delta":"..."}` / 終了 `data: [DONE]` / 異常 `data: {"error":"..."}`
 */
async function streamFromServer(messages, onDelta) {
  const res = await fetch(cfg.endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ messages })
  });

  if (!res.ok || !res.body) {
    throw new Error(`bad response: ${res.status}`);
  }

  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';

  for (;;) {
    const { value, done } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });

    const lines = buffer.split('\n');
    buffer = lines.pop() ?? ''; // 未完成の行は次回へ

    for (const line of lines) {
      const trimmed = line.trim();
      if (!trimmed.startsWith('data:')) continue;
      const payload = trimmed.slice(5).trim();
      if (payload === '[DONE]') return;
      try {
        const obj = JSON.parse(payload);
        if (obj.error) throw new Error(obj.error);
        if (obj.delta) onDelta(obj.delta);
      } catch (e) {
        if (e.message && e.message !== 'Unexpected end of JSON input') {
          // error ペイロードは上位へ
          if (/error/.test(payload)) throw e;
        }
      }
    }
  }
}

function showFallback() {
  appendMessage('assistant', cfg.fallbackMessage);
  // エスカレーション導線（CTA）はDOM下部に常設済み。視認のためスクロール。
  scrollToBottom();
}

/* ---------- 入力UI ---------- */

function setInputEnabled(enabled) {
  dom.text.disabled = !enabled;
  dom.send.disabled = !enabled;
}

function autoResize() {
  dom.text.style.height = 'auto';
  dom.text.style.height = Math.min(dom.text.scrollHeight, 96) + 'px';
}

/* ---------- 初期化 ---------- */

export function initChatbot() {
  if (started || !cfg || cfg.enabled === false) return false;

  dom.thread = document.querySelector('[data-role="chat-thread"]');
  dom.suggestions = document.querySelector('[data-role="chat-suggestions"]');
  dom.form = document.querySelector('[data-role="chat-form"]');
  dom.text = document.querySelector('[data-role="chat-text"]');
  dom.send = dom.form?.querySelector('[data-action="chat-send"]');

  if (!dom.thread || !dom.form || !dom.text || !dom.send) return false;
  started = true;

  // 初期グリーティング + サジェスト
  appendMessage('assistant', cfg.greeting);
  renderSuggestions();
  track(cfg.events.chatStart);

  dom.form.addEventListener('submit', (e) => {
    e.preventDefault();
    sendMessage();
  });

  // Enterで送信 / Shift+Enterで改行
  dom.text.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  dom.text.addEventListener('input', autoResize);

  return true;
}

export default initChatbot;
