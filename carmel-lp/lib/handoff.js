/**
 * lib/handoff.js  (CommonJS / サーバー共有)
 * ------------------------------------------------------------------
 * 有人ハイブリッド対応のコア。Webチャットと Slack を橋渡しする。
 *
 *  - 営業時間内 & Slack設定あり → お客様の相談を Slack スレッドへ通知。
 *    担当者がスレッドに返信すると、その内容をチャット側へ返す。
 *  - 時間外 / Slack未設定 → 本機能は無効。呼び出し側はAI応答へフォールバック。
 *  - 担当者が一定時間(既定20秒)応答しない場合の扱いは呼び出し側(フロント)で制御。
 *
 * 必要な環境変数:
 *   SLACK_BOT_TOKEN   … Slack Bot トークン(xoxb-)。chat:write / channels:history 等の権限。
 *   SLACK_CHANNEL     … 通知先チャンネルID(例 C0123ABCD)。
 *   (任意) BUSINESS_HOURS_START=10 / BUSINESS_HOURS_END=19 / BUSINESS_TZ_OFFSET=9(JST)
 *   (任意) BUSINESS_DAYS="0,1,2,3,4,5,6"（曜日: 日=0）
 *   (任意) HANDOFF_TIMEOUT_MS=20000
 *   (任意) SLACK_BASE_URL（テスト用にモックへ差し替え）
 *
 * セッションはメモリ上のみ（Nodeサーバー前提）。サーバーレスでは永続ストアが別途必要。
 */

'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

function slackBase() {
  return process.env.SLACK_BASE_URL || 'https://slack.com/api';
}

function num(v, def) {
  const n = Number(v);
  return Number.isFinite(n) ? n : def;
}

function parseDays(v) {
  if (!v) return [0, 1, 2, 3, 4, 5, 6];
  return v
    .split(',')
    .map((s) => Number(s.trim()))
    .filter((n) => Number.isInteger(n) && n >= 0 && n <= 6);
}

/** 現在の設定値をまとめて返す。 */
function config() {
  return {
    token: process.env.SLACK_BOT_TOKEN || '',
    channel: process.env.SLACK_CHANNEL || '',
    tzOffset: num(process.env.BUSINESS_TZ_OFFSET, 9), // 既定: JST(+9)
    start: num(process.env.BUSINESS_HOURS_START, 10),
    end: num(process.env.BUSINESS_HOURS_END, 19),
    days: parseDays(process.env.BUSINESS_DAYS),
    timeoutMs: num(process.env.HANDOFF_TIMEOUT_MS, 20000)
  };
}

/** Slack連携が設定済みか（トークン＋チャンネル）。 */
function isEnabled() {
  const c = config();
  return Boolean(c.token && c.channel);
}

/**
 * 指定時刻が営業時間内か判定する。tzOffset 時間ずらした壁掛け時計で評価。
 * @param {Date} [now]
 */
function isWithinBusinessHours(now, c = config()) {
  const base = now instanceof Date ? now : new Date();
  const local = new Date(base.getTime() + c.tzOffset * 3600 * 1000);
  const day = local.getUTCDay();
  const hour = local.getUTCHours() + local.getUTCMinutes() / 60;
  return c.days.includes(day) && hour >= c.start && hour < c.end;
}

/* ---------- Slack Web API 薄ラッパ ---------- */

