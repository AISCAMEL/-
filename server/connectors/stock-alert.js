/* =========================================================================
 * connectors/stock-alert.js ― 在庫車の長期在庫アラートをメール送信（依存ゼロ）
 *
 * クラウド同期サーバーから最新スナップショットを取得し、在庫日数が
 * しきい値を超えた車両があれば、その一覧を Gmail で送信する。
 * （アプリを開かなくても、サーバー側cronで定期的に督促できる）
 *
 * 必要な環境変数:
 *   SYNC_URL / WORKSPACE / TOKEN            … 会計アプリと同じ同期設定
 *   GMAIL_CLIENT_ID / _SECRET / _REFRESH_TOKEN … Gmail送信用 OAuth（scope: gmail.send）
 *   ALERT_TO                                … 通知先メール（省略時は会社設定のemail）
 *   STOCK_ALERT_DAYS                        … しきい値（省略時は会社設定→45）
 *   DRY_RUN=1                               … 送信せず内容だけ表示
 * ========================================================================= */
'use strict';
const L = require('./_lib');

const OAUTH = process.env.GOOGLE_OAUTH_BASE || 'https://oauth2.googleapis.com';
const GSEND = process.env.GMAIL_SEND_BASE || 'https://gmail.googleapis.com';

const today = () => L.ymd(new Date());
const daysBetween = (a, b) => {
  if (!a) return null;
  return Math.max(0, Math.round((new Date((b || today()) + 'T00:00:00') - new Date(a + 'T00:00:00')) / 86400000));
};
const b64url = (buf) => Buffer.from(buf).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

async function main() {
  const cfg = L.cfgBase();
  if (!cfg.workspace) throw new Error('WORKSPACE が未設定です');

  // 1) スナップショット取得
  const pull = await L.postJson(`${cfg.syncUrl}/api/pull`, { workspace: cfg.workspace, token: cfg.token });
  const snap = (pull && pull.data) || {};
  const stores = snap.data || {};
  const vehicles = stores.vehicles || [];
  const journals = stores.journals || [];
  const accounts = stores.accounts || [];
  const settings = (stores.settings || []).find((x) => x.key === 'company') || {};
  const catOf = {}; accounts.forEach((a) => (catOf[a.code] = a.category));

  const thr = Number(process.env.STOCK_ALERT_DAYS || settings.stockAlertDays || 45);
  const to = process.env.ALERT_TO || settings.email;

  // 2) 車両ごとの原価＆在庫日数
  const costOf = (vid) => {
    let cost = 0;
    journals.forEach((j) => {
      if (j.vehicleId !== vid) return;
      (j.lines || []).forEach((l) => { if (catOf[l.account] === 'expense') cost += (l.side === 'debit' ? 1 : -1) * l.amount; });
    });
    return cost;
  };
  const t = today();
  const longStock = vehicles
    .filter((v) => !(v.status === 'sold' || (v.saleDate && v.saleDate <= t)))
    .map((v) => ({ v, days: daysBetween(v.purchaseDate, null), cost: costOf(v.id) }))
    .filter((r) => r.days != null && r.days > thr)
    .sort((a, b) => b.days - a.days);

  if (!longStock.length) { console.log(`在庫日数 ${thr}日超の車両はありません。`); return; }

  // 3) メール本文
  const lines = [
    `長期在庫アラート（在庫日数 ${thr}日超：${longStock.length}台）`, '',
    ...longStock.map((r) => `・${r.v.name}　在庫${r.days}日　原価¥${Number(r.cost).toLocaleString('ja-JP')}${r.v.auctionSite ? `（${r.v.auctionSite}）` : ''}`),
    '', '販売・値下げのご検討をおすすめします。', '（会計アプリ「車両別損益」より自動送信）',
  ];
  const body = lines.join('\n');
  const subject = `【在庫アラート】長期在庫 ${longStock.length}台（最長${longStock[0].days}日）`;

  if (process.env.DRY_RUN === '1' || !to) {
    console.log(`[DRY_RUN] 宛先: ${to || '(未設定)'}\n件名: ${subject}\n---\n${body}`);
    if (!to) console.log('※ ALERT_TO も会社設定のemailも無いため送信しません。');
    return;
  }

  // 4) Gmail送信（OAuth: gmail.send）
  const cid = process.env.GMAIL_CLIENT_ID, csec = process.env.GMAIL_CLIENT_SECRET, rtok = process.env.GMAIL_REFRESH_TOKEN;
  if (!cid || !csec || !rtok) throw new Error('GMAIL_CLIENT_ID / _SECRET / _REFRESH_TOKEN が未設定です');
  const tok = await L.postForm(`${OAUTH}/token`, { client_id: cid, client_secret: csec, refresh_token: rtok, grant_type: 'refresh_token' });
  if (!tok.access_token) throw new Error('アクセストークンを取得できませんでした');

  const rfc822 = [
    `To: ${to}`,
    `Subject: =?UTF-8?B?${Buffer.from(subject, 'utf8').toString('base64')}?=`,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset="UTF-8"',
    'Content-Transfer-Encoding: base64', '',
    Buffer.from(body, 'utf8').toString('base64'),
  ].join('\r\n');

  await L.postJson(`${GSEND}/gmail/v1/users/me/messages/send`, { raw: b64url(rfc822) }, { Authorization: `Bearer ${tok.access_token}` });
  console.log(`在庫アラートを送信しました：${to}（${longStock.length}台）`);
}
main().catch((e) => { console.error('エラー:', e.message); process.exit(1); });
