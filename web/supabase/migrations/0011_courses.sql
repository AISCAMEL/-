-- ============================================================
-- IWASAWA SURF BASE — 0011 オンライン講座（courses / lessons）
-- マニュアル（テキスト解説）＋動画レッスン。プレミアム（サブスク）で全編視聴。
-- 無料プレビュー lesson は誰でも視聴可。
-- ============================================================

create type course_level  as enum ('prep', 'beginner', 'intermediate');
create type course_status as enum ('active', 'draft');

create table public.courses (
  id          uuid primary key default gen_random_uuid(),
  title       text not null,
  description text,
  level       course_level not null default 'beginner',
  cover_url   text,
  status      course_status not null default 'active',
  sort_order  integer not null default 0,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
create index courses_status_idx on public.courses (status, sort_order);

create trigger courses_set_updated_at
  before update on public.courses
  for each row execute function public.set_updated_at();

create table public.lessons (
  id          uuid primary key default gen_random_uuid(),
  course_id   uuid not null references public.courses (id) on delete cascade,
  title       text not null,
  body        text,                       -- マニュアル（テキスト解説）
  video_url   text,                       -- 動画URL（埋め込み）
  is_free     boolean not null default false,  -- 無料プレビューか
  duration_min integer,                   -- 目安分数
  sort_order  integer not null default 0,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
create index lessons_course_idx on public.lessons (course_id, sort_order);

create trigger lessons_set_updated_at
  before update on public.lessons
  for each row execute function public.set_updated_at();

alter table public.courses enable row level security;
alter table public.lessons enable row level security;

-- 講座は公開中を誰でも閲覧可。編集は staff
create policy "anyone can read active courses"
  on public.courses for select
  using (status = 'active' or public.is_staff());
create policy "staff can write courses"
  on public.courses for all to authenticated
  using (public.is_staff()) with check (public.is_staff());

-- レッスン：一覧（メタ情報）は誰でも取得可。本文/動画のアクセス制御はアプリ層で
-- （無料プレビュー or プレミアム会員のみ本文・動画を表示）
create policy "anyone can read lessons meta"
  on public.lessons for select
  using (
    exists (select 1 from public.courses c where c.id = course_id and c.status = 'active')
    or public.is_staff()
  );
create policy "staff can write lessons"
  on public.lessons for all to authenticated
  using (public.is_staff()) with check (public.is_staff());
