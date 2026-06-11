-- =============================================================
-- AIオペレーター24  デモ用シードデータ
-- 実行: psql "$DATABASE_URL" -f db/seed.sql
-- 既存データを汚さないよう固定 UUID を使用（再実行は ON CONFLICT で冪等）。
-- =============================================================

-- デモテナント
insert into tenants (id, company_name, industry, plan, status, billing_email, phone)
values ('00000000-0000-0000-0000-000000000001',
        'デモ美容室 AISALON', '美容室', 'business', 'trial',
        'demo@example.com', '+815000000000')
on conflict (id) do nothing;

-- テナント設定
insert into tenant_settings (
  tenant_id, business_hours, holiday_settings, greeting_message, ai_tone,
  recording_enabled, human_transfer_enabled, transfer_phone_number, notification_email,
  fallback_message)
values (
  '00000000-0000-0000-0000-000000000001',
  '{"mon":[["10:00","18:00"]],"tue":[["10:00","18:00"]],"wed":[["10:00","18:00"]],"thu":[["10:00","18:00"]],"fri":[["10:00","18:00"]]}'::jsonb,
  '{"weekly":["sat","sun"],"dates":[]}'::jsonb,
  'お電話ありがとうございます。AI受付です。ご用件をお話しください。',
  'polite', false, true, '+815011112222', 'owner@example.com',
  '申し訳ありません。担当者より折り返しご連絡いたします。')
on conflict (tenant_id) do nothing;

-- デモ番号
insert into phone_numbers (tenant_id, phone_number, type, status, assigned_at)
values ('00000000-0000-0000-0000-000000000001', '+815099998888', 'demo', 'active', now())
on conflict (phone_number) do nothing;

-- 管理ユーザ（super_admin と テナント owner）
insert into app_users (id, tenant_id, name, email, role)
values
  ('00000000-0000-0000-0000-0000000000a1', null,
   'システム管理者', 'admin@ai-operator24.com', 'super_admin'),
  ('00000000-0000-0000-0000-0000000000a2', '00000000-0000-0000-0000-000000000001',
   'デモ店長', 'owner@example.com', 'owner')
on conflict (id) do nothing;

-- FAQ
insert into faqs (tenant_id, question, answer, category, keywords) values
  ('00000000-0000-0000-0000-000000000001',
   '営業時間を教えてください', '営業時間は、平日10時から18時までです。土日祝日はお休みです。',
   '営業案内', array['営業時間','何時','開店','閉店']),
  ('00000000-0000-0000-0000-000000000001',
   '駐車場はありますか', '近隣のコインパーキングをご利用ください。提携駐車場はございません。',
   '営業案内', array['駐車場','車','パーキング']),
  ('00000000-0000-0000-0000-000000000001',
   'カットの料金はいくらですか', 'カットは4,400円からとなっております。詳細は担当者よりご案内いたします。',
   '料金', array['料金','値段','いくら','カット'])
on conflict do nothing;
