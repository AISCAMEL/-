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

function daysFromNow(n: number): string {
  return new Date(Date.now() + n * 86400_000).toISOString().slice(0, 10); // YYYY-MM-DD
}

export const demoTenant = {
  id: TENANT,
  company_name: '合同会社アイズ（オークション代行 AUC-AGENT）',
  industry: '車オークション代行・買取',
  plan: 'business',
  status: 'trial',
  trial_ends_at: daysFromNow(5),       // 5日後にトライアル終了（アラート対象）
  contract_started_at: null as string | null,
  payment_status: 'none',
  created_at: new Date(Date.now() - 86400_000 * 12).toISOString(),
};

// 運営ダッシュボードを実感できるよう複数テナントを用意（デモ）。
export interface DemoTenantRow {
  id: string; company_name: string; industry: string; plan: string; status: string;
  trial_ends_at: string | null; contract_started_at: string | null; payment_status: string; created_at: string;
}
export const demoTenants: DemoTenantRow[] = [
  { ...demoTenant } as DemoTenantRow,
  { id: 'tn-2', company_name: '山田自動車販売', industry: '中古車販売', plan: 'pro', status: 'active', trial_ends_at: null, contract_started_at: daysFromNow(-180), payment_status: 'paid', created_at: new Date(Date.now() - 86400_000 * 200).toISOString() },
  { id: 'tn-3', company_name: '佐藤クリニック', industry: 'クリニック', plan: 'business', status: 'active', trial_ends_at: null, contract_started_at: daysFromNow(-90), payment_status: 'paid', created_at: new Date(Date.now() - 86400_000 * 100).toISOString() },
  { id: 'tn-4', company_name: '鈴木整体院', industry: '整体・接骨院', plan: 'starter', status: 'active', trial_ends_at: null, contract_started_at: daysFromNow(-45), payment_status: 'overdue', created_at: new Date(Date.now() - 86400_000 * 60).toISOString() },
  { id: 'tn-5', company_name: '田中不動産', industry: '不動産', plan: 'business', status: 'trial', trial_ends_at: daysFromNow(2), contract_started_at: null, payment_status: 'none', created_at: new Date(Date.now() - 86400_000 * 12).toISOString() },
  { id: 'tn-6', company_name: '海鮮居酒屋 大漁丸', industry: '飲食店', plan: 'starter', status: 'inactive', trial_ends_at: null, contract_started_at: daysFromNow(-30), payment_status: 'paid', created_at: new Date(Date.now() - 86400_000 * 40).toISOString() },
];

export const demoSettings = {
  tenant_id: TENANT,
  business_hours: { mon: [['10:00', '19:00']], tue: [['10:00', '19:00']], wed: [['10:00', '19:00']], thu: [['10:00', '19:00']], fri: [['10:00', '19:00']], sat: [['10:00', '17:00']] },
  holiday_settings: { weekly: ['sun'], dates: [] as string[] },
  greeting_message: 'お電話ありがとうございます。オークション代行のアイズ、AI受付です。購入代行・出品代行・買取査定など、ご用件をお話しください。',
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
  google_calendar_id: '',
  google_refresh_token: '',
  appointment_duration_min: 45,
};

export const demoPhoneNumbers = [
  { id: 'pn-1', tenant_id: TENANT, phone_number: '+815099998888', type: 'demo', status: 'active', assigned_at: iso(-60 * 24 * 7) },
];

