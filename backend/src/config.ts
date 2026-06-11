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
    model: process.env.OPENAI_MODEL ?? 'gpt-4o-mini',
    summaryModel: process.env.OPENAI_SUMMARY_MODEL ?? 'gpt-4o-mini',
  },

  mail: {
    resendApiKey: process.env.RESEND_API_KEY ?? '',
    from: process.env.MAIL_FROM ?? 'AIオペレーター24 <noreply@ai-operator24.com>',
  },

  defaultGreeting:
    process.env.DEFAULT_GREETING ??
    'お電話ありがとうございます。AI受付です。ご用件をお話しください。',
} as const;