async function slackPost(method, body) {
  const c = config();
  const res = await fetch(`${slackBase()}/${method}`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${c.token}`,
      'Content-Type': 'application/json; charset=utf-8'
    },
    body: JSON.stringify(body)
  });
  const json = await res.json().catch(() => ({}));
  if (!json.ok) throw new Error(`slack ${method}: ${json.error || res.status}`);
  return json;
}

async function slackGet(method, params) {
  const c = config();
  const qs = new URLSearchParams(params).toString();
  const res = await fetch(`${slackBase()}/${method}?${qs}`, {
    headers: { Authorization: `Bearer ${c.token}` }
  });
  const json = await res.json().catch(() => ({}));
  if (!json.ok) throw new Error(`slack ${method}: ${json.error || res.status}`);
  return json;
}

/* ---------- セッション管理（メモリ） ---------- */

/** @type {Map<string,{channel:string,threadTs:string,lastTs:string,createdAt:number}>} */
const sessions = new Map();
const SESSION_TTL_MS = 60 * 60 * 1000; // 1時間で破棄

function gc() {
  const now = Date.now();
  for (const [id, s] of sessions) {
    if (now - s.createdAt > SESSION_TTL_MS) sessions.delete(id);
  }
}

/**
 * 有人対応を開始。Slackに親メッセージ（相談スレッド）を作成する。
 * @param {string} question お客様の最初の相談内容
 * @returns {Promise<{sessionId:string, timeoutMs:number}>}
 */
async function startHandoff(question) {
  gc();
  const c = config();
  const text =
    `🆕 *LPチャットに新しい相談が入りました*\n` +
    `> ${(question || '(内容なし)').slice(0, 500)}\n` +
    `_このスレッドに返信すると、お客様のチャット画面に届きます。_`;
  const posted = await slackPost('chat.postMessage', { channel: c.channel, text });
  const sessionId = crypto.randomUUID();
  sessions.set(sessionId, {
    channel: posted.channel || c.channel,
    threadTs: posted.ts,
    lastTs: posted.ts,
    createdAt: Date.now()
  });
  return { sessionId, timeoutMs: c.timeoutMs };
}

/** お客様の追加メッセージを Slack スレッドへ流す。 */
async function sendUserMessage(sessionId, text) {
  const s = sessions.get(sessionId);
  if (!s) throw new Error('handoff session not found');
  await slackPost('chat.postMessage', {
    channel: s.channel,
    thread_ts: s.threadTs,
    text: `🙋 お客様: ${String(text || '').slice(0, 1000)}`
  });
  return { ok: true };
}

/**
 * 担当者(人間)のスレッド返信を取得する。前回以降の新着のみ返す。
 * 自分(ボット)の投稿や親メッセージは除外。
 * @returns {Promise<{messages:{ts:string,text:string}[]}>}
 */
async function pollOperator(sessionId) {
  const s = sessions.get(sessionId);
  if (!s) throw new Error('handoff session not found');
  const data = await slackGet('conversations.replies', {
    channel: s.channel,
    ts: s.threadTs,
    oldest: s.lastTs
  });
  const all = Array.isArray(data.messages) ? data.messages : [];
  const fresh = all
    .filter((m) => m && m.ts && Number(m.ts) > Number(s.lastTs))
    .filter((m) => !m.bot_id && m.subtype !== 'bot_message' && m.user) // 人間の返信のみ
    .map((m) => ({ ts: m.ts, text: String(m.text || '') }));
  if (fresh.length) {
    s.lastTs = fresh[fresh.length - 1].ts;
  }
  return { messages: fresh };
}

/** 後日連絡の希望を Slack へ通知する（営業時間外/不在時の受け皿）。 */
async function requestCallback({ name, contact, message } = {}) {
  const c = config();
  const text =
    `📋 *後日連絡のご希望*\n` +
    `• お名前: ${String(name || '(未入力)').slice(0, 100)}\n` +
    `• ご連絡先: ${String(contact || '(未入力)').slice(0, 100)}\n` +
    `• ご相談内容: ${String(message || '(未入力)').slice(0, 1000)}`;
  await slackPost('chat.postMessage', { channel: c.channel, text });
  return { ok: true };
}

/** セッション終了（破棄）。 */
function endHandoff(sessionId) {
  sessions.delete(sessionId);
  return { ok: true };
}

/* ---------- 予約（来店・査定・電話相談） ---------- */

function bookingsDir() {
  return process.env.BOOKINGS_DIR || path.join(__dirname, '..', 'data', 'bookings');
}

function bookingStamp(d) {
  const x = d instanceof Date ? d : new Date();
  const p = (n) => String(n).padStart(2, '0');
  return `${x.getUTCFullYear()}-${p(x.getUTCMonth() + 1)}-${p(x.getUTCDate())}`;
}

/** 予約の控えをJSONLで保存（Slack未設定でもリードを失わないため）。失敗は握りつぶす。 */
function saveBooking(rec) {
  try {
    const dir = bookingsDir();
    fs.mkdirSync(dir, { recursive: true });
    fs.appendFileSync(path.join(dir, `${bookingStamp()}.jsonl`), JSON.stringify(rec) + '\n');
  } catch (_e) {
    /* 保存失敗は受付自体を止めない */
  }
}

/**
 * 来店/査定/電話相談の予約リクエストを受け付ける。
 * 営業時間に関係なく受付可能。控えを保存し、Slack設定時は通知する。
 */
async function requestBooking(p = {}) {
  const s = (v) => String(v || '').slice(0, 200).trim();
  const rec = {
    ts: new Date().toISOString(),
    type: s(p.type) || '来店予約',
    date: s(p.date),
    time: s(p.time),
    name: s(p.name),
    contact: s(p.contact),
    note: String(p.note || '').slice(0, 1000)
  };
  saveBooking(rec);

  if (isEnabled()) {
    const text =
      `📅 *予約リクエスト*\n` +
      `• 種別: ${rec.type}\n` +
      `• 希望日: ${rec.date || '(未入力)'}\n` +
      `• 希望時間: ${rec.time || '(未入力)'}\n` +
      `• お名前: ${rec.name || '(未入力)'}\n` +
      `• ご連絡先: ${rec.contact || '(未入力)'}\n` +
      `• メモ: ${rec.note || '(なし)'}`;
    await slackPost('chat.postMessage', { channel: config().channel, text });
  }
  return { ok: true };
}

module.exports = {
  config,
  isEnabled,
  isWithinBusinessHours,
  startHandoff,
  sendUserMessage,
  pollOperator,
  requestCallback,
  requestBooking,
  endHandoff,
  _sessions: sessions // テスト用
};
