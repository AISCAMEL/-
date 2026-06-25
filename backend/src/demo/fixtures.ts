// DBなしデモモード用のインメモリデータ。
// 管理画面をDB接続なしで動作確認・営業デモできるようにする。
// プロセス内で可変（POST/PATCH が反映される）。再起動でリセット。
import { config } from '../config.js';

const TENANT = config.demoTenantId;

export interface DemoCall {
  id: string;
  tenant_id: string;
  from_number: string;
  to_number: string;
  status: string;
  category: string | null;
  customer_name: string | null;
  company_name: string | null;
  requested_datetime: string | null;
  request_detail: string | null;
  summary: string | null;
  next_action: string | null;
  urgency: string | null;
  sentiment: string | null;
  duration_sec: number | null;
  started_at: string;
  ended_at: string | null;
  tags: string[];
  transcripts: { speaker: string; message: string; sequence: number }[];
  notes: { id: string; note: string; created_at: string }[];
}

export interface DemoFaq {
  id: string; tenant_id: string; question: string; answer: string;
  category: string | null; keywords: string[]; is_active: boolean;
  sort_order: number;
  created_at: string; updated_at: string;
}

function iso(offsetMin: number): string {
  return new Date(Date.now() + offsetMin * 60_000).toISOString();
}

export const demoTenant = {
  id: TENANT,
  company_name: 'デモ美容室 AISALON',
  industry: '美容室',
  plan: 'business',
  status: 'trial',
};

export const demoSettings = {
  tenant_id: TENANT,
  business_hours: { mon: [['10:00', '18:00']], tue: [['10:00', '18:00']], wed: [['10:00', '18:00']], thu: [['10:00', '18:00']], fri: [['10:00', '18:00']] },
  holiday_settings: { weekly: ['sat', 'sun'], dates: [] as string[] },
  greeting_message: 'お電話ありがとうございます。AI受付です。ご用件をお話しください。',
  ai_tone: 'polite',
  default_language: 'ja-JP',
  recording_enabled: false,
  human_transfer_enabled: true,
  transfer_phone_number: '+815011112222',
  notification_email: 'owner@example.com',
  slack_webhook_url: '',
  notify_on_call_end: true,
  notify_on_callback: true,
  notify_on_transfer: true,
  fallback_message: '申し訳ありません。担当者より折り返しご連絡いたします。',
};

export const demoPhoneNumbers = [
  { id: 'pn-1', tenant_id: TENANT, phone_number: '+815099998888', type: 'demo', status: 'active', assigned_at: iso(-60 * 24 * 7) },
];

