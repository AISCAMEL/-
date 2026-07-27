-- ============================================================
-- IWASAWA SURF BASE — 0001 会員基盤（members）
-- 会員 = 種別(role) × プラン(plan) の2軸（仕様書 v2.0 / セクション3）
-- ============================================================

create type member_role as enum ('visitor', 'beginner', 'local', 'staff', 'admin');
create type member_plan as enum ('free', 'premium');
create type member_status as enum ('active', 'suspended');

create table public.members (
  id            uuid primary key references auth.users (id) on delete cascade,
  email         text unique not null,
  handle        text unique,
  display_name  text,
  bio           text,
  avatar_url    text,
  role          member_role   not null default 'beginner',
  plan          member_plan   not null default 'free',
  status        member_status not null default 'active',
  line_user_id  text,
  home_area     text,
  last_login_at timestamptz,
  created_at    timestamptz not null default now(),
  updated_at    timestamptz not null default now()
);

comment on table public.members is 'IWASAWA SURF BASE 会員。role=役割 / plan=課金プラン の2軸で管理';

create or replace function public.set_updated_at()
returns trigger language plpgsql as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create trigger members_set_updated_at
  before update on public.members
  for each row execute function public.set_updated_at();

-- 新規ユーザー登録時に members 行を自動作成（既定 role=beginner / plan=free）
create or replace function public.handle_new_user()
returns trigger language plpgsql security definer set search_path = public as $$
begin
  insert into public.members (id, email, display_name)
  values (
    new.id,
    new.email,
    coalesce(new.raw_user_meta_data ->> 'display_name', split_part(new.email, '@', 1))
  )
  on conflict (id) do nothing;
  return new;
end;
$$;

create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();

-- 権限ヘルパー（SECURITY DEFINER で RLS を回避し、自己参照の無限再帰を防ぐ）
create or replace function public.current_member_role()
returns member_role language sql stable security definer set search_path = public as $$
  select role from public.members where id = auth.uid();
$$;

create or replace function public.is_staff()
returns boolean language sql stable security definer set search_path = public as $$
  select coalesce(
    (select role in ('staff', 'admin') from public.members where id = auth.uid()),
    false
  );
$$;

alter table public.members enable row level security;

create policy "authenticated can read profiles"
  on public.members for select
  to authenticated
  using (true);

create policy "members can update own profile"
  on public.members for update
  to authenticated
  using (auth.uid() = id)
  with check (auth.uid() = id);

create policy "staff can update all members"
  on public.members for update
  to authenticated
  using (public.is_staff());
