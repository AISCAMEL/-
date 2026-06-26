/**
 * server.js  (CommonJS / 依存ゼロのローカル開発サーバー)
 * ------------------------------------------------------------------
 * 静的ファイル配信 + /api/chat の OpenRouter プロキシ。
 * Node 18+ で `node server.js` だけで起動できる（global fetch を使用）。
 *
 * 起動:
 *   OPENROUTER_API_KEY=xxxx node server.js
 *   (PORT で待受ポート変更可。既定 3000)
 *
 * 本番(Vercel等)では api/chat.js が同じ役割を担う。
 */

'use strict';

const http = require('http');
const fs = require('fs');
const path = require('path');
const { streamChat } = require('./lib/carmel-bot');
const { appendLog } = require('./lib/chat-log');
const handoff = require('./lib/handoff');
const admin = require('./lib/admin');

const PORT = process.env.PORT || 3000;
const ROOT = __dirname;

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.ico': 'image/x-icon'
};

function sendSseError(res, message) {
  res.write(`data: ${JSON.stringify({ error: message })}\n\n`);
  res.end();
}

async function handleChat(req, res) {
  let raw = '';
  req.on('data', (c) => {
    raw += c;
    if (raw.length > 1e6) req.destroy(); // 簡易な過大リクエスト防止
  });
  req.on('end', async () => {
    res.writeHead(200, {
      'Content-Type': 'text/event-stream; charset=utf-8',
      'Cache-Control': 'no-cache',
      Connection: 'keep-alive'
    });
    try {
      const { messages } = JSON.parse(raw || '{}');
      let answer = '';
      const { model } = await streamChat(
        messages,
        (delta) => {
          answer += delta;
          res.write(`data: ${JSON.stringify({ delta })}\n\n`);
        },
        { referer: req.headers.referer }
      );
      res.write('data: [DONE]\n\n');
      res.end();
      appendLog({ messages, answer, model }); // CHAT_LOG=1 のとき記録
    } catch (err) {
      sendSseError(res, err && err.message ? err.message : 'chat failed');
    }
  });
}

function readBody(req) {
  return new Promise((resolve) => {
    let raw = '';
    req.on('data', (c) => {
      raw += c;
      if (raw.length > 1e6) req.destroy();
    });
    req.on('end', () => {
      try {
        resolve(JSON.parse(raw || '{}'));
      } catch (_e) {
        resolve({});
      }
    });
    req.on('error', () => resolve({}));
  });
}

function sendJson(res, code, obj) {
  res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8' });
  res.end(JSON.stringify(obj));
}

/**
 * 有人ハイブリッド対応(Slack連携)のエンドポイント群。
 *   GET  /api/handoff/status            -> { enabled, withinHours, timeoutMs }
 *   POST /api/handoff/start  {question} -> { sessionId, timeoutMs } | { available:false, reason }
 *   POST /api/handoff/send   {sessionId,text}
 *   GET  /api/handoff/poll?sessionId=   -> { messages:[{ts,text}] }
 *   POST /api/handoff/callback {name,contact,message}
 *   POST /api/handoff/end    {sessionId}
 */
async function handleHandoff(req, res) {
  const url = new URL(req.url, 'http://localhost');
  const op = url.pathname.replace('/api/handoff/', '').replace('/api/handoff', '');

  try {
    if (op === 'status') {
      return sendJson(res, 200, {
        enabled: handoff.isEnabled(),
        withinHours: handoff.isWithinBusinessHours(),
        timeoutMs: handoff.config().timeoutMs
      });
    }

    if (op === 'poll') {
      const sessionId = url.searchParams.get('sessionId');
      const out = await handoff.pollOperator(sessionId);
      return sendJson(res, 200, out);
    }

    // 以降は POST
    const body = await readBody(req);

    if (op === 'start') {
      if (!handoff.isEnabled() || !handoff.isWithinBusinessHours()) {
        return sendJson(res, 200, {
          available: false,
          reason: !handoff.isEnabled() ? 'disabled' : 'off-hours'
        });
      }
      const out = await handoff.startHandoff(body.question);
      return sendJson(res, 200, { available: true, ...out });
    }

    if (op === 'send') {
      const out = await handoff.sendUserMessage(body.sessionId, body.text);
      return sendJson(res, 200, out);
    }

    if (op === 'callback') {
      const out = await handoff.requestCallback(body);
      return sendJson(res, 200, out);
    }

    if (op === 'reserve') {
      // 予約は営業時間・Slack有無に関わらず受付（控えを保存し、設定時は通知）
      const out = await handoff.requestBooking(body);
      return sendJson(res, 200, out);
    }

    if (op === 'end') {
      return sendJson(res, 200, handoff.endHandoff(body.sessionId));
    }

    return sendJson(res, 404, { error: 'unknown handoff op' });
  } catch (err) {
    return sendJson(res, 500, { error: (err && err.message) || 'handoff failed' });
  }
}

