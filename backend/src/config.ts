// 環境変数の集約。MVP段階では最小限の検証に留める。
function env(key: string, fallback?: string): string {
  const v = process.env[key] ?? fallback;
  if (v === undefined) throw new Error(`Missing required env: ${key}`);
  return v;
}

export const config = {
  port: Number(process.env.PORT ?? 8080),
  publicApiBaseUrl: env('PUBLIC_API_BASE_URL', 'http://localhost:8080'),
  publicWsBaseUrl: env('PUBLIC_WS_BASE_URL', 'ws://localhost:8080'),

  databaseUrl: process.env.DATABASE_URL ?? '',

  twilio: {
    accountSid: process.env.TWILIO_ACCOUNT_SID ?? '',
    authToken: process.env.TWILIO_AUTH_TOKEN ?? '',
    validateSignature: (process.env.TWILIO_VALIDATE_SIGNATURE ?? 'true') === 'true',
  },

  openai: {
    apiKey: process.env.OPENAI_API_KEY ?? '',
    // OpenAI互換のベースURL。OpenRouter等を使う場合に指定（例: https://openrouter.ai/api/v1）。
    baseUrl: process.env.OPENAI_BASE_URL ?? '',
    model: process.env.OPENAI_MODEL ?? 'gpt-4o-mini',
    summaryModel: process.env.OPENAI_SUMMARY_MODEL ?? 'gpt-4o-mini',
  },

  mail: {
    resendApiKey: process.env.RESEND_API_KEY ?? '',
    from: process.env.MAIL_FROM ?? 'AIオペレーター24 <noreply@ai-operator24.com>',
  },

  // Googleカレンダー連携。テナントごとの refresh_token と組み合わせて使う。
  // 未設定時はカレンダー連携オフ（内部予約のみで重複判定）。docs/google-calendar-setup.md 参照。
  google: {
    clientId: process.env.GOOGLE_CLIENT_ID ?? '',
    clientSecret: process.env.GOOGLE_CLIENT_SECRET ?? '',
  },

  // 決済（Square）。docs/square-billing.md 参照。未設定時は課金機能オフ。
  square: {
    env: process.env.SQUARE_ENV ?? 'sandbox',
    accessToken: process.env.SQUARE_ACCESS_TOKEN ?? '',
    locationId: process.env.SQUARE_LOCATION_ID ?? '',
    webhookSignatureKey: process.env.SQUARE_WEBHOOK_SIGNATURE_KEY ?? '',
  },

  auth: {
    // Supabase の JWT 秘密鍵（HS256）。設定時は署名検証する。
    jwtSecret: process.env.SUPABASE_JWT_SECRET ?? '',
    // 開発/デモ用。署名検証を行わず、ヘッダ or デフォルトでテナントを解決する。
    devMode: (process.env.AUTH_DEV_MODE ?? 'true') === 'true',
  },

  // デモテナント（seed.sql の固定 UUID）。
  demoTenantId: process.env.DEMO_TENANT_ID ?? '00000000-0000-0000-0000-000000000001',

  // 新規リード（問い合わせ）の通知先（自社営業）。
  leadsNotifyEmail: process.env.LEADS_NOTIFY_EMAIL ?? 'sales@ai-operator24.com',
  // ステップメール worker の実行間隔(秒)。0で無効。
  leadWorkerIntervalSec: Number(process.env.LEAD_WORKER_INTERVAL_SEC ?? 60),

  // アウトバウンド架電を許可する時間帯(JST)。督促等の法令配慮。既定 9-20時。
  outboundCallStartHourJst: Number(process.env.OUTBOUND_CALL_START_HOUR_JST ?? 9),
  outboundCallEndHourJst: Number(process.env.OUTBOUND_CALL_END_HOUR_JST ?? 20),

  // 週次サマリー自動送信（opt-in）。毎週月曜の指定時刻(UTC)に送信。
  weeklyDigestEnabled: (process.env.WEEKLY_DIGEST_ENABLED ?? 'false') === 'true',
  weeklyDigestHourUtc: Number(process.env.WEEKLY_DIGEST_HOUR_UTC ?? 23), // 23 UTC = 月曜8時(JST)

  // 管理画面フロントの許可オリジン（CORS）。
  corsOrigin: process.env.CORS_ORIGIN ?? '*',

  defaultGreeting:
    process.env.DEFAULT_GREETING ??
    'お電話ありがとうございます。AI受付です。ご用件をお話しください。',
} as const;
