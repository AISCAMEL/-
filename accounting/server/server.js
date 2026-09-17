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
const https = require('https');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { URL } = require('url');

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

  /* ---- AI会計相談プロキシ（APIキーはサーバー側で保持） ------------------
   * body {workspace, token, question, context}。環境変数で提供者を設定：
   *   AI_API_KEY（必須）, AI_PROVIDER=anthropic|openai, AI_MODEL, AI_API_BASE
   * ------------------------------------------------------------------- */
  if (url === '/api/ai' && req.method === 'POST') {
    const body = await readBody(req);
    if (!body || !body.workspace) return send(res, 400, { error: 'workspace は必須です' });
    const rec = readWs(body.workspace);
    if (rec && rec.token && rec.token !== body.token) return send(res, 401, { error: 'トークンが一致しません' });
    const key = process.env.AI_API_KEY;
    if (!key) return send(res, 501, { error: 'AI連携が未設定です（サーバーに AI_API_KEY を設定してください）' });
    try {
      const answer = await askAI(body.question || '', body.context || {});
      return send(res, 200, { answer });
    } catch (e) { return send(res, 502, { error: 'AI応答エラー: ' + e.message }); }
  }

  send(res, 404, { error: 'not found' });
});

/* ---- LLM 呼び出し（Anthropic / OpenAI） -------------------------------- */
const httpsJson = (urlStr, headers, payload) => new Promise((resolve, reject) => {
  const u = new URL(urlStr);
  const lib = u.protocol === 'http:' ? http : https;
  const data = JSON.stringify(payload);
  const req = lib.request(u, { method: 'POST', headers: Object.assign({ 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data) }, headers) }, (res) => {
    let d = ''; res.on('data', (c) => (d += c));
    res.on('end', () => { let b = {}; try { b = JSON.parse(d); } catch (e) {} (res.statusCode >= 200 && res.statusCode < 300) ? resolve(b) : reject(new Error((b.error && (b.error.message || b.error)) || ('HTTP ' + res.statusCode))); });
  });
  req.on('error', reject); req.write(data); req.end();
});

const buildContext = (ctx) => {
  const m = (ctx && ctx.metrics) || {};
  const y = (n) => '¥' + Math.round(Number(n) || 0).toLocaleString('ja-JP');
  const lines = [
    `売上: ${y(m.revenue)} / 費用: ${y(m.expense)} / 当期純利益: ${y(m.net)}`,
    `現預金: ${y(m.cash)} / 売掛金: ${y(m.receivable)} / 買掛金: ${y(m.payable)}`,
    `課税売上(税抜): ${y(m.taxableSalesNet)}`,
  ];
  if (ctx && ctx.alerts && ctx.alerts.length) lines.push('検出事項: ' + ctx.alerts.map((a) => a.text).join(' / '));
  return lines.join('\n');
};

async function askAI(question, ctx) {
  const provider = (process.env.AI_PROVIDER || 'anthropic').toLowerCase();
  const key = process.env.AI_API_KEY;
  const sys = 'あなたは日本の会計・税務に詳しいアシスタントです。提供される会社の会計データ（要約）を踏まえ、簡潔で実務的な助言を日本語で行ってください。断定は避け、税額や申告に関わる重要事項には必ず「最終的な判断は税理士等の専門家にご確認ください」と添えてください。';
  const userMsg = `【当社の会計データ要約】\n${buildContext(ctx)}\n\n【質問】\n${question}`;

  if (provider === 'openai') {
    const base = process.env.AI_API_BASE || 'https://api.openai.com';
    const r = await httpsJson(`${base}/v1/chat/completions`, { Authorization: `Bearer ${key}` }, {
      model: process.env.AI_MODEL || 'gpt-4o-mini', max_tokens: 700,
      messages: [{ role: 'system', content: sys }, { role: 'user', content: userMsg }],
    });
    return (r.choices && r.choices[0] && r.choices[0].message && r.choices[0].message.content) || '(応答が空です)';
  }
  // Anthropic（既定）
  const base = process.env.AI_API_BASE || 'https://api.anthropic.com';
  const r = await httpsJson(`${base}/v1/messages`, { 'x-api-key': key, 'anthropic-version': '2023-06-01' }, {
    model: process.env.AI_MODEL || 'claude-3-5-haiku-latest', max_tokens: 700,
    system: sys, messages: [{ role: 'user', content: userMsg }],
  });
  return (r.content && r.content[0] && r.content[0].text) || '(応答が空です)';
}

server.listen(PORT, () => {
  console.log(`クラウド会計 同期サーバー起動: http://localhost:${PORT}`);
  console.log(`データ保存先: ${DATA_DIR}`);
});
