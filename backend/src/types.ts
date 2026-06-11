// ドメイン共通型。DB ENUM と一致させる。

export type CallCategory =
  | 'reservation' | 'inquiry' | 'pricing' | 'callback'
  | 'transfer' | 'complaint' | 'other';

export type ConversationState =
  | 'initial' | 'intent_detected' | 'inquiry_answering'
  | 'reservation_collect_datetime' | 'reservation_collect_name' | 'reservation_confirm'
  | 'callback_collect_name' | 'callback_collect_detail' | 'callback_confirm'
  | 'transfer_precheck' | 'transfer_ready' | 'complaint_escalation'
  | 'closing' | 'ended';

export interface ExtractedFields {
  customer_name: string | null;
  company_name: string | null;
  requested_datetime: string | null;
  request_detail: string | null;
  callback_requested: boolean;
  callback_number_confirmed: boolean;
}

// LLM がターンごとに返す出力契約（docs/ai-conversation.md §2）
export interface AiTurnResult {
  reply: string;
  intent: CallCategory;
  state: ConversationState;
  extracted: ExtractedFields;
  should_transfer: boolean;
  should_end_call: boolean;
  next_action: string | null;
  confidence: number;
}

// 通話終了後の要約契約（docs/ai-conversation.md §6）
export interface CallSummary {
  summary: string;
  category: CallCategory;
  customer_name: string | null;
  company_name: string | null;
  requested_datetime: string | null;
  request_detail: string | null;
  next_action: string | null;
  urgency: 'low' | 'normal' | 'high';
  sentiment: 'positive' | 'neutral' | 'negative';
  callback_requested: boolean;
  should_follow_up: boolean;
}

// テナント設定（DB tenant_settings の主要項目）
export interface TenantContext {
  tenantId: string;
  companyName: string;
  industry: string | null;
  greetingMessage: string;
  aiTone: string;
  businessHours: unknown;
  holidaySettings: unknown;
  humanTransferEnabled: boolean;
  transferPhoneNumber: string | null;
  notificationEmail: string | null;
  slackWebhookUrl: string | null;
  notifyOnCallEnd: boolean;
  notifyOnCallback: boolean;
  notifyOnTransfer: boolean;
  fallbackMessage: string | null;
  faqs: Faq[];
}

export interface Faq {
  question: string;
  answer: string;
  category: string | null;
}

export interface TranscriptLine {
  speaker: 'customer' | 'ai' | 'agent' | 'system';
  message: string;
}