export const demoCalls: DemoCall[] = [
  {
    id: 'call-1001', tenant_id: TENANT, from_number: '+819011112222', to_number: '+815099998888',
    status: 'completed', category: 'reservation', customer_name: '山田太郎', company_name: null,
    requested_datetime: '明日の15時', request_detail: 'カットの予約希望', summary: '明日15時にカットの予約希望。山田太郎様。折り返し不要。',
    next_action: '予約枠の確認', urgency: 'normal', sentiment: 'positive', duration_sec: 92,
    started_at: iso(-35), ended_at: iso(-33), tags: ['VIP'],
    transcripts: [
      { speaker: 'ai', message: 'お電話ありがとうございます。AI受付です。ご用件をお話しください。', sequence: 0 },
      { speaker: 'customer', message: '予約したいんですけど', sequence: 1 },
      { speaker: 'ai', message: 'ありがとうございます。ご予約希望ですね。ご希望の日にちとお時間を教えてください。', sequence: 2 },
      { speaker: 'customer', message: '明日の15時で', sequence: 3 },
      { speaker: 'ai', message: '明日の15時ですね。確認しました。次に、お名前をフルネームでお願いします。', sequence: 4 },
      { speaker: 'customer', message: '山田太郎です', sequence: 5 },
      { speaker: 'ai', message: '山田太郎様ですね。明日の15時に、山田太郎様で予約希望として受け付けます。この内容でよろしいでしょうか？', sequence: 6 },
      { speaker: 'customer', message: 'はい、お願いします', sequence: 7 },
      { speaker: 'ai', message: 'ありがとうございます。担当者よりあらためてご連絡いたします。失礼いたします。', sequence: 8 },
    ],
    notes: [],
  },
  {
    id: 'call-1002', tenant_id: TENANT, from_number: '+819033334444', to_number: '+815099998888',
    status: 'callback_requested', category: 'callback', customer_name: '佐藤花子', company_name: '佐藤商事',
    requested_datetime: null, request_detail: '見積もりについて折り返し希望', summary: '佐藤商事の佐藤様より見積もりの件で折り返し希望。現在の番号で連絡可。',
    next_action: '担当者より折り返し', urgency: 'normal', sentiment: 'neutral', duration_sec: 64,
    started_at: iso(-120), ended_at: iso(-119), tags: ['要注意'],
    transcripts: [
      { speaker: 'ai', message: 'お電話ありがとうございます。AI受付です。ご用件をお話しください。', sequence: 0 },
      { speaker: 'customer', message: '担当の人に折り返してほしいんですが', sequence: 1 },
      { speaker: 'ai', message: '承知しました。折り返しのご希望ですね。お名前と会社名を教えていただけますか？', sequence: 2 },
      { speaker: 'customer', message: '佐藤商事の佐藤です', sequence: 3 },
      { speaker: 'ai', message: '佐藤商事の佐藤様ですね。ご用件を簡単に教えていただけますか？', sequence: 4 },
      { speaker: 'customer', message: '見積もりの件で', sequence: 5 },
      { speaker: 'ai', message: '承知しました。現在おかけいただいている番号でよろしいでしょうか？', sequence: 6 },
      { speaker: 'customer', message: 'はい', sequence: 7 },
      { speaker: 'ai', message: 'ありがとうございます。担当者より折り返しご連絡いたします。', sequence: 8 },
    ],
    notes: [{ id: 'note-1', note: '既存顧客。優先対応。', created_at: iso(-110) }],
  },
  {
    id: 'call-1003', tenant_id: TENANT, from_number: '+819055556666', to_number: '+815099998888',
    status: 'new', category: 'inquiry', customer_name: null, company_name: null,
    requested_datetime: null, request_detail: '営業時間の問い合わせ', summary: '営業時間の問い合わせ。FAQに基づき平日10-18時と回答済み。',
    next_action: null, urgency: 'low', sentiment: 'neutral', duration_sec: 28,
    started_at: iso(-15), ended_at: iso(-14), tags: [],
    transcripts: [
      { speaker: 'ai', message: 'お電話ありがとうございます。AI受付です。ご用件をお話しください。', sequence: 0 },
      { speaker: 'customer', message: '営業時間を教えてください', sequence: 1 },
      { speaker: 'ai', message: '営業時間は、平日10時から18時までです。土日祝日はお休みです。', sequence: 2 },
      { speaker: 'customer', message: 'わかりました、ありがとう', sequence: 3 },
    ],
    notes: [],
  },
];

export const demoFaqs: DemoFaq[] = [
  { id: 'faq-1', tenant_id: TENANT, question: '営業時間を教えてください', answer: '営業時間は、平日10時から18時までです。土日祝日はお休みです。', category: '営業案内', keywords: ['営業時間', '何時'], is_active: true, sort_order: 1, created_at: iso(-1000), updated_at: iso(-1000) },
  { id: 'faq-2', tenant_id: TENANT, question: '駐車場はありますか', answer: '近隣のコインパーキングをご利用ください。提携駐車場はございません。', category: '営業案内', keywords: ['駐車場', '車'], is_active: true, sort_order: 2, created_at: iso(-1000), updated_at: iso(-1000) },
  { id: 'faq-3', tenant_id: TENANT, question: 'カットの料金はいくらですか', answer: 'カットは4,400円からとなっております。詳細は担当者よりご案内いたします。', category: '料金', keywords: ['料金', 'カット'], is_active: true, sort_order: 3, created_at: iso(-1000), updated_at: iso(-1000) },
];

