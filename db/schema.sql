-- =============================================================
-- AIオペレーター24  データベーススキーマ
-- PostgreSQL 15+ / Supabase
--
-- 設計方針:
--   * マルチテナント前提。全業務テーブルに tenant_id を持たせ RLS で分離。
--   * MVPでは少数 Twilio 番号で運用するが、将来の Subaccount 分離に耐える構造。
--   * 金額は数値で保持し、通貨は currency 列で管理（MVPは JPY 固定）。
-- 実行: psql "$DATABASE_URL" -f db/schema.sql
-- =============================================================

create extension if not exists "pgcrypto";   -- gen_random_uuid()

-- -------------------------------------------------------------
-- ENUM 型
-- -------------------------------------------------------------
do $$ begin
  create type tenant_status       as enum ('active','inactive','suspended','trial','closed');
exception when duplicate_object then null; end $$;

do $$ begin
  create type plan_type           as enum ('starter','business','pro','enterprise');
exception when duplicate_object then null; end $$;

do $$ begin
  create type user_role           as enum ('owner','admin','staff','super_admin');
exception when duplicate_object then null; end $$;

do $$ begin
  create type call_status         as enum ('new','in_progress','completed','need_human',
                                           'transferred','callback_requested','failed','closed');
exception when duplicate_object then null; end $$;

do $$ begin
  create type call_category       as enum ('reservation','inquiry','pricing','callback',
                                           'transfer','complaint','other');
exception when duplicate_object then null; end $$;

do $$ begin
  create type transcript_speaker  as enum ('customer','ai','agent','system');
exception when duplicate_object then null; end $$;

do $$ begin
  create type notification_status as enum ('pending','sent','failed');
exception when duplicate_object then null; end $$;

do $$ begin
  create type notification_type   as enum ('email','slack','line','webhook');
exception when duplicate_object then null; end $$;

do $$ begin
  create type phone_number_type   as enum ('main','demo','overflow','test');
exception when duplicate_object then null; end $$;

do $$ begin
  create type urgency_level       as enum ('low','normal','high');
exception when duplicate_object then null; end $$;

do $$ begin
  create type sentiment_label     as enum ('positive','neutral','negative');
exception when duplicate_object then null; end $$;

-- -------------------------------------------------------------
-- 共通: updated_at 自動更新トリガ関数
-- -------------------------------------------------------------
create or replace function set_updated_at() returns trigger as $$
begin
  new.updated_at = now();
  return new;
end;
$$ language plpgsql;

-- =============================================================
-- tenants  (契約企業/店舗)
-- =============================================================
create table if not exists tenants (
  id                    uuid primary key default gen_random_uuid(),
  company_name          text          not null,
  industry              text,
  plan                  plan_type     not null default 'starter',
  status                tenant_status not null default 'trial',
  billing_email         text,
  phone                 text,
  address               text,
  twilio_subaccount_sid text,          -- MVPは null。将来 Subaccount 分離時に使用
  memo                  text,
  created_at            timestamptz   not null default now(),
  updated_at            timestamptz   not null default now()
);
create trigger trg_tenants_updated before update on tenants
  for each row execute function set_updated_at();

-- =============================================================
-- app_users  (管理画面ユーザ。auth_user_id は Supabase Auth/Clerk 等の ID)
-- =============================================================
create table if not exists app_users (
  id           uuid primary key default gen_random_uuid(),
  auth_user_id text unique,                    -- 認証基盤側のユーザID
  tenant_id    uuid references tenants(id) on delete cascade,  -- super_admin は null 可
  name         text,
  email        text not null,
  role         user_role not null default 'staff',
  is_active    boolean not null default true,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now(),
  unique (tenant_id, email)
);
create index if not exists idx_app_users_tenant on app_users(tenant_id);
create trigger trg_app_users_updated before update on app_users
  for each row execute function set_updated_at();

-- =============================================================
-- tenant_settings  (テナント別 AI / 通知 / 営業時間設定。1テナント1行)
-- =============================================================
create table if not exists tenant_settings (
  id                     uuid primary key default gen_random_uuid(),
  tenant_id              uuid not null unique references tenants(id) on delete cascade,
  business_hours         jsonb  not null default '{}'::jsonb,   -- {"mon":[["10:00","18:00"]], ...}
  holiday_settings       jsonb  not null default '{}'::jsonb,   -- {"weekly":["sat","sun"],"dates":["2026-01-01"]}
  greeting_message       text,
  ai_tone                text   not null default 'polite',      -- polite / friendly / formal
  default_language       text   not null default 'ja-JP',
  recording_enabled      boolean not null default false,
  human_transfer_enabled boolean not null default true,
  transfer_phone_number  text,
  notification_email     text,
  slack_webhook_url      text,
  notify_on_call_end     boolean not null default true,
  notify_on_callback     boolean not null default true,
  notify_on_transfer     boolean not null default true,
  fallback_message       text,
  created_at             timestamptz not null default now(),
  updated_at             timestamptz not null default now()
);
create trigger trg_tenant_settings_updated before update on tenant_settings
  for each row execute function set_updated_at();

