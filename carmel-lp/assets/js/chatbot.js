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
const hcfg = CHAT_CONFIG.handoff;
const bcfg = CHAT_CONFIG.booking;

let started = false;
let streaming = false;
/** @type {{role:'user'|'assistant', content:string}[]} */
const history = [];

/** 有人対応(Slack)の状態。 */
const handoff = {
  available: false, // バックエンドが有効か（status由来）
  active: false, // 有人セッション進行中か
  connected: false, // 担当者が応答したか
  sessionId: null,
  pollTimer: null,
  giveupTimer: null
};

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
  const variant = role === 'user' ? 'user' : role === 'operator' ? 'operator' : 'bot';
  el.className = `chat-msg chat-msg--${variant}`;
  if (role === 'operator') {
    const tag = document.createElement('span');
    tag.className = 'chat-msg__who';
    tag.textContent = hcfg.operatorName;
    el.appendChild(tag);
    el.appendChild(document.createTextNode(text));
  } else {
    el.textContent = text;
  }
  dom.thread.appendChild(el);
  scrollToBottom();
  return el;
}

function appendNote(text) {
  const el = document.createElement('div');
  el.className = 'chat-note';
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

  // 有人対応中はAIではなく担当者(Slack)へ中継する
  if (handoff.active) {
    appendMessage('user', text);
    dom.text.value = '';
    autoResize();
    relayToOperator(text);
    return;
  }

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
    renderCtaButtons(); // 回答ごとに有人相談（LINE/電話）へ誘導
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

/**
 * 回答の直後に「LINEで相談 / 電話で相談」CTAを表示し、有人窓口へ誘導する。
 * 文言・リンクは config.js（CHAT_CONFIG）に集約。クリックは計測する。
 */
function renderCtaButtons() {
  const wrap = document.createElement('div');
  wrap.className = 'chat-msg__cta';

  const line = document.createElement('a');
  line.className = 'chat-cta chat-cta--line';
  line.href = CHAT_CONFIG.lineUrl;
  line.target = '_blank';
  line.rel = 'noopener';
  line.textContent = '💬 LINEで相談';
  line.addEventListener('click', () => track(CHAT_CONFIG.events.ctaLineClick, { from: 'chatbot' }));

  const tel = document.createElement('a');
  tel.className = 'chat-cta chat-cta--tel';
  tel.href = CHAT_CONFIG.telUrl;
  tel.textContent = `📞 ${CHAT_CONFIG.telDisplay}`;
  tel.addEventListener('click', () => track(CHAT_CONFIG.events.ctaTelClick, { from: 'chatbot' }));

  wrap.append(line, tel);
  dom.thread.appendChild(wrap);
  scrollToBottom();
}

function showFallback() {
  appendMessage('assistant', cfg.fallbackMessage);
  // エスカレーション導線（CTA）はDOM下部に常設済み。視認のためスクロール。
  scrollToBottom();
}

/* ---------- 有人ハイブリッド対応（Slack連携） ---------- */

/** 起動時にバックエンドの有効状態を取得し、入口ボタンを出す。 */
async function initHandoff() {
  try {
    const res = await fetch('/api/handoff/status');
    if (!res.ok) return;
    const s = await res.json();
    handoff.available = Boolean(s.enabled);
  } catch (_e) {
    handoff.available = false;
  }
  if (handoff.available) renderHandoffEntry();
}

/** 「担当者と話す」入口ボタンをサジェスト領域に追加。 */
function renderHandoffEntry() {
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'chat-suggestion chat-suggestion--accent';
  btn.textContent = hcfg.entryLabel;
  btn.addEventListener('click', () => startHandoff());
  dom.suggestions.appendChild(btn);
}

/**
 * 有人対応につながらなかった後、AIで会話を続けられるよう導線を復帰する。
 * （担当者は営業時間内のみ。時間外・不在でもAIは常に応答する）
 */
function restoreAiEntry() {
  appendNote(hcfg.aiContinueNote);
  renderSuggestions();
  renderBookingEntry();
  if (handoff.available) renderHandoffEntry();
}

/** 有人対応を開始。営業時間外/不在時はフォールバックUIを出す。 */
async function startHandoff() {
  if (handoff.active || streaming) return;
  clearSuggestions();
  track(hcfg.events.start);

  // 直近のユーザー発話を相談内容として渡す
  const lastUser = [...history].reverse().find((m) => m.role === 'user');
  const question = lastUser ? lastUser.content : '（LPチャットから担当者希望）';

  const typingEl = showTyping();
  let started;
  try {
    const res = await fetch('/api/handoff/start', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question })
    });
    started = await res.json();
  } catch (_e) {
    started = { available: false, reason: 'error' };
  }
  typingEl.remove();

  if (!started || !started.available) {
    // 時間外/未設定でも担当者にはつながない＝AIが引き続き対応する。
    // handoff.active は false のままなので、以降の入力は通常どおりAIへ。
    if (started && started.reason === 'off-hours') {
      appendNote(hcfg.offHoursMessage);
      track(hcfg.events.offHours);
    } else {
      appendMessage('assistant', cfg.fallbackMessage);
    }
    renderCtaButtons();
    renderCallbackForm();
    restoreAiEntry(); // AIで会話を続けられるよう導線を復帰（行き止まりにしない）
    return;
  }

  handoff.active = true;
  handoff.connected = false;
  handoff.sessionId = started.sessionId;
  appendNote(hcfg.connectingMessage);

  // 担当者の返信をポーリング
  handoff.pollTimer = setInterval(pollOperator, hcfg.pollIntervalMs);
  // 規定時間内に応答が無ければ不在として案内
  handoff.giveupTimer = setTimeout(
    onOperatorTimeout,
    Number(started.timeoutMs) || 20000
  );
}

