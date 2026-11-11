/* =========================================================================
 * server/scheduler.js ― ワンコマンド自動運用（依存ゼロ）
 *
 * `node server/scheduler.js` だけで、同期サーバーの起動と、全コネクタ
 * （Gmail取込・在庫アラート・月次レポート・AI監視・決済）の定期実行を
 * まとめて行う。設定は server/.env から読み込む。
 *
 *   node server/scheduler.js          … 常駐して定期実行
 *   node server/scheduler.js --once   … 有効なコネクタを一度だけ実行して終了
 *
 * 必要な値が未設定のコネクタは自動でスキップ（部分的な設定でも安全に動く）。
 * ========================================================================= */
'use strict';
const http = require('http');
const path = require('path');
const fs = require('fs');
const { spawn } = require('child_process');

const DIR = __dirname;
const log = (msg) => console.log(`[${new Date().toISOString().replace('T', ' ').slice(0, 19)}] ${msg}`);

/* ---- .env 読み込み（簡易・既存の環境変数を優先しない＝.envが上書き） ---- */
(function loadEnv() {
  const f = path.join(DIR, '.env');
  if (!fs.existsSync(f)) return;
  fs.readFileSync(f, 'utf8').split(/\r?\n/).forEach((line) => {
    const m = line.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\s*$/);
    if (!m || line.trim().startsWith('#')) return;
    let v = m[2];
    if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) v = v.slice(1, -1);
    process.env[m[1]] = v;
  });
})();

const E = process.env;
const num = (v, d) => (v != null && v !== '' && !isNaN(Number(v)) ? Number(v) : d);
const have = (k) => !!(E[k] && String(E[k]).trim());
const syncReady = have('WORKSPACE');
const gmailReady = have('GMAIL_CLIENT_ID') && have('GMAIL_CLIENT_SECRET') && have('GMAIL_REFRESH_TOKEN');

const CFG = {
  port: num(E.PORT, 8787),
  startServer: E.SCHED_NO_SERVER !== '1',
  gmailMin: num(E.SCHED_GMAIL_MIN, 30),
  payMin: num(E.SCHED_PAY_MIN, 60),
  stockAt: E.SCHED_STOCK_HHMM || '08:00',
  aiwatchAt: E.SCHED_AIWATCH_HHMM || '08:30',
  monthlyDom: num(E.SCHED_MONTHLY_DOM, 1),
  monthlyAt: E.SCHED_MONTHLY_HHMM || '08:00',
};

/* ---- コネクタ定義（必要な値が揃っているものだけ有効） ---------------- */
const C = (f) => path.join(DIR, 'connectors', f);
const TASKS = [
  { key: 'gmail', file: C('gmail.js'), enabled: gmailReady && syncReady, type: 'interval', min: CFG.gmailMin },
  { key: 'stock-alert', file: C('stock-alert.js'), enabled: gmailReady && syncReady, type: 'daily', at: CFG.stockAt },
  { key: 'ai-watch', file: C('ai-watch.js'), enabled: gmailReady && syncReady, type: 'daily', at: CFG.aiwatchAt },
  { key: 'monthly-report', file: C('monthly-report.js'), enabled: gmailReady && syncReady, type: 'monthly', dom: CFG.monthlyDom, at: CFG.monthlyAt },
  { key: 'stripe', file: C('stripe.js'), enabled: have('STRIPE_API_KEY') && syncReady, type: 'interval', min: CFG.payMin },
  { key: 'square', file: C('square.js'), enabled: have('SQUARE_ACCESS_TOKEN') && syncReady, type: 'interval', min: CFG.payMin },
  { key: 'paypal', file: C('paypal.js'), enabled: have('PAYPAL_CLIENT_ID') && have('PAYPAL_SECRET') && syncReady, type: 'interval', min: CFG.payMin },
];

