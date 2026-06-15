-- ============================================================
-- IWASAWA SURF BASE — 0003 スキル掲示板（skills / skill_applications）
-- 仕様書 v2.0 / セクション5-B・6
-- MVP は「掲示板＋連絡」まで（無料）。決済・手数料は将来 skill_orders で。
-- ============================================================

create type skill_category   as enum ('school', 'repair', 'photo', 'guide', 'other');
create type skill_status     as enum ('open', 'closed', 'hidden');
create type skill_app_status as enum ('applied', 'accepted', 'declined', 'done');

-- skills（出品）-----------------------------------------------
create table public.skills (
  id          uuid primary key default gen_random_uuid(),
  owner_id    uuid not null references public.members (id) on delete cascade,
  category    skill_category not null default 'other',
  title       text not null,
  description text not null,
  -- price は将来の決済用。MVP は null（即納・断定価格表現を避ける運用）
  price       integer,
  area        text,                       -- 提供エリア・場所（例：岩沢海岸）
  status      skill_status not null default 'open',
  is_featured boolean not null default false,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
create index skills_category_idx on public.skills (category, created_at desc);
create index skills_status_idx on public.skills (status, created_at desc);

create trigger skills_set_updated_at
  before update on public.skills
  for each row execute function public.set_updated_at();

-- skill_applications（申込／連絡）-----------------------------
create table public.skill_applications (
  id           uuid primary key default gen_random_uuid(),
  skill_id     uuid not null references public.skills (id) on delete cascade,
  applicant_id uuid not null references public.members (id) on delete cascade,
  message      text,
  status       skill_app_status not null default 'applied',
  -- 将来、決済（skill_orders）と紐づけるための予約列
  order_id     uuid,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now(),
  unique (skill_id, applicant_id)
);
create index skill_apps_skill_idx on public.skill_applications (skill_id, created_at desc);
create index skill_apps_applicant_idx on public.skill_applications (applicant_id, created_at desc);

create trigger skill_apps_set_updated_at
  before update on public.skill_applications
  for each row execute function public.set_updated_at();

-- ============================================================
-- RLS
-- ============================================================
alter table public.skills enable row level security;
alter table public.skill_applications enable row level security;

-- skills: 公開中(open)は誰でも閲覧可。本人/staff は全状態を閲覧可
create policy "anyone can read open skills"
  on public.skills for select
  using (status = 'open' or owner_id = auth.uid() or public.is_staff());

-- skills: 出品は認証済み（自分名義）。beginner 以上
create policy "members can create skills"
  on public.skills for insert
  to authenticated
  with check (
    owner_id = auth.uid()
    and public.current_member_role() in ('beginner', 'local', 'staff', 'admin')
  );

create policy "owner or staff can update skills"
  on public.skills for update
  to authenticated
  using (owner_id = auth.uid() or public.is_staff());

create policy "owner or staff can delete skills"
  on public.skills for delete
  to authenticated
  using (owner_id = auth.uid() or public.is_staff());

-- skill_applications: 申込者・出品者・staff のみ閲覧可
create policy "involved can read applications"
  on public.skill_applications for select
  to authenticated
  using (
    applicant_id = auth.uid()
    or public.is_staff()
    or exists (
      select 1 from public.skills s
      where s.id = skill_id and s.owner_id = auth.uid()
    )
  );

-- 申込は認証済み（自分名義）。自分の出品には申し込めない
create policy "members can apply"
  on public.skill_applications for insert
  to authenticated
  with check (
    applicant_id = auth.uid()
    and not exists (
      select 1 from public.skills s
      where s.id = skill_id and s.owner_id = auth.uid()
    )
  );

-- 申込者は取り下げ、出品者/staff は受付状態の更新が可能
create policy "involved can update applications"
  on public.skill_applications for update
  to authenticated
  using (
    applicant_id = auth.uid()
    or public.is_staff()
    or exists (
      select 1 from public.skills s
      where s.id = skill_id and s.owner_id = auth.uid()
    )
  );