-- =============================================================
-- phone_numbers  (Twilio 番号とテナントの紐付け)
-- =============================================================
create table if not exists phone_numbers (
  id                       uuid primary key default gen_random_uuid(),
  tenant_id                uuid references tenants(id) on delete set null,  -- 未割当=null
  phone_number             text not null unique,            -- E.164 (+81...)
  twilio_phone_number_sid  text,
  twilio_account_sid       text,
  type                     phone_number_type not null default 'main',
  status                   text not null default 'active',  -- active / released
  assigned_at              timestamptz,
  created_at               timestamptz not null default now()
);
create index if not exists idx_phone_numbers_tenant on phone_numbers(tenant_id);

-- =============================================================
-- calls  (通話。1着信1行)
-- =============================================================
create table if not exists calls (
  id                  uuid primary key default gen_random_uuid(),
  tenant_id           uuid not null references tenants(id) on delete cascade,
  twilio_call_sid     text unique,
  twilio_session_id   text,
  twilio_account_sid  text,
  from_number         text,
  to_number           text,
  status              call_status   not null default 'new',
  category            call_category,
  customer_name       text,
  company_name        text,
  requested_datetime  text,          -- 自然言語のまま保持（"明日の15時"等）。確定値は別途
  request_detail      text,
  summary             text,
  next_action         text,
  urgency             urgency_level,
  sentiment           sentiment_label,
  ai_confidence       numeric(4,3),  -- 0.000 - 1.000
  duration_sec        integer,
  recording_url       text,
  recording_sid       text,
  transferred_to      text,
  transfer_status     text,          -- requested / connected / failed / no_answer
  started_at          timestamptz,
  ended_at            timestamptz,
  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now()
);
create index if not exists idx_calls_tenant_started on calls(tenant_id, started_at desc);
create index if not exists idx_calls_tenant_status  on calls(tenant_id, status);
create index if not exists idx_calls_category        on calls(tenant_id, category);
create trigger trg_calls_updated before update on calls
  for each row execute function set_updated_at();

-- =============================================================
-- transcripts  (発話単位の文字起こし)
-- =============================================================
create table if not exists transcripts (
  id         uuid primary key default gen_random_uuid(),
  call_id    uuid not null references calls(id) on delete cascade,
  tenant_id  uuid not null references tenants(id) on delete cascade,
  speaker    transcript_speaker not null,
  message    text not null,
  sequence   integer not null,         -- 会話内の順序
  metadata   jsonb default '{}'::jsonb,
  created_at timestamptz not null default now(),
  unique (call_id, sequence)
);
create index if not exists idx_transcripts_call on transcripts(call_id, sequence);

