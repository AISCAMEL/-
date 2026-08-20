-- =====================================================================
-- AIオペレーター24 — minimal production seed
--
-- Purpose: a fresh production DB that can log in and take a call.
-- Creates ONE tenant, ONE owner user, a tenant_settings row, one active
-- phone number, and a couple of FAQs.
--
-- All values below are PLACEHOLDERS — replace before going live:
--   * the owner email / phone / transfer numbers
--   * the active phone_number (must be the real E.164 DID that Twilio
--     routes to this tenant; resolveTenantByPhone matches on it)
--   * business hours / greeting / booking URLs
-- No secrets are hardcoded (google_refresh_token / slack webhook are empty).
--
-- Idempotent: safe to re-run (on conflict do nothing / fixed UUID).
-- Run AFTER schema.sql.
-- =====================================================================

-- Fixed tenant id = config.demoTenantId, so demo-mode links keep working
-- when the same tenant is provisioned in DB mode.
insert into tenants (id, company_name, industry, plan, status, billing_email, payment_status)
values (
  '00000000-0000-0000-0000-000000000001',
  '車買取専門店',              -- TODO: replace with the real company name
  '車買取',
  'business',
  'trial',
  'owner@example.com',        -- TODO: real billing email
  'none'
)
on conflict (id) do nothing;

-- Owner user (the account that logs into the admin dashboard).
-- Auth is handled by Supabase JWT (see src/auth/jwt.ts); this row is the
-- app-side profile/role. Email must match the login identity.
insert into app_users (tenant_id, name, email, role, is_active)
values (
  '00000000-0000-0000-0000-000000000001',
  '店長（オーナー）',
  'owner@example.com',        -- TODO: real owner login email
  'owner',
  true
)
on conflict (tenant_id, email) do nothing;

-- Tenant settings (one row; upserted by the app on save).
insert into tenant_settings (
  tenant_id,
  business_hours,
  holiday_settings,
  greeting_message,
  ai_tone,
  default_language,
  recording_enabled,
  human_transfer_enabled,
  transfer_phone_number,
  notification_email,
  slack_webhook_url,
  notify_on_call_end,
  notify_on_callback,
  notify_on_transfer,
  fallback_message,
  google_calendar_id,
  google_refresh_token,
  appointment_duration_min,
  ai_instructions,
  reception_types,
  sales_call_reply,
  online_booking_url,
  appraisal_form_url
)
values (
  '00000000-0000-0000-0000-000000000001',
  '{"mon":[["10:00","19:00"]],"tue":[["10:00","19:00"]],"wed":[["10:00","19:00"]],"thu":[["10:00","19:00"]],"fri":[["10:00","19:00"]],"sat":[["10:00","17:00"]]}'::jsonb,
  '{"weekly":["sun"],"dates":[]}'::jsonb,
  'お電話ありがとうございます。車買取専門店、AI受付です。買取査定のご依頼を承ります。お車の車種・年式と、だいたいの地域を教えてください。',
  'polite',
  'ja-JP',
  false,
  true,
  '+815011112222',            -- TODO: real human-transfer phone number
  'owner@example.com',        -- TODO: real notification email
  '',                         -- Slack webhook (empty = off; set via env/UI)
  true,
  true,
  true,
  '申し訳ありません。担当者より折り返しご連絡いたします。',
  '',                         -- google_calendar_id (empty = internal booking only)
  '',                         -- google_refresh_token (NEVER hardcode; set via OAuth flow)
  45,
  '査定は基本「画像査定」で進める：お客様の携帯番号を確認し、査定フォームのURLをSMSでお送りする旨を伝える。ビデオ査定希望者にはオンライン査定を案内。査定額はお電話で確約しない。',
  '画像査定,オンライン査定,出張査定,持込査定',
  '恐れ入りますが、営業・勧誘のお電話はお取り次ぎしておりません。ご用件があれば会社名とお名前を伺い、担当者より折り返しご連絡いたします。',
  'https://example.com/online-booking',   -- TODO: real online booking URL
  'https://example.com/satei-form'        -- TODO: real appraisal form URL
)
on conflict (tenant_id) do nothing;

-- One active inbound number. resolveTenantByPhone matches on phone_number
-- + status='active', so this MUST be the real DID Twilio forwards here.
insert into phone_numbers (tenant_id, phone_number, type, status)
values (
  '00000000-0000-0000-0000-000000000001',
  '+815099998888',            -- TODO: real E.164 inbound number
  'twilio',
  'active'
)
on conflict (phone_number) do nothing;

-- A couple of starter FAQs so the AI can answer immediately.
-- faqs has no natural unique key, so guard on "tenant has no FAQs yet"
-- to keep this seed safe to re-run.
insert into faqs (tenant_id, question, answer, category, keywords, is_active, sort_order)
select v.tenant_id, v.question, v.answer, v.category, v.keywords, v.is_active, v.sort_order
from (values
  (
    '00000000-0000-0000-0000-000000000001'::uuid,
    '営業時間・対応エリアを教えてください',
    '受付は平日10時から19時、土曜10時から17時です。出張査定はエリアが限られるため、遠方のお客様には写真・ビデオでのオンライン査定を承っております。',
    '営業案内',
    array['営業時間','何時','エリア','出張'],
    true,
    1
  ),
  (
    '00000000-0000-0000-0000-000000000001'::uuid,
    '買取査定をお願いしたい',
    '無料査定を承ります。車種・年式・走行距離・おおよその状態と、お住まいの地域をお伺いします。基本はお写真を送っていただく画像査定でご案内し、ご希望の方には出張・持ち込み・オンライン査定も承ります。',
    '買取査定',
    array['売りたい','買取','査定'],
    true,
    2
  )
) as v(tenant_id, question, answer, category, keywords, is_active, sort_order)
where not exists (
  select 1 from faqs where tenant_id = '00000000-0000-0000-0000-000000000001'
);
