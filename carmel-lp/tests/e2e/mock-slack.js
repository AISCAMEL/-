/**
 * tests/e2e/mock-slack.js
 * ------------------------------------------------------------------
 * Slack Web API の最小モック。実トークン無しで有人対応フローを検証する。
 * 対応: chat.postMessage / conversations.replies
 *
 * 起動: node tests/e2e/mock-slack.js  (既定 PORT=4600)
 * スレッドはメモリ上に保持。?inject= で「担当者返信」を差し込めるよう
 * /__inject エンドポイントも用意（テストから操作）。
 */
'use strict';
const http = require('http');

const PORT = process.env.MOCK_SLACK_PORT || 4600;

// threadTs -> [{ts,text,user?,bot_id?}]
const threads = new Map();
let lastThreadTs = null;
let lastPostedText = '';
let seq = 1000;
const nextTs = () => String((seq++) + 0.0001).padStart(4, '0');

function readBody(req) {
  return new Promise((resolve) => {
    let raw = '';
    req.on('data', (c) => (raw += c));
    req.on('end', () => {
      try {
        resolve(JSON.parse(raw || '{}'));
      } catch {
        resolve({});
      }
    });
  });
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://localhost');
  const send = (obj) => {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(obj));
  };

  // テスト用: 担当者の返信を（最新の）スレッドへ差し込む
  if (url.pathname === '/__inject') {
    const body = await readBody(req);
    const threadTs = body.thread_ts || lastThreadTs;
    const arr = threads.get(threadTs) || [];
    arr.push({ ts: nextTs(), text: body.text, user: 'U_OPERATOR' });
    threads.set(threadTs, arr);
    return send({ ok: true });
  }

  // テスト用: 直近のボット投稿テキストを覗く
  if (url.pathname === '/__last') {
    return send({ ok: true, text: lastPostedText });
  }

  if (url.pathname === '/chat.postMessage') {
    const body = await readBody(req);
    const ts = nextTs();
    lastPostedText = body.text || '';
    // ボット投稿には bot_id を付ける（人間の返信と区別するため）
    const msg = { ts, text: body.text, bot_id: 'B_CARMEL' };
    const threadTs = body.thread_ts || ts;
    if (!body.thread_ts) lastThreadTs = threadTs; // 親メッセージ=新規スレッド
    const arr = threads.get(threadTs) || [];
    arr.push(msg);
    threads.set(threadTs, arr);
    return send({ ok: true, ts, channel: body.channel });
  }

  if (url.pathname === '/conversations.replies') {
    const ts = url.searchParams.get('ts');
    const oldest = Number(url.searchParams.get('oldest') || 0);
    const arr = (threads.get(ts) || []).filter((m) => Number(m.ts) >= oldest);
    return send({ ok: true, messages: arr });
  }

  send({ ok: false, error: 'unknown_method' });
});

server.listen(PORT, () => {
  // eslint-disable-next-line no-console
  console.log(`mock-slack on http://localhost:${PORT}`);
});
