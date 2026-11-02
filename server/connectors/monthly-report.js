/* =========================================================================
 * connectors/monthly-report.js ― 月次業績サマリを自動メール送信（依存ゼロ）
 *
 * クラウド同期サーバーからスナップショットを取得し、対象月（既定：先月）の
 * 売上・費用・利益・現預金・売掛/買掛を集計してメール送信する。
 * サーバーにAI連携(AI_API_KEY)があれば一言コメントも添える（任意）。
 *
 * 環境変数:
 *   SYNC_URL / WORKSPACE / TOKEN            … 会計アプリと同じ同期設定
 *   GMAIL_CLIENT_ID / _SECRET / _REFRESH_TOKEN … Gmail送信用 OAuth（gmail.send）
 *   ALERT_TO       … 通知先（省略時は会社設定email）
 *   MONTH=YYYY-MM  … 対象月（省略時は先月）
 *   DRY_RUN=1      … 送信せず内容のみ表示
 * ========================================================================= */
'use strict';
const L = require('./_lib');
const OAUTH = process.env.GOOGLE_OAUTH_BASE || 'https://oauth2.googleapis.com';
const GSEND = process.env.GMAIL_SEND_BASE || 'https://gmail.googleapis.com';

const pad = (x) => String(x).padStart(2, '0');
const prevMonth = () => { const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - 1); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}`; };
const monthBounds = (ym) => { const [y, m] = ym.split('-').map(Number); const end = new Date(y, m, 0); return { start: `${ym}-01`, end: `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}` }; };
const yen = (n) => '¥' + Math.round(Number(n) || 0).toLocaleString('ja-JP');
const b64url = (s) => Buffer.from(s).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

async function main() {
  const cfg = L.cfgBase();
  if (!cfg.workspace) throw new Error('WORKSPACE が未設定です');
  const ym = process.env.MONTH || prevMonth();
  const { start, end } = monthBounds(ym);

  const pull = await L.postJson(`${cfg.syncUrl}/api/pull`, { workspace: cfg.workspace, token: cfg.token });
  const stores = ((pull && pull.data) || {}).data || {};
  const journals = (stores.journals || []).filter((j) => j.source !== 'closing');
  const catOf = {}; (stores.accounts || []).forEach((a) => (catOf[a.code] = a.category));
  const settings = (stores.settings || []).find((x) => x.key === 'company') || {};
  const to = process.env.ALERT_TO || settings.email;

  const inRange = (d) => d >= start && d <= end;
  let revenue = 0, expense = 0;
  journals.forEach((j) => { if (!inRange(j.date)) return; (j.lines || []).forEach((l) => {
    const c = catOf[l.account];
    if (c === 'revenue') revenue += (l.side === 'credit' ? 1 : -1) * l.amount;
    else if (c === 'expense') expense += (l.side === 'debit' ? 1 : -1) * l.amount;
  }); });
  const balAsOf = (codes, normal) => { let v = 0; journals.forEach((j) => { if (j.date > end) return; (j.lines || []).forEach((l) => { if (codes.indexOf(l.account) < 0) return; v += (l.side === normal ? 1 : -1) * l.amount; }); }); return v; };
  const cash = balAsOf(['100', '110'], 'debit');
  const receivable = balAsOf(['120'], 'debit');
  const payable = balAsOf(['200', '210'], 'credit');
  const net = revenue - expense;

  // AIコメント（任意・失敗しても継続）
  let comment = '';
  try {
    const r = await L.postJson(`${cfg.syncUrl}/api/ai`, { workspace: cfg.workspace, token: cfg.token,
      question: `${ym}の月次業績を、経営者向けに2〜3文で簡潔に講評し、次月の着眼点を一つ挙げてください。`,
      context: { metrics: { revenue, expense, net, cash, receivable, payable } } });
    if (r && r.answer) comment = String(r.answer).trim();
  } catch (e) { /* AI未設定などは無視 */ }

  const [y, m] = ym.split('-');
  const subject = `【月次レポート】${y}年${Number(m)}月`;
  const body = [
    `${settings.name || ''} ${y}年${Number(m)}月 月次サマリ`, '',
    `売上：${yen(revenue)}`,
    `費用：${yen(expense)}`,
    `当期純利益：${yen(net)}`, '',
    `現預金残高：${yen(cash)}`,
    `売掛金：${yen(receivable)}`,
    `買掛金・未払金：${yen(payable)}`,
    comment ? `\n■コメント\n${comment}` : '',
    '', '（会計アプリより自動送信・概算です）',
  ].join('\n');

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
  console.log(`月次レポートを送信しました：${to}（${ym}）`);
}
main().catch((e) => { console.error('エラー:', e.message); process.exit(1); });
