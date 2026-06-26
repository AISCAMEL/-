/**
 * lib/admin.js  (CommonJS / サーバー共有)
 * ------------------------------------------------------------------
 * 運用者向けの簡易管理画面。会話ログ・予約・よくある質問を閲覧する。
 * 各予約はカレンダー追加ファイル(.ics)としてダウンロードできる（OAuth不要）。
 *
 * セキュリティ:
 *   - ADMIN_USER / ADMIN_PASS を設定した時のみ有効（Basic認証）。
 *     未設定なら管理画面は存在しない扱い（情報を露出しない）。
 *   - 本番では必ずHTTPS下で利用すること（Basic認証は平文のため）。
 *
 * データ元:
 *   - 予約:   BOOKINGS_DIR（既定 data/bookings）
 *   - 会話ログ: CHAT_LOG_DIR（既定 data/chat-logs）
 */

'use strict';

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

function bookingsDir() {
  return process.env.BOOKINGS_DIR || path.join(__dirname, '..', 'data', 'bookings');
}
function chatLogDir() {
  return process.env.CHAT_LOG_DIR || path.join(__dirname, '..', 'data', 'chat-logs');
}

/** 管理画面が有効か（ユーザー/パスワード両方が設定済み）。 */
function isEnabled() {
  return Boolean(process.env.ADMIN_USER && process.env.ADMIN_PASS);
}

function safeEqual(a, b) {
  const ba = Buffer.from(String(a));
  const bb = Buffer.from(String(b));
  if (ba.length !== bb.length) return false;
  return crypto.timingSafeEqual(ba, bb);
}

/** Basic認証の検証。 */
function checkAuth(req) {
  if (!isEnabled()) return false;
  const h = (req.headers && req.headers.authorization) || '';
  const m = /^Basic\s+(.+)$/i.exec(h);
  if (!m) return false;
  let decoded = '';
  try {
    decoded = Buffer.from(m[1], 'base64').toString('utf8');
  } catch (_e) {
    return false;
  }
  const i = decoded.indexOf(':');
  if (i === -1) return false;
  const user = decoded.slice(0, i);
  const pass = decoded.slice(i + 1);
  return safeEqual(user, process.env.ADMIN_USER) && safeEqual(pass, process.env.ADMIN_PASS);
}

/** ディレクトリ内の *.jsonl を新しい順に読み、最大 limit 件返す。 */
function readJsonl(dir, limit) {
  let files;
  try {
    files = fs.readdirSync(dir).filter((f) => f.endsWith('.jsonl'));
  } catch (_e) {
    return [];
  }
  files.sort().reverse(); // 日付ファイル名の新しい順
  const out = [];
  for (const f of files) {
    let lines = [];
    try {
      lines = fs.readFileSync(path.join(dir, f), 'utf8').split(/\r?\n/).filter(Boolean);
    } catch (_e) {
      continue;
    }
    for (let i = lines.length - 1; i >= 0; i--) {
      try {
        out.push(JSON.parse(lines[i]));
      } catch (_e) {
        /* skip broken line */
      }
      if (out.length >= limit) return out;
    }
  }
  return out;
}

