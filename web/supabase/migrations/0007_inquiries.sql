-- ============================================================
-- IWASAWA SURF BASE — 0007 お問い合わせ（inquiries）
-- ============================================================

create table public.inquiries (
  id         uuid primary key default gen_random_uuid(),
  name       text not null,
  email      text not null,
  category   text,
  body       text not null,
  status     text not null default 'open',   -- open / handled
  created_at timestamptz not null default now()
);
create index inquiries_created_idx on public.inquiries (created_at desc);

alter table public.inquiries enable row level security;

-- 誰でも（未ログインでも）送信可能
create policy "anyone can send inquiry"
  on public.inquiries for insert
  to anon, authenticated
  with check (true);

-- 閲覧・更新は staff のみ
create policy "staff can read inquiries"
  on public.inquiries for select
  to authenticated
  using (public.is_staff());

create policy "staff can update inquiries"
  on public.inquiries for update
  to authenticated
  using (public.is_staff());