export const demoCalls: DemoCall[] = [
  {
    id: 'call-1001', tenant_id: TENANT, from_number: '+819011112222', to_number: '+815099998888',
    status: 'completed', category: 'inquiry', customer_name: '山田太郎', company_name: null,
    requested_datetime: '明日の15時ごろ', request_detail: '買取査定の依頼（プリウス 2019年式）', summary: '山田様より買取査定のご依頼。プリウス2019年式・走行4万km。明日15時ごろに出張査定を希望。',
    next_action: '出張査定の日程確定・担当割当', urgency: 'normal', sentiment: 'positive', duration_sec: 96,
    started_at: iso(-35), ended_at: iso(-33), tags: ['買取査定'],
    transcripts: [
      { speaker: 'ai', message: 'お電話ありがとうございます。オークション代行のアイズ、AI受付です。ご用件をお話しください。', sequence: 0 },
      { speaker: 'customer', message: '車を売りたいんですけど、査定お願いできますか', sequence: 1 },
      { speaker: 'ai', message: 'ありがとうございます。買取査定を承ります。車種・年式・走行距離を教えていただけますか？', sequence: 2 },
      { speaker: 'customer', message: 'プリウスの2019年式、4万キロくらいです', sequence: 3 },
      { speaker: 'ai', message: 'プリウス2019年式・約4万kmですね。出張査定と持ち込み、どちらをご希望ですか？', sequence: 4 },
      { speaker: 'customer', message: '出張で、明日の15時くらいに来てもらえると', sequence: 5 },
      { speaker: 'ai', message: '明日15時ごろの出張査定ですね。お名前を伺えますか？', sequence: 6 },
      { speaker: 'customer', message: '山田太郎です', sequence: 7 },
      { speaker: 'ai', message: '山田様ですね。明日15時ごろの出張査定希望として承ります。担当者より日程確定のご連絡をいたします。', sequence: 8 },
    ],
    notes: [],
  },
  {
    id: 'call-1002', tenant_id: TENANT, from_number: '+819033334444', to_number: '+815099998888',
    status: 'callback_requested', category: 'callback', customer_name: '佐藤健一', company_name: null,
    requested_datetime: null, request_detail: '購入代行の見積もりについて折り返し希望', summary: '佐藤様より購入代行（アルファード希望・予算350万）の見積もりの件で折り返し希望。現在の番号で連絡可。',
    next_action: '担当バイヤーより折り返し', urgency: 'normal', sentiment: 'neutral', duration_sec: 68,
    started_at: iso(-120), ended_at: iso(-119), tags: ['購入代行'],
    transcripts: [
      { speaker: 'ai', message: 'お電話ありがとうございます。オークション代行のアイズ、AI受付です。ご用件をお話しください。', sequence: 0 },
      { speaker: 'customer', message: '購入代行を考えてて、担当の人に折り返してほしいんですが', sequence: 1 },
      { speaker: 'ai', message: '承知しました。購入代行のご相談ですね。ご希望の車種やご予算はお決まりですか？', sequence: 2 },
      { speaker: 'customer', message: 'アルファードで、予算は350万くらい', sequence: 3 },
      { speaker: 'ai', message: 'アルファード・ご予算350万円ほどですね。お名前を伺えますか？', sequence: 4 },
      { speaker: 'customer', message: '佐藤健一です', sequence: 5 },
      { speaker: 'ai', message: '佐藤様ですね。現在おかけの番号へ担当バイヤーより折り返しでよろしいでしょうか？', sequence: 6 },
      { speaker: 'customer', message: 'はい、お願いします', sequence: 7 },
      { speaker: 'ai', message: 'ありがとうございます。担当より折り返しご連絡いたします。', sequence: 8 },
    ],
    notes: [{ id: 'note-1', note: '予算350万・アルファード希望。優先対応。', created_at: iso(-110) }],
  },
  {
    id: 'call-1003', tenant_id: TENANT, from_number: '+819055556666', to_number: '+815099998888',
    status: 'new', category: 'inquiry', customer_name: null, company_name: null,
    requested_datetime: null, request_detail: '購入代行の手数料・会員登録の問い合わせ', summary: '購入代行の手数料と会員登録について問い合わせ。FAQに基づき「落札価格帯別の定額・会員登録は無料」と回答済み。',
    next_action: null, urgency: 'low', sentiment: 'neutral', duration_sec: 32,
    started_at: iso(-15), ended_at: iso(-14), tags: [],
    transcripts: [
      { speaker: 'ai', message: 'お電話ありがとうございます。オークション代行のアイズ、AI受付です。ご用件をお話しください。', sequence: 0 },
      { speaker: 'customer', message: '購入代行って手数料いくらですか？会員登録も要る？', sequence: 1 },
      { speaker: 'ai', message: '手数料は落札価格帯別の定額です。ご利用には無料の会員登録が必要で、メールがあれば1分ほどで完了します。ご希望の予算・車種を伺えれば、担当より詳しい概算をご案内します。', sequence: 2 },
      { speaker: 'customer', message: 'わかりました、ありがとう', sequence: 3 },
    ],
    notes: [],
  },
];