/** よくある質問の集計（会話ログの question を正規化して件数順）。 */
function topQuestions(logs, limit = 10) {
  const counts = new Map();
  for (const l of logs) {
    const q = String((l && l.question) || '').trim();
    if (!q) continue;
    counts.set(q, (counts.get(q) || 0) + 1);
  }
  return [...counts.entries()]
    .map(([question, count]) => ({ question, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, limit);
}

/** 管理画面に表示する集計データ。 */
function collectData() {
  const bookings = readJsonl(bookingsDir(), 200);
  const logs = readJsonl(chatLogDir(), 300);
  return {
    counts: { bookings: bookings.length, logs: logs.length },
    bookings,
    logs: logs.slice(0, 100),
    topQuestions: topQuestions(logs)
  };
}

/* ---------- ICS（カレンダー追加ファイル）生成 ---------- */

// 時間帯ラベル → 開始/終了(時)。先頭一致で判定。
const TIME_BANDS = [
  { key: '午前', start: 10, end: 12 },
  { key: '午後', start: 12, end: 15 },
  { key: '夕方', start: 15, end: 18 },
  { key: '夜', start: 18, end: 19 }
];

function bandHours(timeLabel) {
  const t = String(timeLabel || '');
  const hit = TIME_BANDS.find((b) => t.startsWith(b.key));
  return hit ? [hit.start, hit.end] : [10, 11];
}

function pad(n) {
  return String(n).padStart(2, '0');
}

function icsEscape(s) {
  return String(s || '').replace(/([,;\\])/g, '\\$1').replace(/\r?\n/g, '\\n');
}

/** 予約レコードからICS文字列を生成（フローティング＝端末ローカル時刻で表示）。 */
function bookingToIcs(b, now) {
  const date = String((b && b.date) || '').replace(/-/g, '');
  const [sh, eh] = bandHours(b && b.time);
  const dtStart = `${date}T${pad(sh)}0000`;
  const dtEnd = `${date}T${pad(eh)}0000`;
  const stampSrc = now instanceof Date ? now : new Date();
  const stamp =
    `${stampSrc.getUTCFullYear()}${pad(stampSrc.getUTCMonth() + 1)}${pad(stampSrc.getUTCDate())}` +
    `T${pad(stampSrc.getUTCHours())}${pad(stampSrc.getUTCMinutes())}${pad(stampSrc.getUTCSeconds())}Z`;
  const uid = `${(b && b.id) || 'booking'}@carmel`;
  const title = `カーメル ${b && b.type ? b.type : '予約'}: ${b && b.name ? b.name + '様' : 'お客様'}`;
  const desc = [
    `種別: ${b && b.type}`,
    `ご連絡先: ${b && b.contact}`,
    b && b.note ? `メモ: ${b.note}` : ''
  ]
    .filter(Boolean)
    .join('\n');

  return [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Carmel LP//Booking//JA',
    'CALSCALE:GREGORIAN',
    'BEGIN:VEVENT',
    `UID:${uid}`,
    `DTSTAMP:${stamp}`,
    `DTSTART:${dtStart}`,
    `DTEND:${dtEnd}`,
    `SUMMARY:${icsEscape(title)}`,
    `DESCRIPTION:${icsEscape(desc)}`,
    'END:VEVENT',
    'END:VCALENDAR'
  ].join('\r\n');
}

/** id から予約を1件探してICSを返す（無ければ null）。 */
function icsById(id) {
  const bookings = readJsonl(bookingsDir(), 1000);
  const b = bookings.find((x) => x && x.id === id);
  return b ? bookingToIcs(b) : null;
}

/* ---------- ダッシュボードHTML ---------- */

function adminHtml() {
  // データは /api/admin/data から取得（Basic認証はブラウザが自動付与）。
  return `<!doctype html>
<html lang="ja"><head><meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>カーメル 管理画面</title>
<style>
  :root{--navy:#1a3a6b;--line:#06c755;--muted:#6b7280;--border:#e5e7eb}
  *{box-sizing:border-box}
  body{font-family:system-ui,'Hiragino Sans','Noto Sans JP',sans-serif;margin:0;background:#f6f7f9;color:#1f2937}
  header{background:var(--navy);color:#fff;padding:14px 20px;font-weight:700}
  main{max-width:980px;margin:0 auto;padding:20px}
  h2{font-size:1.05rem;margin:24px 0 10px;border-left:4px solid var(--navy);padding-left:8px}
  .cards{display:flex;gap:12px;flex-wrap:wrap}
  .card{background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px 18px;min-width:140px}
  .card b{font-size:1.6rem;color:var(--navy)}
  table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--border);border-radius:10px;overflow:hidden}
  th,td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--border);font-size:0.85rem;vertical-align:top}
  th{background:#f0f2f5;color:var(--muted);font-weight:700}
  tr:last-child td{border-bottom:none}
  .ics{display:inline-block;background:var(--navy);color:#fff;text-decoration:none;border-radius:6px;padding:4px 10px;font-size:0.78rem;white-space:nowrap}
  .muted{color:var(--muted)}
  .empty{color:var(--muted);padding:10px}
  .q{display:flex;justify-content:space-between;gap:10px;background:#fff;border:1px solid var(--border);border-radius:8px;padding:8px 12px;margin-bottom:6px;font-size:0.85rem}
  .q b{color:var(--navy)}
</style></head>
<body>
<header>カーメル 管理画面</header>
<main>
  <div class="cards">
    <div class="card"><div class="muted">予約（控え）</div><b id="c-book">–</b></div>
    <div class="card"><div class="muted">会話ログ</div><b id="c-log">–</b></div>
  </div>

  <h2>📅 予約一覧</h2>
  <div id="bookings"><p class="empty">読み込み中…</p></div>

  <h2>❓ よくある質問（会話ログ集計）</h2>
  <div id="faq"><p class="empty">読み込み中…</p></div>

  <h2>💬 最近の会話ログ</h2>
  <div id="logs"><p class="empty">読み込み中…</p></div>
</main>
<script>
  const esc = (s)=>String(s==null?'':s).replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
  fetch('/api/admin/data',{headers:{'Accept':'application/json'}})
    .then(r=>r.json()).then(d=>{
      document.getElementById('c-book').textContent=d.counts.bookings;
      document.getElementById('c-log').textContent=d.counts.logs;

      // 予約
      const bw=document.getElementById('bookings');
      if(!d.bookings.length){bw.innerHTML='<p class="empty">予約はまだありません。</p>';}
      else{
        let h='<table><tr><th>受付</th><th>種別</th><th>希望日</th><th>時間帯</th><th>お名前</th><th>連絡先</th><th>メモ</th><th></th></tr>';
        d.bookings.forEach(b=>{
          const ics = b.id ? '<a class="ics" href="/api/admin/ics?id='+encodeURIComponent(b.id)+'">＋カレンダー</a>' : '';
          h+='<tr><td class="muted">'+esc((b.ts||'').slice(0,16).replace('T',' '))+'</td><td>'+esc(b.type)+'</td><td>'+esc(b.date)+'</td><td>'+esc(b.time)+'</td><td>'+esc(b.name)+'</td><td>'+esc(b.contact)+'</td><td>'+esc(b.note)+'</td><td>'+ics+'</td></tr>';
        });
        bw.innerHTML=h+'</table>';
      }

      // FAQ
      const fw=document.getElementById('faq');
      if(!d.topQuestions.length){fw.innerHTML='<p class="empty">会話ログがまだありません（CHAT_LOG=1 で記録されます）。</p>';}
      else{fw.innerHTML=d.topQuestions.map(q=>'<div class="q"><span>'+esc(q.question)+'</span><b>'+q.count+'件</b></div>').join('');}

      // ログ
      const lw=document.getElementById('logs');
      if(!d.logs.length){lw.innerHTML='<p class="empty">会話ログがまだありません。</p>';}
      else{
        let h='<table><tr><th>時刻</th><th>質問</th><th>AI回答</th></tr>';
        d.logs.forEach(l=>{h+='<tr><td class="muted">'+esc((l.ts||'').slice(0,16).replace('T',' '))+'</td><td>'+esc(l.question)+'</td><td>'+esc((l.answer||'').slice(0,120))+'</td></tr>';});
        lw.innerHTML=h+'</table>';
      }
    }).catch(()=>{document.body.insertAdjacentHTML('beforeend','<p class="empty" style="padding:20px">データ取得に失敗しました。</p>');});
</script>
</body></html>`;
}

module.exports = {
  isEnabled,
  checkAuth,
  collectData,
  adminHtml,
  bookingToIcs,
  icsById,
  bandHours,
  readJsonl,
  topQuestions
};
