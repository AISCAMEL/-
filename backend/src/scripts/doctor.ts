// 実機通話テスト前の設定診断ツール。
//   npm run doctor
// 環境変数・接続性・Twilioに設定すべきURLを確認し、問題を洗い出す。
import pg from 'pg';
import { config } from '../config.js';

type Level = 'ok' | 'warn' | 'error';
const marks: Record<Level, string> = { ok: '✅', warn: '⚠️ ', error: '❌' };
let hasError = false;
let hasWarn = false;

function line(level: Level, label: string, detail = '') {
  if (level === 'error') hasError = true;
  if (level === 'warn') hasWarn = true;
  console.log(`${marks[level]} ${label}${detail ? `  — ${detail}` : ''}`);
}

function isPublicUrl(url: string): boolean {
  return /^https?:\/\//.test(url) && !/localhost|127\.0\.0\.1|0\.0\.0\.0/.test(url);
}

async function main() {
  console.log('\n=== AIオペレーター24 設定診断 (doctor) ===\n');

  // --- 公開URL ---
  console.log('[ 公開URL（Twilioから到達できる必要あり） ]');
  if (isPublicUrl(config.publicApiBaseUrl)) {
    line('ok', 'PUBLIC_API_BASE_URL', config.publicApiBaseUrl);
  } else {
    line('warn', 'PUBLIC_API_BASE_URL がローカル', `${config.publicApiBaseUrl} — ngrok等の公開URLが必要`);
  }
  if (config.publicWsBaseUrl.startsWith('wss://')) {
    line('ok', 'PUBLIC_WS_BASE_URL', config.publicWsBaseUrl);
  } else {
    line('warn', 'PUBLIC_WS_BASE_URL が wss でない', `${config.publicWsBaseUrl} — 本番は wss:// 必須`);
  }
  console.log('');
  console.log('  Twilio 電話番号に設定するWebhook (A CALL COMES IN, HTTP POST):');
  console.log(`    ${config.publicApiBaseUrl}/api/twilio/incoming-call`);
  console.log('  ConversationRelay が接続するWS:');
  console.log(`    ${config.publicWsBaseUrl}/ws/conversation\n`);

  // --- Twilio ---
  console.log('[ Twilio ]');
  line(config.twilio.accountSid.startsWith('AC') ? 'ok' : 'warn', 'TWILIO_ACCOUNT_SID',
    config.twilio.accountSid ? config.twilio.accountSid.slice(0, 6) + '…' : '未設定');
  line(config.twilio.authToken ? 'ok' : 'warn', 'TWILIO_AUTH_TOKEN', config.twilio.authToken ? '設定済' : '未設定');
  line(config.twilio.validateSignature ? 'ok' : 'warn', '署名検証',
    config.twilio.validateSignature ? '有効' : '無効（TWILIO_VALIDATE_SIGNATURE=false）。本番では有効化を推奨');

  // --- OpenAI ---
  console.log('\n[ AI (OpenAI) ]');
  if (config.openai.apiKey) {
    line('ok', 'OPENAI_API_KEY', '設定済');
    line('ok', 'モデル', `応答=${config.openai.model} / 要約=${config.openai.summaryModel}`);
    line('ok', 'プロバイダ', config.openai.baseUrl ? config.openai.baseUrl : 'OpenAI（標準）');
  } else {
    line('warn', 'OPENAI_API_KEY 未設定', 'フォールバック応答モードで動作（実機テストには設定推奨）');
  }

  // --- DB ---
  console.log('\n[ データベース ]');
  if (!config.databaseUrl) {
    line('warn', 'DATABASE_URL 未設定', 'デモモード（インメモリ）。通話ログは永続化されません');
  } else {
    const pool = new pg.Pool({ connectionString: config.databaseUrl, connectionTimeoutMillis: 5000 });
    try {
      const r = await pool.query('select count(*)::int as n from tenants');
      line('ok', 'DB接続', `tenants ${r.rows[0].n} 件`);
      const pn = await pool.query('select count(*)::int as n from phone_numbers where status = $1', ['active']);
      if (pn.rows[0].n === 0) line('warn', '有効な電話番号', '0件 — phone_numbers にTwilio番号を登録してください');
      else line('ok', '有効な電話番号', `${pn.rows[0].n} 件`);
    } catch (err) {
      line('error', 'DB接続失敗', String((err as Error).message));
      console.log('     → schema.sql / seed.sql を投入したか、DATABASE_URL を確認してください');
    } finally {
      await pool.end().catch(() => {});
    }
  }

  // --- 通知 ---
  console.log('\n[ 通知 ]');
  line(config.mail.resendApiKey ? 'ok' : 'warn', 'RESEND_API_KEY',
    config.mail.resendApiKey ? '設定済' : '未設定（dry-runでコンソール出力）');

  // --- 認証 ---
  console.log('\n[ 管理画面認証 ]');
  if (config.auth.jwtSecret) line('ok', 'SUPABASE_JWT_SECRET', '設定済（署名検証あり）');
  else if (config.auth.devMode) line('warn', '認証 devモード', 'JWT署名検証なし。本番では SUPABASE_JWT_SECRET を設定し AUTH_DEV_MODE=false に');
  else line('error', '認証未構成', 'jwtSecret も devMode もなし');

  // --- 総括 ---
  console.log('\n=== 結果 ===');
  if (hasError) console.log('❌ 重大な問題があります。上記を解消してください。');
  else if (hasWarn) console.log('⚠️  警告あり。デモは可能ですが、実機通話テスト前に確認してください。');
  else console.log('✅ 実機通話テストの準備が整っています。');
  console.log('');

  process.exit(hasError ? 1 : 0);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
