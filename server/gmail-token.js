/* =========================================================================
 * server/gmail-token.js ― Gmail の GMAIL_REFRESH_TOKEN を取得する補助（依存ゼロ）
 *
 * 事前に Google Cloud で「OAuth クライアント（デスクトップアプリ）」を作成し、
 * その client_id / client_secret を環境変数で渡して実行します。
 *   GMAIL_CLIENT_ID=xxx GMAIL_CLIENT_SECRET=yyy node server/gmail-token.js
 *
 * ローカルにリダイレクト受け（http://localhost:5151）を立て、表示URLを
 * ブラウザで許可すると、refresh_token を取得して表示します。
 * ========================================================================= */
'use strict';
const http = require('http');
const { URL } = require('url');
const L = require('./connectors/_lib');

const OAUTH = process.env.GOOGLE_OAUTH_BASE || 'https://oauth2.googleapis.com';
const AUTH = process.env.GOOGLE_AUTH_BASE || 'https://accounts.google.com/o/oauth2/v2/auth';
const PORT = Number(process.argv[2] || process.env.OAUTH_PORT || 5151);
const cid = process.env.GMAIL_CLIENT_ID, csec = process.env.GMAIL_CLIENT_SECRET;

if (!cid || !csec) {
  console.error('GMAIL_CLIENT_ID と GMAIL_CLIENT_SECRET を環境変数で指定してください。');
  console.error('例: GMAIL_CLIENT_ID=xxx GMAIL_CLIENT_SECRET=yyy node server/gmail-token.js');
  process.exit(1);
}

const redirect = `http://localhost:${PORT}`;
const scope = encodeURIComponent('https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send');
const consent = `${AUTH}?client_id=${encodeURIComponent(cid)}&redirect_uri=${encodeURIComponent(redirect)}&response_type=code&access_type=offline&prompt=consent&scope=${scope}`;

console.log('\n次のURLをブラウザで開き、Googleアカウントで許可してください：\n');
console.log(consent + '\n');

const srv = http.createServer(async (req, res) => {
  const u = new URL(req.url, redirect);
  const code = u.searchParams.get('code');
  if (!code) { res.writeHead(400, { 'Content-Type': 'text/plain; charset=utf-8' }); res.end('code がありません'); return; }
  try {
    const tok = await L.postForm(`${OAUTH}/token`, { code, client_id: cid, client_secret: csec, redirect_uri: redirect, grant_type: 'authorization_code' });
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end('<h2>取得完了。ターミナルに戻って表示された GMAIL_REFRESH_TOKEN を server/.env に設定してください。</h2>');
    if (tok.refresh_token) {
      console.log('\n==============================================');
      console.log('GMAIL_REFRESH_TOKEN=' + tok.refresh_token);
      console.log('==============================================');
      console.log('↑ この行を server/.env に設定してください。');
    } else {
      console.log('\n⚠ refresh_token が返りませんでした。Googleアカウントのアプリ連携を解除してから、再度 prompt=consent で実行してください。');
    }
    setTimeout(() => process.exit(0), 400);
  } catch (e) {
    res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' }); res.end('エラー');
    console.error('トークン交換エラー:', e.message);
    process.exit(1);
  }
});
srv.listen(PORT, () => console.log(`（待受中: ${redirect} でGoogleからのリダイレクトを待っています。Ctrl+Cで中止）`));