/** お客様メッセージを担当者(Slack)へ中継。 */
async function relayToOperator(text) {
  try {
    await fetch('/api/handoff/send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sessionId: handoff.sessionId, text })
    });
  } catch (_e) {
    appendNote('送信に失敗しました。通信状況をご確認ください。');
  }
}

/** 担当者の新規返信を取得して表示。最初の応答でタイムアウトを解除。 */
async function pollOperator() {
  if (!handoff.active) return;
  try {
    const res = await fetch(
      `/api/handoff/poll?sessionId=${encodeURIComponent(handoff.sessionId)}`
    );
    if (!res.ok) return;
    const data = await res.json();
    (data.messages || []).forEach((m) => {
      if (!handoff.connected) {
        handoff.connected = true;
        clearTimeout(handoff.giveupTimer);
        appendNote(hcfg.connectedNote);
        track(hcfg.events.connected);
      }
      appendMessage('operator', m.text);
    });
  } catch (_e) {
    /* 一時的な失敗は無視（次回ポーリングで回復） */
  }
}

/** 規定時間内に担当者応答が無かった場合の案内。 */
function onOperatorTimeout() {
  if (handoff.connected) return; // すでに接続済みなら何もしない
  stopHandoff();
  appendNote(hcfg.unavailableMessage);
  track(hcfg.events.unavailable);
  renderCtaButtons();
  renderCallbackForm();
  restoreAiEntry(); // 担当者につながらなくてもAIで継続できるように
}

/** 有人セッションを終了（ポーリング/タイマー停止）。 */
function stopHandoff() {
  handoff.active = false;
  clearInterval(handoff.pollTimer);
  clearTimeout(handoff.giveupTimer);
  if (handoff.sessionId) {
    fetch('/api/handoff/end', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sessionId: handoff.sessionId })
    }).catch(() => {});
  }
}