export interface DemoUser {
  id: string; tenant_id: string; name: string; email: string;
  role: 'owner' | 'admin' | 'staff'; is_active: boolean; created_at: string;
}

export const demoUsers: DemoUser[] = [
  { id: 'user-1', tenant_id: TENANT, name: 'デモ店長', email: 'owner@example.com', role: 'owner', is_active: true, created_at: iso(-60 * 24 * 30) },
  { id: 'user-2', tenant_id: TENANT, name: '受付スタッフ A', email: 'staff-a@example.com', role: 'staff', is_active: true, created_at: iso(-60 * 24 * 10) },
];

export function newId(prefix: string): string {
  return `${prefix}-${Math.random().toString(36).slice(2, 10)}`;
}

export interface DemoCampaign {
  id: string; tenant_id: string; name: string; purpose: string;
  goal_prompt: string | null; opening: string | null; status: string; created_at: string;
}
export interface DemoTarget {
  id: string; campaign_id: string; tenant_id: string; name: string | null; company: string | null;
  phone_number: string; status: string; outcome: string | null; note: string | null;
  amount: number | null; due_date: string | null; created_at: string;
}

export const demoCampaigns: DemoCampaign[] = [
  {
    id: 'camp-1', tenant_id: TENANT, name: '新サービス案内（5月）', purpose: 'sales',
    goal_prompt: '当社の新しい予約システムを紹介し、興味があれば担当者との打合せを打診する。',
    opening: 'お世話になっております。デモ美容室AISALONのAI担当です。新サービスのご案内でお電話しました。少しお時間よろしいでしょうか？',
    status: 'draft', created_at: iso(-60 * 24 * 2),
  },
];
export const demoTargets: DemoTarget[] = [
  { id: 'tgt-1', campaign_id: 'camp-1', tenant_id: TENANT, name: '田中様', company: '田中商店', phone_number: '+819012340001', status: 'pending', outcome: null, note: null, amount: null, due_date: null, created_at: iso(-60 * 24) },
  { id: 'tgt-2', campaign_id: 'camp-1', tenant_id: TENANT, name: '鈴木様', company: null, phone_number: '+819012340002', status: 'pending', outcome: null, note: null, amount: null, due_date: null, created_at: iso(-60 * 24) },
];

export interface DemoNotification {
  id: string; tenant_id: string; call_id: string | null; type: string;
  destination: string | null; status: string; subject: string | null;
  error_message: string | null; created_at: string; sent_at: string | null;
}

export interface DemoCallerRule {
  id: string; tenant_id: string; phone_number: string;
  action: 'block' | 'greeting'; message: string | null; label: string | null; created_at: string;
}

export const demoCallerRules: DemoCallerRule[] = [
  { id: 'cr-1', tenant_id: TENANT, phone_number: '+819099990000', action: 'block', message: '申し訳ありませんが、このお電話はお受けできません。', label: '迷惑電話', created_at: iso(-60 * 24) },
  { id: 'cr-2', tenant_id: TENANT, phone_number: '+819011112222', action: 'greeting', message: 'いつもありがとうございます。VIPのお客様として担当者へおつなぎします。', label: 'VIP', created_at: iso(-60 * 24) },
];

export const demoNotifications: DemoNotification[] = [
  { id: 'ntf-1', tenant_id: TENANT, call_id: 'call-1001', type: 'email', destination: 'owner@example.com', status: 'sent', subject: '【AIオペレーター24】新しい電話受付がありました', error_message: null, created_at: iso(-33), sent_at: iso(-33) },
  { id: 'ntf-2', tenant_id: TENANT, call_id: 'call-1002', type: 'email', destination: 'owner@example.com', status: 'sent', subject: '【AIオペレーター24】新しい電話受付がありました', error_message: null, created_at: iso(-119), sent_at: iso(-119) },
  { id: 'ntf-3', tenant_id: TENANT, call_id: 'call-1002', type: 'slack', destination: 'slack', status: 'failed', subject: null, error_message: 'Slack 404: invalid_token', created_at: iso(-119), sent_at: null },
];

