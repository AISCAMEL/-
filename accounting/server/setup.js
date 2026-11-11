/* =========================================================================
 * server/setup.js ― セットアップ補助（依存ゼロ）
 *   node server/setup.js            … server/.env を作成し、設定状況を点検
 *   node server/setup.js --systemd  … 常時稼働用の systemd ユニット例を出力
 * ========================================================================= */
'use strict';
const fs = require('fs');
const path = require('path');
const DIR = __dirname;
const envPath = path.join(DIR, '.env');
const examplePath = path.join(DIR, '.env.example');

// 1) .env を用意
if (!fs.existsSync(envPath)) {
  fs.copyFileSync(examplePath, envPath);
  console.log('✓ server/.env を作成しました（.env.example をコピー）。エディタで値を編集してください。');
} else {
  console.log('• server/.env は既にあります（上書きしません）。');
}

// 2) .env 読み込み
const env = {};
try {
  fs.readFileSync(envPath, 'utf8').split(/\r?\n/).forEach((line) => {
    if (line.trim().startsWith('#')) return;
    const m = line.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\s*$/);
    if (m) { let v = m[2]; if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) v = v.slice(1, -1); env[m[1]] = v.trim(); }
  });
} catch (e) {}

const has = (k) => !!(env[k] && env[k] !== '' && !/ここに|example\.com|合言葉/.test(env[k]));
const ok = (b) => (b ? '✅' : '⬜');

// 3) Node バージョン
const major = Number(process.versions.node.split('.')[0]);
console.log(`\n• Node.js: v${process.versions.node} ${major >= 14 ? '(OK)' : '(⚠ v14以上を推奨)'}`);

// 4) 設定状況
const syncReady = has('WORKSPACE');
const gmailReady = has('GMAIL_CLIENT_ID') && has('GMAIL_CLIENT_SECRET') && has('GMAIL_REFRESH_TOKEN');
console.log('\n=== 設定状況 ===');
console.log(`${ok(syncReady)} 同期サーバー（WORKSPACE）`);
console.log(`${ok(has('ALERT_TO'))} 通知先メール（ALERT_TO）`);
console.log(`${ok(gmailReady)} Gmail送信/取込（GMAIL_CLIENT_ID / _SECRET / _REFRESH_TOKEN）`);
console.log(`${ok(has('AI_API_KEY'))} AI連携（AI_API_KEY・任意：AI講評/OCR）`);
console.log('\n--- 有効になるコネクタ ---');
console.log(`${ok(gmailReady && syncReady)} Gmail取込 / 在庫アラート / 月次レポート / AI監視`);
console.log(`${ok(has('STRIPE_API_KEY') && syncReady)} Stripe   ${ok(has('SQUARE_ACCESS_TOKEN') && syncReady)} Square   ${ok((has('PAYPAL_CLIENT_ID') && has('PAYPAL_SECRET')) && syncReady)} PayPal`);

// 5) systemd ユニット例
if (process.argv.includes('--systemd')) {
  const unit = `# /etc/systemd/system/aizu-kaikei.service
[Unit]
Description=Aizu Kaikei scheduler (sync server + connectors)
After=network-online.target

[Service]
WorkingDirectory=${path.dirname(DIR)}
ExecStart=${process.execPath} ${path.join(DIR, 'scheduler.js')}
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
`;
  console.log('\n=== systemd ユニット例（常時稼働） ===\n' + unit);
  console.log('保存後:  sudo systemctl daemon-reload && sudo systemctl enable --now aizu-kaikei');
}

// 6) 次の手順
console.log('\n=== 次の手順 ===');
if (!syncReady) console.log('1) server/.env の WORKSPACE / TOKEN を、会計アプリの「設定→クラウド同期」と一致させる');
if (!gmailReady) console.log('2) Gmailを使う場合： node server/gmail-token.js で GMAIL_REFRESH_TOKEN を取得し server/.env に設定');
console.log('3) 動作確認：  node server/scheduler.js --once   （有効なコネクタを一度だけ実行）');
console.log('4) 常時稼働：  node server/scheduler.js        （または npm start / systemd）');
console.log('\n詳しい設定は server/connectors/README.md を参照してください。');