const runConnector = (t) => new Promise((resolve) => {
  log(`▶ ${t.key} 実行`);
  const p = spawn(process.execPath, [t.file], { env: process.env });
  let out = '';
  p.stdout.on('data', (d) => (out += d));
  p.stderr.on('data', (d) => (out += d));
  p.on('close', (code) => {
    out.trim().split('\n').filter(Boolean).forEach((l) => log(`  ${t.key}: ${l}`));
    log(`✔ ${t.key} 終了 (code ${code})`);
    resolve(code);
  });
  p.on('error', (e) => { log(`✖ ${t.key} 起動失敗: ${e.message}`); resolve(1); });
});

/* ---- 同期サーバーを子プロセスで起動＋ヘルス待ち ---------------------- */
let serverProc = null;
const startServer = () => new Promise((resolve) => {
  if (!CFG.startServer) { log('同期サーバーは起動しません（SCHED_NO_SERVER=1）'); return resolve(); }
  log(`同期サーバーを起動（PORT=${CFG.port}）`);
  serverProc = spawn(process.execPath, [path.join(DIR, 'server.js')], { env: process.env });
  serverProc.stdout.on('data', (d) => process.stdout.write(`[server] ${d}`));
  serverProc.stderr.on('data', (d) => process.stderr.write(`[server] ${d}`));
  // ヘルス待ち（最大15秒）
  let tries = 0;
  const probe = () => {
    http.get({ host: 'localhost', port: CFG.port, path: '/api/health', timeout: 1500 }, (res) => { res.resume(); log('同期サーバー準備OK'); resolve(); })
      .on('error', () => { if (++tries > 30) { log('同期サーバーのヘルス確認に失敗（続行）'); return resolve(); } setTimeout(probe, 500); });
  };
  setTimeout(probe, 500);
});

/* ---- メイン ---------------------------------------------------------- */
async function once() {
  await startServer();
  const enabled = TASKS.filter((t) => t.enabled);
  log(`一括実行：有効なコネクタ ${enabled.length}件（${enabled.map((t) => t.key).join(', ') || 'なし'}）`);
  for (const t of enabled) await runConnector(t);
  if (serverProc) serverProc.kill();
  log('完了（--once）');
}

async function loop() {
  await startServer();
  const enabled = TASKS.filter((t) => t.enabled);
  const disabled = TASKS.filter((t) => !t.enabled).map((t) => t.key);
  log(`自動運用を開始。有効：${enabled.map((t) => t.key).join(', ') || 'なし'}`);
  if (disabled.length) log(`未設定でスキップ：${disabled.join(', ')}（server/.env に値を追加すると有効化）`);
  if (!enabled.length) log('※ 有効なコネクタがありません。server/.env に WORKSPACE と GMAIL_* などを設定してください。');

  const state = {}; // key -> {lastTs, lastDay}
  enabled.forEach((t) => (state[t.key] = { lastTs: 0, lastDay: '' }));
  // interval系は起動直後に一度実行
  for (const t of enabled) if (t.type === 'interval') { await runConnector(t); state[t.key].lastTs = Date.now(); }

  const tick = async () => {
    const now = new Date();
    const hhmm = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    const day = now.toISOString().slice(0, 10);
    for (const t of enabled) {
      const s = state[t.key];
      try {
        if (t.type === 'interval' && Date.now() - s.lastTs >= t.min * 60000) { s.lastTs = Date.now(); await runConnector(t); }
        else if (t.type === 'daily' && hhmm === t.at && s.lastDay !== day) { s.lastDay = day; await runConnector(t); }
        else if (t.type === 'monthly' && now.getDate() === t.dom && hhmm === t.at && s.lastDay !== day) { s.lastDay = day; await runConnector(t); }
      } catch (e) { log(`✖ ${t.key}: ${e.message}`); }
    }
  };
  setInterval(tick, 60000); // 毎分チェック
  log('スケジューラ稼働中（Ctrl+C で停止）。');
}

const bye = () => { if (serverProc) serverProc.kill(); process.exit(0); };
process.on('SIGINT', bye); process.on('SIGTERM', bye);

(process.argv.includes('--once') ? once() : loop()).catch((e) => { log('致命的エラー: ' + e.message); process.exit(1); });