/**
 * 管理画面（任意・Basic認証）。ADMIN_USER/ADMIN_PASS 未設定なら 404（存在しない扱い）。
 *   GET /admin              -> ダッシュボードHTML
 *   GET /api/admin/data     -> 集計JSON
 *   GET /api/admin/ics?id=  -> 予約のカレンダーファイル(.ics)
 */
function handleAdmin(req, res) {
  if (!admin.isEnabled()) {
    res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end('Not Found');
    return;
  }
  if (!admin.checkAuth(req)) {
    res.writeHead(401, {
      'WWW-Authenticate': 'Basic realm="Carmel Admin", charset="UTF-8"',
      'Content-Type': 'text/plain; charset=utf-8'
    });
    res.end('Authentication required');
    return;
  }

  const url = new URL(req.url, 'http://localhost');

  if (url.pathname === '/api/admin/data') {
    return sendJson(res, 200, admin.collectData());
  }

  if (url.pathname === '/api/admin/ics') {
    const ics = admin.icsById(url.searchParams.get('id'));
    if (!ics) {
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('booking not found');
      return;
    }
    res.writeHead(200, {
      'Content-Type': 'text/calendar; charset=utf-8',
      'Content-Disposition': 'attachment; filename="carmel-booking.ics"'
    });
    res.end(ics);
    return;
  }

  // それ以外（/admin など）はダッシュボード
  res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
  res.end(admin.adminHtml());
}

function serveStatic(req, res) {
  let urlPath = decodeURIComponent(req.url.split('?')[0]);
  if (urlPath === '/') urlPath = '/index.html';

  // ディレクトリトラバーサル防止
  const filePath = path.normalize(path.join(ROOT, urlPath));
  if (!filePath.startsWith(ROOT)) {
    res.writeHead(403).end('Forbidden');
    return;
  }

  fs.readFile(filePath, (err, data) => {
    if (err) {
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('Not Found');
      return;
    }
    const ext = path.extname(filePath).toLowerCase();
    res.writeHead(200, { 'Content-Type': MIME[ext] || 'application/octet-stream' });
    res.end(data);
  });
}

/**
 * CORS。WordPress等の別ドメインからウィジェットを埋め込めるようにする。
 * ALLOWED_ORIGINS（カンマ区切り）を設定すると許可元を限定。未設定なら全許可(*)。
 * 認証情報(Cookie)は使わないステートレスAPIのため * でも安全。
 */
function applyCors(req, res) {
  const allow = process.env.ALLOWED_ORIGINS;
  const origin = req.headers.origin;
  if (allow) {
    const list = allow.split(',').map((s) => s.trim()).filter(Boolean);
    if (origin && list.includes(origin)) {
      res.setHeader('Access-Control-Allow-Origin', origin);
    } else if (list.length) {
      res.setHeader('Access-Control-Allow-Origin', list[0]);
    }
    res.setHeader('Vary', 'Origin');
  } else {
    res.setHeader('Access-Control-Allow-Origin', '*');
  }
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
}

const server = http.createServer((req, res) => {
  applyCors(req, res);
  if (req.method === 'OPTIONS') {
    res.writeHead(204);
    res.end();
    return;
  }
  if (req.method === 'POST' && req.url.startsWith('/api/chat')) {
    handleChat(req, res);
    return;
  }
  if (req.url.startsWith('/api/handoff')) {
    handleHandoff(req, res);
    return;
  }
  if (req.url === '/admin' || req.url.startsWith('/admin') || req.url.startsWith('/api/admin')) {
    handleAdmin(req, res);
    return;
  }
  serveStatic(req, res);
});

server.listen(PORT, () => {
  // eslint-disable-next-line no-console
  console.log(`Carmel LP running:  http://localhost:${PORT}`);
  if (!process.env.OPENROUTER_API_KEY) {
    // eslint-disable-next-line no-console
    console.warn('[warn] OPENROUTER_API_KEY 未設定。AI応答は失敗し、LINE/電話導線にフォールバックします。');
  }
});
