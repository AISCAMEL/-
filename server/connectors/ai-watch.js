/* =========================================================================
 * connectors/ai-watch.js ― AI監視アラートの自動メール送信（依存ゼロ）
 *
 * クラウド同期サーバーから最新スナップショットを取得し、会計データを走査して
 * 異常・リスク（貸借不一致／費用急増／資金／長期在庫／重複仕訳／未配賦過多／
 * 無効な登録番号／期限超過の未入金／減価償却未計上／バックアップ切れ／決算期接近）
 * を検知。しきい値以上の事項があればメールで通知する（開いていなくても督促）。
 * サーバーに AI 連携(AI_API_KEY)があれば一言講評も添える。
 *
 * 環境変数:
 *   SYNC_URL / WORKSPACE / TOKEN            … 会計アプリと同じ同期設定
 *   GMAIL_CLIENT_ID / _SECRET / _REFRESH_TOKEN … Gmail送信用 OAuth（gmail.send）
 *   ALERT_TO           … 通知先（省略時は会社設定email）
 *   WATCH_MIN_LEVEL    … 通知するしきい値 bad|warn|info（既定 warn）
 *   DRY_RUN=1          … 送信せず内容のみ表示
 * ========================================================================= */
'use strict';
const L = require('./_lib');
const OAUTH = process.env.GOOGLE_OAUTH_BASE || 'https://oauth2.googleapis.com';
const GSEND = process.env.GMAIL_SEND_BASE || 'https://gmail.googleapis.com';

const pad = (x) => String(x).padStart(2, '0');
const today = () => L.ymd(new Date());
const ymOf = (d) => (d || '').slice(0, 10).slice(0, 7);
const inRange = (d, a, b) => d >= a && d <= b;
const yen = (n) => '¥' + Math.round(Number(n) || 0).toLocaleString('ja-JP');
const b64url = (s) => Buffer.from(s).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const daysBetween = (a, b) => (a ? Math.round((new Date((b || today()) + 'T00:00:00') - new Date(a + 'T00:00:00')) / 86400000) : null);

const fiscalRange = (dateStr, startMonth) => {
  const d = new Date(dateStr + 'T00:00:00'); const y = d.getFullYear(), m = d.getMonth() + 1;
  const sy = m >= startMonth ? y : y - 1;
  const endD = new Date(sy + 1, startMonth - 1, 0);
  return { start: `${sy}-${pad(startMonth)}-01`, end: `${endD.getFullYear()}-${pad(endD.getMonth() + 1)}-${pad(endD.getDate())}` };
};
const checkReg = (raw) => {
  const s = String(raw || '').trim().toUpperCase().replace(/[\s-]/g, '');
  const m = s.match(/^T(\d{13})$/); if (!m) return false;
  const n = m[1], c = Number(n[0]), b = n.slice(1);
  let sum = 0; for (let i = 0; i < 12; i++) { const k = 12 - i; sum += Number(b[i]) * (k % 2 === 1 ? 1 : 2); }
  return (9 - (sum % 9)) === c;
};

