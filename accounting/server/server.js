/* =========================================================================
 * server.js ― クラウド会計 同期サーバー（依存ゼロ / Node 標準モジュールのみ）
 *
 * 複数人・複数端末で同じ帳簿を共有するための最小サーバー。
 * データは「ワークスペース」単位のJSONスナップショットとして保存する。
 * 楽観ロック（version）で、他の人の更新を上書きしないようにする。
 *
 * 起動:  node server/server.js           （既定 http://localhost:8787）
 *        PORT=9000 node server/server.js
 * データ: server/data/<workspace>.json に保存
 * ========================================================================= */
'use strict';
const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PORT = process.env.PORT || 8787;
const DATA_DIR = path.join(__dirname, 'data');
if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });

const wsPath = (ws) => path.join(DATA_DIR, ws.replace(/[^\w\-]/g, '_') + '.json');
const readWs = (ws) => { try { return JSON.parse(fs.readFileSync(wsPath(ws), 'utf8')); } catch (e) { return null; } };
const writeWs = (ws, obj) => fs.writeFileSync(wsPath(ws), JSON.stringify(obj));

const send = (res, code, obj) => {
  const body = JSON.stringify(obj);
  res.writeHead(code, {
    'Content-Type': 'application/json; charset=utf-8',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Access-Control-Allow-Methods': 'GET,POST,OPTIONS',
  });
  res.end(body);
};
const readBody = (req) => new Promise((resolve) => {
  let d = ''; req.on('data', (c) => { d += c; if (d.length > 20e6) req.destroy(); });
  req.on('end', () => { try { resolve(d ? JSON.parse(d) : {}); } catch (e) { resolve(null); } });
});

const server = http.createServer(async (req, res) => {
  if (req.method === 'OPTIONS') return send(res, 204, {});
  const url = req.url.split('?')[0];

  if (url === '/api/health') return send(res, 200, { ok: true, app: 'kaikei-sync', time: Date.now() });

  if (url === '/api/pull' && req.method === 'POST') {
    const body = await readBody(req);
    if (!body || !body.workspace) return send(res, 400, { error: 'workspace は必須です' });
    const rec = readWs(body.workspace);
    if (!rec) return send(res, 404, { error: 'ワークスペースが存在しません', version: 0 });
    if (rec.token && rec.token !== body.token) return send(res, 401, { error: 'トークンが一致しません' });
    return send(res, 200, { version: rec.version, data: rec.data, updatedAt: rec.updatedAt });
  }

  if (url === '/api/push' && req.method === 'POST') {
    const body = await readBody(req);
    if (!body || !body.workspace) return send(res, 400, { error: 'workspace は必須です' });
    const rec = readWs(body.workspace);
    // 新規ワークスペース: 送られたトークンを登録
    if (!rec) {
      const token = body.token || crypto.randomBytes(12).toString('hex');
      writeWs(body.workspace, { token, version: 1, data: body.data || {}, updatedAt: Date.now() });
      return send(res, 200, { version: 1, token, created: true });
    }
    if (rec.token && rec.token !== body.token) return send(res, 401, { error: 'トークンが一致しません' });
    // 楽観ロック: 送信元が見ていた版が最新でなければ衝突
    if (body.baseVersion != null && body.baseVersion !== rec.version) {
      return send(res, 409, { error: '他の端末で更新されています。先に取り込み（pull）してください。', version: rec.version, data: rec.data });
    }
    const next = { token: rec.token, version: rec.version + 1, data: body.data || {}, updatedAt: Date.now() };
    writeWs(body.workspace, next);
    return send(res, 200, { version: next.version });
  }

  /* ---- 取込Webhook（外部システム→会計アプリの受信キュー） ------------
   * 外部（GAS・スクリプト・決済/カードのwebhook等）が取引データをPOSTし、
   * 会計アプリ側が pull で取り込む。items は {date, description, amount, dir?,
   * account?, tax?} の配列。dir 省略時は amount の符号で判定。
   * ------------------------------------------------------------------- */
  if (url === '/api/inbox' && req.method === 'POST') {
    const body = await readBody(req);
    if (!body || !body.workspace) return send(res, 400, { error: 'workspace は必須です' });
    const items = Array.isArray(body.items) ? body.items : (body.item ? [body.item] : []);
    if (!items.length) return send(res, 400, { error: 'items がありません' });
    let rec = readWs(body.workspace);
    if (!rec) {
      const token = body.token || crypto.randomBytes(12).toString('hex');
      rec = { token, version: 0, data: {}, inbox: [], updatedAt: Date.now() };
    }
    if (rec.token && rec.token !== body.token) return send(res, 401, { error: 'トークンが一致しません' });
    rec.inbox = (rec.inbox || []).concat(items.map((it) => ({
      date: String(it.date || ''), description: String(it.description || it.desc || ''),
      amount: Number(it.amount) || 0, dir: it.dir || null,
      account: it.account || null, tax: it.tax || null, receivedAt: Date.now(),
    })));
    writeWs(body.workspace, rec);
    return send(res, 200, { queued: items.length, total: rec.inbox.length });
  }
  if (url === '/api/inbox/pull' && req.method === 'POST') {
    const body = await readBody(req);
    if (!body || !body.workspace) return send(res, 400, { error: 'workspace は必須です' });
    const rec = readWs(body.workspace);
    if (!rec) return send(res, 200, { items: [] });
    if (rec.token && rec.token !== body.token) return send(res, 401, { error: 'トークンが一致しません' });
    const items = rec.inbox || [];
    rec.inbox = [];
    writeWs(body.workspace, rec);
    return send(res, 200, { items });
  }

  send(res, 404, { error: 'not found' });
});

server.listen(PORT, () => {
  console.log(`クラウド会計 同期サーバー起動: http://localhost:${PORT}`);
  console.log(`データ保存先: ${DATA_DIR}`);
});
