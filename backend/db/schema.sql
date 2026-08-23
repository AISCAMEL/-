-- =====================================================================
-- AIオペレーター24 — Production PostgreSQL schema
--
-- Generated to match EXACTLY the tables/columns the backend touches
-- (src/db/*, src/leads/*, src/outbound/*, src/calendar/*, src/apikeys/*,
--  src/admin/pnl.ts). Column names, types and nullability are inferred
-- from the SQL queries and cross-checked against the demo fixtures
-- (src/demo/fixtures.ts) and TypeScript interfaces (src/types.ts).
--
-- Conventions:
--   * IDs that the code inserts via `insert ... returning *` (no explicit
--     id) are `uuid primary key default gen_random_uuid()`.
--   * tenant ids are `uuid` (config.demoTenantId is a UUID).
--   * `tags` / keywords / scopes are `text[]`.
--   * business_hours / holiday_settings / meta / payload / metadata are `jsonb`.
--   * timestamps are `timestamptz`; money/counts are integer/numeric.
--   * `*_enabled` / `notify_*` flags are boolean.
-- =====================================================================

create extension if not exists pgcrypto;   -- gen_random_uuid()

-- =====================================================================
-- Core: tenants & settings
-- =====================================================================

-- テナント（契約企業）。src/db/queries.ts の listTenants / createTenant /
-- updateTenant(TENANT_FIELDS) / getTenantDetail / getUsageSummary が参照。
create table if not exists tenants (
  id                  uuid primary key default gen_random_uuid(),
  company_name        text not null,
  industry            text,
  plan                text not null default 'starter',   -- starter/business/pro/enterprise
  status              text not null default 'trial',      -- active/inactive/suspended/trial/closed
  billing_email       text,
  phone               text,
  address             text,
  memo                text,
  trial_ends_at       date,
  contract_started_at date,
  payment_status      text default 'none',                -- none/paid/overdue/...
  created_at          timestamptz not null default now()
);

-- テナント設定。SETTING_FIELDS(src/db/queries.ts) の全カラム + tenant_id(unique)。
-- upsert: `insert into tenant_settings (tenant_id) ... on conflict (tenant_id)`.
create table if not exists tenant_settings (
  id                       uuid primary key default gen_random_uuid(),
  tenant_id                uuid not null unique references tenants(id) on delete cascade,
  business_hours           jsonb,
  holiday_settings         jsonb,
  greeting_message         text,
  ai_tone                  text default 'polite',
  default_language         text default 'ja-JP',
  recording_enabled        boolean default false,
  human_transfer_enabled   boolean default true,
  transfer_phone_number    text,
  notification_email       text,
  slack_webhook_url        text,
  notify_on_call_end       boolean default true,
  notify_on_callback       boolean default true,
  notify_on_transfer       boolean default true,
  fallback_message         text,
  google_calendar_id       text,
  google_refresh_token     text,
  appointment_duration_min integer default 30,
  ai_instructions          text,
  reception_types          text,   -- comma/読点/改行 区切り文字列（配列も許容: parseReceptionTypes）
  sales_call_reply         text,
  online_booking_url       text,
  appraisal_form_url       text,
  created_at               timestamptz not null default now(),
  updated_at               timestamptz not null default now()
);

-- テナント内スタッフ。src/db/queries.ts の app_users クエリ群。
-- 重複メール検出は code の 23505 ハンドリングに合わせて (tenant_id, email) をユニークに。
create table if not exists app_users (
  id         uuid primary key default gen_random_uuid(),
  tenant_id  uuid not null references tenants(id) on delete cascade,
  name       text,
  email      text not null,
  role       text not null default 'staff',   -- owner/admin/staff/super_admin
  is_active  boolean not null default true,
  password_hash text,                          -- 自前ログイン用（scrypt）。未設定なら /api/auth/bootstrap で設定
  created_at timestamptz not null default now(),
  unique (tenant_id, email)
);

-- 着信番号。resolveTenantByPhone は phone_number + status='active' で解決。
create table if not exists phone_numbers (
  id          uuid primary key default gen_random_uuid(),
  tenant_id   uuid not null references tenants(id) on delete cascade,
  phone_number text not null unique,
  type        text,                       -- demo/twilio/...
  status      text not null default 'active',
  assigned_at timestamptz,
  created_at  timestamptz not null default now()
);

-- =====================================================================
-- Calls & conversation
-- =====================================================================

-- 通話レコード。createCall/finalizeCall/updateCallStatus/resummarizeCall や
-- ダッシュボード・利用量集計が参照。id は insert...returning id（自動採番）。
create table if not exists calls (
  id                 uuid primary key default gen_random_uuid(),
  tenant_id          uuid not null references tenants(id) on delete cascade,
  twilio_call_sid    text unique,                 -- on conflict (twilio_call_sid)
  twilio_session_id  text,
  from_number        text,
  to_number          text,
  status             text not null default 'in_progress',
  category           text,
  customer_name      text,
  company_name       text,
  requested_datetime text,                        -- 自由記述（例「明日の15時ごろ」）
  request_detail     text,
  summary            text,
  next_action        text,
  urgency            text,                         -- low/normal/high
  sentiment          text,                         -- positive/neutral/negative
  duration_sec       integer,
  tags               text[] not null default '{}',
  started_at         timestamptz,
  ended_at           timestamptz,
  created_at         timestamptz not null default now()
);
create index if not exists idx_calls_tenant       on calls (tenant_id);
create index if not exists idx_calls_started_at   on calls (started_at);
create index if not exists idx_calls_tenant_start on calls (tenant_id, started_at desc);
create index if not exists idx_calls_from_number  on calls (tenant_id, from_number);

-- 会話ログ。insert ... on conflict (call_id, sequence) do nothing。
create table if not exists transcripts (
  id        uuid primary key default gen_random_uuid(),
  call_id   uuid not null references calls(id) on delete cascade,
  tenant_id uuid references tenants(id) on delete cascade,
  speaker   text not null,     -- customer/ai/agent/system
  message   text not null,
  sequence  integer not null,
  created_at timestamptz not null default now(),
  unique (call_id, sequence)
);
create index if not exists idx_transcripts_call on transcripts (call_id, sequence);

-- 通話メモ（スタッフ手入力）。
create table if not exists call_notes (
  id         uuid primary key default gen_random_uuid(),
  call_id    uuid not null references calls(id) on delete cascade,
  tenant_id  uuid references tenants(id) on delete cascade,
  user_id    uuid,             -- app_users.id（null許容。FKは張らない）
  note       text not null,
  created_at timestamptz not null default now()
);
create index if not exists idx_call_notes_call on call_notes (call_id, created_at);

-- =====================================================================
-- Notifications & usage ledger
-- =====================================================================

-- 通知送信ログ。recordNotification / addNotification / listNotifications。
create table if not exists notifications (
  id            uuid primary key default gen_random_uuid(),
  tenant_id     uuid not null references tenants(id) on delete cascade,
  call_id       uuid references calls(id) on delete set null,
  type          text not null,          -- email/slack/...
  destination   text,
  status        text not null,          -- pending/sent/failed
  subject       text,
  payload       jsonb,
  error_message text,
  created_at    timestamptz not null default now(),
  sent_at       timestamptz
);
create index if not exists idx_notifications_tenant on notifications (tenant_id, created_at desc);

-- 利用量・原価の台帳。recordUsage。
create table if not exists usage_records (
  id          uuid primary key default gen_random_uuid(),
  tenant_id   uuid not null references tenants(id) on delete cascade,
  call_id     uuid references calls(id) on delete set null,
  usage_type  text not null,
  quantity    numeric not null default 0,
  unit        text,
  cost_amount numeric not null default 0,
  currency    text not null default 'JPY',
  metadata    jsonb,
  created_at  timestamptz not null default now()
);
create index if not exists idx_usage_tenant on usage_records (tenant_id, created_at);

-- =====================================================================
-- FAQ & caller rules
-- =====================================================================

-- FAQ。listFaqs は order by sort_order, created_at。
create table if not exists faqs (
  id         uuid primary key default gen_random_uuid(),
  tenant_id  uuid not null references tenants(id) on delete cascade,
  question   text not null,
  answer     text not null,
  category   text,
  keywords   text[] not null default '{}',
  is_active  boolean not null default true,
  sort_order integer not null default 1,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists idx_faqs_tenant on faqs (tenant_id, sort_order, created_at);

-- 発信者ルール（ブロック/専用アナウンス）。upsert on (tenant_id, phone_number)。
create table if not exists caller_rules (
  id           uuid primary key default gen_random_uuid(),
  tenant_id    uuid not null references tenants(id) on delete cascade,
  phone_number text not null,
  action       text not null default 'greeting',   -- block/greeting
  message      text,
  label        text,
  created_at   timestamptz not null default now(),
  unique (tenant_id, phone_number)
);

-- =====================================================================
-- CRM: contacts & activities
-- =====================================================================

-- 見込み客・取引先。src/outbound/contacts.ts。
create table if not exists contacts (
  id           uuid primary key default gen_random_uuid(),
  tenant_id    uuid not null references tenants(id) on delete cascade,
  name         text,
  company      text,
  phone_number text,
  email        text,
  category     text,
  note         text,
  tags         text[] not null default '{}',
  status       text not null default 'active',   -- active/in_progress/won/lost/do_not_contact
  created_at   timestamptz not null default now()
);
create index if not exists idx_contacts_tenant on contacts (tenant_id, created_at desc);

-- 連絡先の活動履歴。
create table if not exists contact_activities (
  id         uuid primary key default gen_random_uuid(),
  tenant_id  uuid not null references tenants(id) on delete cascade,
  contact_id uuid not null references contacts(id) on delete cascade,
  type       text not null,   -- status_changed/email_sent/sms_sent/note_added/...
  detail     text,
  created_at timestamptz not null default now()
);
create index if not exists idx_contact_activities on contact_activities (tenant_id, contact_id, created_at desc);

-- =====================================================================
-- Appointments (予約・査定)
-- =====================================================================

create table if not exists appointments (
  id             uuid primary key default gen_random_uuid(),
  tenant_id      uuid not null references tenants(id) on delete cascade,
  contact_id     uuid references contacts(id) on delete set null,
  call_id        uuid references calls(id) on delete set null,
  type           text not null default '査定',
  title          text,
  customer_name  text,
  phone_number   text,
  start_at       timestamptz not null,
  end_at         timestamptz not null,
  status         text not null default 'confirmed',   -- confirmed/tentative/cancelled
  source         text not null default 'manual',      -- manual/ai_inbound/ai_outbound
  google_event_id text,
  note           text,
  meet_url       text,
  created_at     timestamptz not null default now()
);
create index if not exists idx_appointments_tenant on appointments (tenant_id, start_at);

-- =====================================================================
-- Leads (LP 問い合わせ導線) — テナント非依存のグローバル台帳
-- =====================================================================

create table if not exists leads (
  id          uuid primary key default gen_random_uuid(),
  source      text not null default 'lp_form',
  category    text not null default 'inquiry',
  status      text not null default 'new',   -- new/contacted/in_progress/meeting_scheduled/won/lost/closed
  name        text,
  company     text,
  email       text,
  phone       text,
  industry    text,
  message     text,
  assigned_to text,                          -- 担当者（自由テキスト/ユーザ参照。FKなし）
  meta        jsonb not null default '{}',
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
create index if not exists idx_leads_status on leads (status, created_at desc);

create table if not exists lead_notes (
  id         uuid primary key default gen_random_uuid(),
  lead_id    uuid not null references leads(id) on delete cascade,
  note       text not null,
  created_at timestamptz not null default now()
);
create index if not exists idx_lead_notes_lead on lead_notes (lead_id, created_at);

create table if not exists meetings (
  id           uuid primary key default gen_random_uuid(),
  lead_id      uuid not null references leads(id) on delete cascade,
  title        text not null,
  scheduled_at timestamptz,
  status       text not null default 'proposed',   -- proposed/confirmed/canceled
  meeting_url  text,
  note         text,
  created_at   timestamptz not null default now()
);
create index if not exists idx_meetings_lead on meetings (lead_id, created_at);
create index if not exists idx_meetings_scheduled on meetings (scheduled_at);

-- ステップメール（アウトボックス）。processDueEmails は status='pending' and scheduled_at<=now()。
create table if not exists scheduled_emails (
  id           uuid primary key default gen_random_uuid(),
  lead_id      uuid not null references leads(id) on delete cascade,
  step_no      integer not null default 0,
  subject      text not null,
  body         text not null,
  to_email     text not null,
  scheduled_at timestamptz not null,
  status       text not null default 'pending',   -- pending/sent/failed/canceled
  sent_at      timestamptz,
  error        text,
  created_at   timestamptz not null default now()
);
create index if not exists idx_scheduled_emails_due on scheduled_emails (status, scheduled_at);
create index if not exists idx_scheduled_emails_lead on scheduled_emails (lead_id, step_no);

-- =====================================================================
-- Outbound campaigns (AI 発信)
-- =====================================================================

create table if not exists outbound_campaigns (
  id          uuid primary key default gen_random_uuid(),
  tenant_id   uuid not null references tenants(id) on delete cascade,
  name        text not null,
  purpose     text not null default 'sales',   -- sales/reminder/survey/followup/other
  goal_prompt text,
  opening     text,
  status      text not null default 'draft',    -- draft/active/...
  created_at  timestamptz not null default now()
);
create index if not exists idx_outbound_campaigns_tenant on outbound_campaigns (tenant_id, created_at desc);

create table if not exists outbound_targets (
  id           uuid primary key default gen_random_uuid(),
  campaign_id  uuid not null references outbound_campaigns(id) on delete cascade,
  tenant_id    uuid not null references tenants(id) on delete cascade,
  name         text,
  company      text,
  phone_number text not null,
  amount       integer,        -- 催促（reminder）用の金額（JPY）
  due_date     date,
  status       text not null default 'pending',   -- pending/done/...
  outcome      text,
  note         text,
  created_at   timestamptz not null default now()
);
create index if not exists idx_outbound_targets_campaign on outbound_targets (campaign_id, created_at);
create index if not exists idx_outbound_targets_phone on outbound_targets (campaign_id, phone_number);

-- =====================================================================
-- API keys (外部連携)
-- =====================================================================

create table if not exists api_keys (
  id           uuid primary key default gen_random_uuid(),
  tenant_id    uuid not null references tenants(id) on delete cascade,
  name         text not null,
  key_prefix   text,
  key_hash     text not null unique,   -- sha256（平文は保存しない）
  scopes       text[] not null default '{}',
  last_used_at timestamptz,
  revoked_at   timestamptz,
  created_at   timestamptz not null default now()
);
create index if not exists idx_api_keys_tenant on api_keys (tenant_id, created_at desc);

-- =====================================================================
-- Operator expenses (運営 P&L の固定費) — テナント非依存
-- =====================================================================

create table if not exists operator_expenses (
  id          uuid primary key default gen_random_uuid(),
  label       text not null,
  category    text not null default 'other',   -- personnel/infra/tools/marketing/other
  monthly_jpy integer not null default 0,
  created_at  timestamptz not null default now()
);