// スナップショットから監視findingsを計算（ブラウザ版 reports.watchdog と同等）
function watchdog(stores, settings) {
  const journals = (stores.journals || []);
  const invoices = (stores.invoices || []);
  const vehicles = (stores.vehicles || []);
  const assets = (stores.assets || []);
  const partners = (stores.partners || []);
  const cat = {}; (stores.accounts || []).forEach((a) => (cat[a.code] = a.category));
  const fsMonth = settings.fiscalStartMonth || 4;
  const fy = fiscalRange(today(), fsMonth);
  const t = today();
  const out = [];
  const add = (level, title, detail) => out.push({ level, title, detail });

  // 集計（当期）
  let rev = 0, exp = 0, asset = 0, liab = 0, equity = 0;
  const closingExists = journals.some((j) => j.source === 'closing' && inRange(j.date, fy.start, fy.end));
  journals.forEach((j) => {
    (j.lines || []).forEach((l) => {
      const c = cat[l.account]; const s = l.side === 'debit' ? 1 : -1;
      if (c === 'asset') asset += s * l.amount;
      else if (c === 'liability') liab += -s * l.amount;
      else if (c === 'equity') equity += -s * l.amount;
    });
    if (j.source === 'closing' || !inRange(j.date, fy.start, fy.end)) return;
    (j.lines || []).forEach((l) => {
      const c = cat[l.account];
      if (c === 'revenue') rev += (l.side === 'credit' ? 1 : -1) * l.amount;
      else if (c === 'expense') exp += (l.side === 'debit' ? 1 : -1) * l.amount;
    });
  });
  const netIncome = rev - exp;
  const bsNet = closingExists ? 0 : netIncome;
  if (asset !== liab + equity + bsNet) add('bad', '貸借対照表が一致していません', '仕訳の借方・貸方に差異があります。');

  // 現預金
  let cash = 0; journals.forEach((j) => { if (j.date > t) return; (j.lines || []).forEach((l) => { if (l.account === '100' || l.account === '110') cash += (l.side === 'debit' ? 1 : -1) * l.amount; }); });
  if (cash < 0) add('bad', '現預金残高がマイナスです', `現預金 ${yen(cash)}。記帳誤りか資金不足の可能性。`);

  // 未入金・未計上
  const overdue = invoices.filter((i) => i.type === 'invoice' && i.posted && !i.paid && i.dueDate && i.dueDate < t);
  if (overdue.length) add('bad', `支払期限超過の未入金（${overdue.length}件）`, '取引先への督促をご検討ください。');
  const draft = invoices.filter((i) => i.type === 'invoice' && !i.posted);
  if (draft.length) add('info', `売上未計上の請求書（${draft.length}件）`, '売上計上漏れがないかご確認ください。');

  // 長期在庫車
  const thr = settings.stockAlertDays || 45;
  const ls = vehicles.filter((v) => !(v.status === 'sold' || (v.saleDate && v.saleDate <= t))).map((v) => ({ v, d: daysBetween(v.purchaseDate, null) })).filter((x) => x.d != null && x.d > thr);
  if (ls.length) add('warn', `長期在庫の車両（${ls.length}台）`, `最長 ${Math.max.apply(null, ls.map((x) => x.d))}日。販売・値下げの検討を。`);

  // 費用急増（直近月 vs 過去3か月平均）
  const months = []; for (let i = 0; i < 4; i++) { const d = new Date(t + 'T00:00:00'); d.setDate(1); d.setMonth(d.getMonth() - i); months.push(`${d.getFullYear()}-${pad(d.getMonth() + 1)}`); }
  const em = {}; journals.forEach((j) => { if (j.source === 'closing') return; const m = ymOf(j.date); (j.lines || []).forEach((l) => { if (cat[l.account] !== 'expense') return; em[l.account] = em[l.account] || {}; em[l.account][m] = (em[l.account][m] || 0) + (l.side === 'debit' ? 1 : -1) * l.amount; }); });
  Object.keys(em).forEach((acc) => { const cur = em[acc][months[0]] || 0; const avg = (months.slice(1).reduce((s, m) => s + (em[acc][m] || 0), 0)) / 3; if (cur >= 50000 && avg > 0 && cur > avg * 1.5) add('warn', `費用の急増：科目${acc}`, `今月 ${yen(cur)}（過去3か月平均 ${yen(Math.round(avg))}の${Math.round(cur / avg * 100)}%）。`); });

  // 重複仕訳
  const seen = {}; journals.forEach((j) => { if (j.source === 'closing' || j.source === 'depreciation') return; const tot = (j.lines || []).filter((l) => l.side === 'debit').reduce((s, l) => s + l.amount, 0); if (!j.description || !tot) return; const k = `${j.date}|${tot}|${j.description}`; seen[k] = (seen[k] || 0) + 1; });
  const dup = Object.keys(seen).filter((k) => seen[k] > 1).length;
  if (dup) add('warn', `重複の疑いがある仕訳（${dup}件）`, '同じ日付・金額・摘要の仕訳が複数あります。');

  // 未配賦過多
  if ((settings.departments || []).length) {
    let tg = 0, ut = 0; journals.forEach((j) => { if (j.source === 'closing' || !inRange(j.date, fy.start, fy.end)) return; const amt = (j.lines || []).filter((l) => l.side === 'debit').reduce((s, l) => s + l.amount, 0); if (j.dept) tg += amt; else ut += amt; });
    if (tg + ut > 0 && ut / (tg + ut) > 0.5) add('info', '事業未設定の取引が多い', `当期の約${Math.round(ut / (tg + ut) * 100)}%が未配賦です。`);
  }

  // 登録番号が無効な取引先
  const badReg = partners.filter((p) => p.regNo && !checkReg(p.regNo));
  if (badReg.length) add('warn', `登録番号が無効な取引先（${badReg.length}件）`, 'インボイス要件を満たさない可能性があります。');

  // バックアップ
  if (journals.length || invoices.length) {
    const last = settings.lastBackupAt;
    const d = last ? daysBetween(last, null) : null;
    if (d === null) add('warn', 'バックアップがありません', 'データはブラウザ内のみ。書き出して保管を。');
    else if (d >= 14) add('warn', `バックアップから${d}日経過`, 'データの書き出しをおすすめします。');
  }

  // 決算期接近
  const toEnd = daysBetween(t, fy.end);
  if (toEnd != null && toEnd >= 0 && toEnd <= 31) add('info', '決算期が近づいています', `当期末まで約${toEnd}日。決算・申告の準備を。`);

  const rank = { bad: 0, warn: 1, info: 2 };
  out.sort((a, b) => rank[a.level] - rank[b.level]);
  return { findings: out, metrics: { revenue: rev, expense: exp, net: netIncome, cash } };
}