export const demoFaqs: DemoFaq[] = [
  { id: 'faq-1', tenant_id: TENANT, question: '営業時間・対応エリアを教えてください', answer: '受付は平日10時から19時、土曜10時から17時です。福島県いわき市を拠点に全国対応しております。', category: '営業案内', keywords: ['営業時間', '何時', 'エリア', '対応'], is_active: true, sort_order: 1, created_at: iso(-1000), updated_at: iso(-1000) },
  { id: 'faq-2', tenant_id: TENANT, question: '車を売りたい（買取査定・出品代行）', answer: '無料査定と出品代行を承っております。車種・年式・走行距離・お車の状態をお伺いし、担当者より査定・お手取りの目安をご案内します。', category: '出品・買取', keywords: ['売りたい', '買取', '査定', '出品'], is_active: true, sort_order: 2, created_at: iso(-1000), updated_at: iso(-1000) },
  { id: 'faq-3', tenant_id: TENANT, question: '購入代行の手数料はいくらですか', answer: '手数料は落札価格帯別の定額です。ご希望の予算・車種をお伺いし、担当者より詳細をご案内します。会員登録は無料です。', category: '購入代行', keywords: ['手数料', '料金', '購入', '代行'], is_active: true, sort_order: 3, created_at: iso(-1000), updated_at: iso(-1000) },
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
    id: 'camp-1', tenant_id: TENANT, name: '買取査定のご提案', purpose: 'sales',
    goal_prompt: '乗り換え・売却に関心がある方へ無料査定をご提案し、希望者は車種・年式・走行距離を伺い担当者へつなぐ。金額の確約はしない。',
    opening: 'お世話になっております。オークション代行アイズのAI担当です。お車の無料査定・買取のご案内でお電話しました。少しお時間よろしいでしょうか？',
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


export interface DemoContact {
  id: string; tenant_id: string; name: string | null; company: string | null;
  phone_number: string | null; email: string | null; category: string | null;
  note: string | null; tags: string[]; status: string; created_at: string;
}
export const demoContacts: DemoContact[] = [
  { id: 'ct-1', tenant_id: TENANT, name: '田中太郎', company: '田中商店', phone_number: '+819012340001', email: 'tanaka@example.com', category: '見込み客', note: '展示会で名刺交換。予約システムに関心。', tags: ['ホット'], status: 'in_progress', created_at: iso(-60 * 24 * 3) },
  { id: 'ct-2', tenant_id: TENANT, name: '佐藤花子', company: '佐藤クリニック', phone_number: '+819012340002', email: 'sato@example.com', category: '既存顧客', note: '毎月利用。アップセル候補。', tags: ['VIP'], status: 'won', created_at: iso(-60 * 24 * 10) },
  { id: 'ct-3', tenant_id: TENANT, name: '鈴木一郎', company: '鈴木工務店', phone_number: '+819012340003', email: null, category: '休眠', note: '半年連絡なし。再アプローチ。', tags: [], status: 'active', created_at: iso(-60 * 24 * 30) },
];

export interface DemoAppointment {
  id: string; tenant_id: string; contact_id: string | null; call_id: string | null;
  type: string; title: string | null; customer_name: string | null; phone_number: string | null;
  start_at: string; end_at: string; status: string; source: string;
  google_event_id: string | null; note: string | null; created_at: string;
}
// 当日の指定時刻(JST想定)を ISO で返すデモ用ヘルパー。
function todayAt(hour: number, min = 0): string {
  const d = new Date(); d.setHours(hour, min, 0, 0); return d.toISOString();
}
export const demoAppointments: DemoAppointment[] = [
  { id: 'ap-1', tenant_id: TENANT, contact_id: 'ct-1', call_id: null, type: '査定', title: '田中商店 出張査定', customer_name: '田中太郎', phone_number: '+819012340001', start_at: todayAt(11, 0), end_at: todayAt(11, 45), status: 'confirmed', source: 'ai_outbound', google_event_id: null, note: '車種：プリウス／年式2019', created_at: iso(-60 * 24) },
  { id: 'ap-2', tenant_id: TENANT, contact_id: null, call_id: 'call-1002', type: '来店', title: '佐藤様 来店相談', customer_name: '佐藤花子', phone_number: '+819033334444', start_at: todayAt(14, 30), end_at: todayAt(15, 0), status: 'tentative', source: 'ai_inbound', google_event_id: null, note: null, created_at: iso(-100) },
];

export interface DemoExpense { id: string; label: string; category: string; monthly_jpy: number; created_at: string; }
export const demoExpenses: DemoExpense[] = [
  { id: 'ex-1', label: '人件費（運営1名）', category: 'personnel', monthly_jpy: 300000, created_at: iso(-1000) },
  { id: 'ex-2', label: 'サーバー・インフラ（Render/Supabase等）', category: 'infra', monthly_jpy: 15000, created_at: iso(-1000) },
  { id: 'ex-3', label: 'ツール・SaaS（各種）', category: 'tools', monthly_jpy: 20000, created_at: iso(-1000) },
  { id: 'ex-4', label: '広告・マーケティング', category: 'marketing', monthly_jpy: 50000, created_at: iso(-1000) },
];

export interface DemoContactActivity {
  id: string; tenant_id: string; contact_id: string; type: string;
  detail: string | null; created_at: string;
}
export const demoContactActivities: DemoContactActivity[] = [
  { id: 'ca-1', tenant_id: TENANT, contact_id: 'ct-1', type: 'status_changed', detail: '見込み → 商談中', created_at: iso(-60 * 24 * 1) },
  { id: 'ca-2', tenant_id: TENANT, contact_id: 'ct-2', type: 'email_sent', detail: '【ご案内】新サービスのお知らせ', created_at: iso(-60 * 24 * 5) },
  { id: 'ca-3', tenant_id: TENANT, contact_id: 'ct-2', type: 'status_changed', detail: '商談中 → 成約', created_at: iso(-60 * 24 * 4) },
];