-- =============================================================
-- faqs  (テナント別 FAQ)
-- =============================================================
create table if not exists faqs (
  id         uuid primary key default gen_random_uuid(),
  tenant_id  uuid not null references tenants(id) on delete cascade,
  question   text not null,
  answer     text not null,
  category   text,
  keywords   text[] default '{}',
  is_active  boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists idx_faqs_tenant_active on faqs(tenant_id, is_active);
create trigger trg_faqs_updated before update on faqs
  for each row execute function set_updated_at();

-- =============================================================
-- call_notes  (社内メモ)
-- =============================================================
create table if not exists call_notes (
  id         uuid primary key default gen_random_uuid(),
  call_id    uuid not null references calls(id) on delete cascade,
  tenant_id  uuid not null references tenants(id) on delete cascade,
  user_id    uuid references app_users(id) on delete set null,
  note       text not null,
  created_at timestamptz not null default now()
);
create index if not exists idx_call_notes_call on call_notes(call_id, created_at);

-- =============================================================
-- notifications  (通知ログ)
-- =============================================================
create table if not exists notifications (
  id            uuid primary key default gen_random_uuid(),
  tenant_id     uuid not null references tenants(id) on delete cascade,
  call_id       uuid references calls(id) on delete set null,
  type          notification_type   not null,
  destination   text,
  status        notification_status not null default 'pending',
  subject       text,
  payload       jsonb default '{}'::jsonb,
  error_message text,
  sent_at       timestamptz,
  created_at    timestamptz not null default now()
);
create index if not exists idx_notifications_tenant on notifications(tenant_id, created_at desc);

-- =============================================================
-- usage_records  (利用量・課金原資。通話分数や転送分数を記録)
-- =============================================================
create table if not exists usage_records (
  id           uuid primary key default gen_random_uuid(),
  tenant_id    uuid not null references tenants(id) on delete cascade,
  call_id      uuid references calls(id) on delete set null,
  usage_type   text not null,                  -- ai_minutes / transfer_minutes / recording 等
  quantity     numeric(12,3) not null default 0,
  unit         text not null default 'minute',
  cost_amount  numeric(12,2),                  -- 原価
  price_amount numeric(12,2),                  -- 顧客請求額
  currency     text not null default 'JPY',
  metadata     jsonb default '{}'::jsonb,
  created_at   timestamptz not null default now()
);
create index if not exists idx_usage_tenant_created on usage_records(tenant_id, created_at desc);

-- =============================================================
-- ai_sessions  (進行中の会話セッション状態。realtime 用)
-- =============================================================
create table if not exists ai_sessions (
  id         uuid primary key default gen_random_uuid(),
  tenant_id  uuid not null references tenants(id) on delete cascade,
  call_id    uuid references calls(id) on delete cascade,
  session_id text unique,                       -- Twilio sessionId
  state      text not null default 'initial',
  intent     call_category,
  extracted  jsonb not null default '{}'::jsonb,
  is_active  boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists idx_ai_sessions_active on ai_sessions(tenant_id, is_active);
create trigger trg_ai_sessions_updated before update on ai_sessions
  for each row execute function set_updated_at();

-- =============================================================
-- Row Level Security (RLS)
--   * JWT クレーム app.tenant_id / app.role でテナント分離。
--   * super_admin は全テナント参照可。
--   * バックエンドは service_role 接続で RLS を迂回し、アプリ層で tenant を担保する想定。
--     （管理画面が PostgREST/Supabase 経由で直接読む場合に下記ポリシが効く）
-- =============================================================
-- 現在のリクエストの tenant_id を返すヘルパ
create or replace function current_tenant_id() returns uuid as $$
  select nullif(current_setting('request.jwt.claim.tenant_id', true), '')::uuid;
$$ language sql stable;

create or replace function is_super_admin() returns boolean as $$
  select coalesce(current_setting('request.jwt.claim.role', true) = 'super_admin', false);
$$ language sql stable;

do $$
declare t text;
begin
  foreach t in array array[
    'tenants','app_users','tenant_settings','phone_numbers','calls','transcripts',
    'faqs','call_notes','notifications','usage_records','ai_sessions'
  ] loop
    execute format('alter table %I enable row level security;', t);
  end loop;
end $$;

-- tenants 本体は id で判定
drop policy if exists tenant_isolation on tenants;
create policy tenant_isolation on tenants
  using (is_super_admin() or id = current_tenant_id());

-- tenant_id を持つテーブルは共通ポリシ
do $$
declare t text;
begin
  foreach t in array array[
    'app_users','tenant_settings','phone_numbers','calls','transcripts',
    'faqs','call_notes','notifications','usage_records','ai_sessions'
  ] loop
    execute format('drop policy if exists tenant_isolation on %I;', t);
    execute format($f$
      create policy tenant_isolation on %I
        using (is_super_admin() or tenant_id = current_tenant_id())
        with check (is_super_admin() or tenant_id = current_tenant_id());
    $f$, t);
  end loop;
end $$;

-- =============================================================
-- 集計ビュー: ダッシュボード用（テナント別 当日/当月）
-- =============================================================
create or replace view dashboard_daily as
select
  tenant_id,
  count(*)                                              as calls_today,
  count(*) filter (where status = 'completed')          as completed_count,
  count(*) filter (where status = 'callback_requested') as callback_count,
  count(*) filter (where status = 'transferred')        as transfer_count,
  count(*) filter (where status in ('new','need_human'))as unhandled_count,
  coalesce(avg(duration_sec),0)::int                    as avg_duration_sec
from calls
where started_at >= date_trunc('day', now())
group by tenant_id;

-- =============================================================
-- 問い合わせ導線（リード管理）  ※運営者(自社)向け・テナント非依存
--   LP問い合わせ/資料請求 → リード → ステップメール → 商談 → 受注後フォロー
-- =============================================================
do $$ begin
  create type lead_source   as enum ('lp_form','contact','demo_request','referral','manual','phone');
exception when duplicate_object then null; end $$;
do $$ begin
  create type lead_category as enum ('inquiry','consultation','demo','document','order_followup','other');
exception when duplicate_object then null; end $$;
do $$ begin
  create type lead_status   as enum ('new','contacted','in_progress','meeting_scheduled','won','lost','closed');
exception when duplicate_object then null; end $$;
do $$ begin
  create type meeting_status as enum ('proposed','confirmed','done','canceled');
exception when duplicate_object then null; end $$;
do $$ begin
  create type scheduled_email_status as enum ('pending','sent','failed','canceled');
exception when duplicate_object then null; end $$;

create table if not exists leads (
  id           uuid primary key default gen_random_uuid(),
  source       lead_source   not null default 'lp_form',
  category     lead_category not null default 'inquiry',
  status       lead_status   not null default 'new',
  name         text,
  company      text,
  email        text,
  phone        text,
  industry     text,
  message      text,
  assigned_to  uuid references app_users(id) on delete set null,
  meta         jsonb not null default '{}'::jsonb,   -- utm等
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
create index if not exists idx_leads_status on leads(status, created_at desc);
create trigger trg_leads_updated before update on leads
  for each row execute function set_updated_at();

create table if not exists lead_notes (
  id         uuid primary key default gen_random_uuid(),
  lead_id    uuid not null references leads(id) on delete cascade,
  user_id    uuid references app_users(id) on delete set null,
  note       text not null,
  created_at timestamptz not null default now()
);
create index if not exists idx_lead_notes_lead on lead_notes(lead_id, created_at);

create table if not exists meetings (
  id           uuid primary key default gen_random_uuid(),
  lead_id      uuid not null references leads(id) on delete cascade,
  title        text not null default '商談・ご相談',
  scheduled_at timestamptz,
  status       meeting_status not null default 'proposed',
  meeting_url  text,
  note         text,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
create index if not exists idx_meetings_lead on meetings(lead_id, created_at);
create trigger trg_meetings_updated before update on meetings
  for each row execute function set_updated_at();

-- ステップメールの送信予約（アウトボックス）。本文はリード作成時に確定して積む。
create table if not exists scheduled_emails (
  id           uuid primary key default gen_random_uuid(),
  lead_id      uuid not null references leads(id) on delete cascade,
  step_no      integer not null,
  subject      text not null,
  body         text not null,
  to_email     text not null,
  scheduled_at timestamptz not null,
  status       scheduled_email_status not null default 'pending',
  sent_at      timestamptz,
  error        text,
  created_at   timestamptz not null default now()
);
create index if not exists idx_scheduled_emails_due on scheduled_emails(status, scheduled_at);

-- これらは運営者(super_admin)のみ参照。RLSで保護。
alter table leads            enable row level security;
alter table lead_notes       enable row level security;
alter table meetings         enable row level security;
alter table scheduled_emails enable row level security;
do $$
declare t text;
begin
  foreach t in array array['leads','lead_notes','meetings','scheduled_emails'] loop
    execute format('drop policy if exists operator_only on %I;', t);
    execute format('create policy operator_only on %I using (is_super_admin()) with check (is_super_admin());', t);
  end loop;
end $$;

-- =============================================================
-- 追加マイグレーション（既存DBにも安全に適用）
-- =============================================================
-- 通話タグ（VIP・要注意 等）。配列で保持し GIN インデックスで絞り込み。
alter table calls add column if not exists tags text[] not null default '{}';
create index if not exists idx_calls_tags on calls using gin (tags);

-- FAQ表示順（管理画面で並べ替え）。
alter table faqs add column if not exists sort_order integer not null default 0;
create index if not exists idx_faqs_tenant_order on faqs(tenant_id, sort_order);

-- 決済（Square）連携用。テナントごとの Square 顧客/サブスクID。
alter table tenants add column if not exists square_customer_id text;
alter table tenants add column if not exists square_subscription_id text;

-- =============================================================
-- caller_rules  (発信者番号ごとのルール：ブロック/専用アナウンス)
-- =============================================================
do $$ begin
  create type caller_action as enum ('block','greeting');
exception when duplicate_object then null; end $$;

create table if not exists caller_rules (
  id           uuid primary key default gen_random_uuid(),
  tenant_id    uuid not null references tenants(id) on delete cascade,
  phone_number text not null,                 -- 発信者番号（E.164推奨）
  action       caller_action not null,        -- block=着信拒否/専用文言、greeting=専用挨拶
  message      text,                          -- 読み上げる文言（任意）
  label        text,                          -- 管理用ラベル（例:クレーマー/VIP）
  created_at   timestamptz not null default now(),
  unique (tenant_id, phone_number)
);
create index if not exists idx_caller_rules_lookup on caller_rules(tenant_id, phone_number);
alter table caller_rules enable row level security;
drop policy if exists tenant_isolation on caller_rules;
create policy tenant_isolation on caller_rules
  using (is_super_admin() or tenant_id = current_tenant_id())
  with check (is_super_admin() or tenant_id = current_tenant_id());