async function main() {
  const cfg = L.cfgBase();
  if (!cfg.workspace) throw new Error('WORKSPACE が未設定です');
  const minLevel = (process.env.WATCH_MIN_LEVEL || 'warn').toLowerCase();
  const rank = { bad: 0, warn: 1, info: 2 };
  const threshold = rank[minLevel] != null ? rank[minLevel] : 1;

  const pull = await L.postJson(`${cfg.syncUrl}/api/pull`, { workspace: cfg.workspace, token: cfg.token });
  const stores = ((pull && pull.data) || {}).data || {};
  const settings = (stores.settings || []).find((x) => x.key === 'company') || {};
  const to = process.env.ALERT_TO || settings.email;

  const { findings, metrics } = watchdog(stores, settings);
  const notify = findings.filter((f) => rank[f.level] <= threshold);
  const cnt = { bad: 0, warn: 0, info: 0 }; findings.forEach((f) => (cnt[f.level] += 1));

  if (!notify.length) { console.log(`通知対象（${minLevel}以上）の検知事項はありません。（検知 計${findings.length}件）`); return; }

  // AI講評（任意）
  let comment = '';
  try {
    const r = await L.postJson(`${cfg.syncUrl}/api/ai`, { workspace: cfg.workspace, token: cfg.token,
      question: '検知された事項をふまえ、経営者向けに今月の注意点と打ち手を3つ、簡潔に挙げてください。',
      context: { metrics, alerts: findings.map((f) => ({ text: f.title })) } });
    if (r && r.answer) comment = String(r.answer).trim();
  } catch (e) { /* AI未設定は無視 */ }

  const label = { bad: '要対応', warn: '注意', info: 'お知らせ' };
  const lines = [
    `${settings.name || ''} AI監視アラート（${today()}）`, '',
    `要対応 ${cnt.bad}件／注意 ${cnt.warn}件／お知らせ ${cnt.info}件`, '',
    ...notify.map((f) => `・[${label[f.level]}] ${f.title}${f.detail ? `\n    ${f.detail}` : ''}`),
    comment ? `\n■AI講評\n${comment}` : '',
    '', '（会計アプリ AI監視より自動送信・概算です）',
  ];
  const body = lines.join('\n');
  const subject = `【AI監視】要対応${cnt.bad}・注意${cnt.warn}（${today()}）`;

  if (process.env.DRY_RUN === '1' || !to) {
    console.log(`[DRY_RUN] 宛先: ${to || '(未設定)'}\n件名: ${subject}\n---\n${body}`);
    return;
  }

  const cid = process.env.GMAIL_CLIENT_ID, csec = process.env.GMAIL_CLIENT_SECRET, rtok = process.env.GMAIL_REFRESH_TOKEN;
  if (!cid || !csec || !rtok) throw new Error('GMAIL_CLIENT_ID / _SECRET / _REFRESH_TOKEN が未設定です');
  const tok = await L.postForm(`${OAUTH}/token`, { client_id: cid, client_secret: csec, refresh_token: rtok, grant_type: 'refresh_token' });
  if (!tok.access_token) throw new Error('アクセストークンを取得できませんでした');
  const rfc822 = [
    `To: ${to}`, `Subject: =?UTF-8?B?${Buffer.from(subject, 'utf8').toString('base64')}?=`,
    'MIME-Version: 1.0', 'Content-Type: text/plain; charset="UTF-8"', 'Content-Transfer-Encoding: base64', '',
    Buffer.from(body, 'utf8').toString('base64'),
  ].join('\r\n');
  await L.postJson(`${GSEND}/gmail/v1/users/me/messages/send`, { raw: b64url(rfc822) }, { Authorization: `Bearer ${tok.access_token}` });
  console.log(`AI監視アラートを送信しました：${to}（要対応${cnt.bad}・注意${cnt.warn}）`);
}
main().catch((e) => { console.error('エラー:', e.message); process.exit(1); });