/** 後日連絡フォームをスレッド内に表示。 */
function renderCallbackForm() {
  const c = hcfg.callback;
  const form = document.createElement('form');
  form.className = 'chat-callback';
  form.innerHTML = `
    <p class="chat-callback__title">${c.title}</p>
    <input class="chat-callback__input" name="name" placeholder="${c.nameLabel}" autocomplete="name" />
    <input class="chat-callback__input" name="contact" placeholder="${c.contactLabel}" />
    <textarea class="chat-callback__input" name="message" rows="2" placeholder="${c.messageLabel}"></textarea>
    <button class="chat-callback__submit" type="submit">${c.submitLabel}</button>
  `;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const payload = {
      name: fd.get('name'),
      contact: fd.get('contact'),
      message: fd.get('message')
    };
    if (!String(payload.contact || '').trim()) {
      form.querySelector('[name="contact"]').focus();
      return;
    }
    const submit = form.querySelector('button');
    submit.disabled = true;
    try {
      await fetch('/api/handoff/callback', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      form.replaceWith(makeNote(c.doneMessage));
      track(hcfg.events.callbackSent);
    } catch (_e) {
      submit.disabled = false;
      appendNote('送信に失敗しました。お手数ですがLINE・お電話をご利用ください。');
    }
  });
  dom.thread.appendChild(form);
  scrollToBottom();
}

function makeNote(text) {
  const el = document.createElement('div');
  el.className = 'chat-note';
  el.textContent = text;
  return el;
}

/* ---------- 予約（来店・査定・電話相談） ---------- */

/** 「来店・査定を予約」入口ボタンをサジェスト領域に追加（常時）。 */
function renderBookingEntry() {
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'chat-suggestion chat-suggestion--book';
  btn.textContent = bcfg.entryLabel;
  btn.addEventListener('click', () => {
    clearSuggestions();
    renderBookingForm();
    // 続けてAI/予約導線に戻れるように
    renderSuggestions();
    renderBookingEntry();
    if (handoff.available) renderHandoffEntry();
  });
  dom.suggestions.appendChild(btn);
}

/** 予約フォームをスレッド内に表示。送信で /api/handoff/reserve に送る。 */
function renderBookingForm() {
  const form = document.createElement('form');
  form.className = 'chat-callback';
  const opts = (arr) => arr.map((v) => `<option value="${v}">${v}</option>`).join('');
  form.innerHTML = `
    <p class="chat-callback__title">${bcfg.title}</p>
    <label class="chat-callback__label">${bcfg.typeLabel}</label>
    <select class="chat-callback__input" name="type">${opts(bcfg.types)}</select>
    <label class="chat-callback__label">${bcfg.dateLabel}</label>
    <input class="chat-callback__input" type="date" name="date" />
    <label class="chat-callback__label">${bcfg.timeLabel}</label>
    <select class="chat-callback__input" name="time">${opts(bcfg.times)}</select>
    <input class="chat-callback__input" name="name" placeholder="${bcfg.nameLabel}" autocomplete="name" />
    <input class="chat-callback__input" name="contact" placeholder="${bcfg.contactLabel}" />
    <textarea class="chat-callback__input" name="note" rows="2" placeholder="${bcfg.noteLabel}"></textarea>
    <button class="chat-callback__submit" type="submit">${bcfg.submitLabel}</button>
  `;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const payload = {
      type: fd.get('type'),
      date: fd.get('date'),
      time: fd.get('time'),
      name: fd.get('name'),
      contact: fd.get('contact'),
      note: fd.get('note')
    };
    // 最低限：連絡先と希望日は必須
    if (!String(payload.contact || '').trim()) {
      form.querySelector('[name="contact"]').focus();
      return;
    }
    if (!String(payload.date || '').trim()) {
      form.querySelector('[name="date"]').focus();
      return;
    }
    const submit = form.querySelector('button');
    submit.disabled = true;
    try {
      await fetch('/api/handoff/reserve', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      form.replaceWith(makeNote(bcfg.doneMessage));
      track(bcfg.event, { type: payload.type });
    } catch (_e) {
      submit.disabled = false;
      appendNote('送信に失敗しました。お手数ですがLINE・お電話をご利用ください。');
    }
  });
  dom.thread.appendChild(form);
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
  renderBookingEntry(); // 「来店・査定を予約」（常時表示）
  initHandoff(); // 有人対応が有効なら「担当者と話す」を追加表示
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
