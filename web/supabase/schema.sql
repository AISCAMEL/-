-- ============================================================
-- IWASAWA SURF BASE — 統合スキーマ（このファイルを丸ごとコピーして
-- Supabase の SQL Editor に貼り付け → Run すれば全テーブルが作られます）
-- 内訳: 0001 会員 / 0002 コミュニティ / 0003 スキル / 0004 運営
-- ============================================================

-- ============================================================
-- IWASAWA SURF BASE — 0001 会員基盤（members）
-- 会員 = 種別(role) × プラン(plan) の2軸（仕様書 v2.0 / セクション3）
-- ============================================================

-- 列挙型 ------------------------------------------------------
create type member_role as enum ('visitor', 'beginner', 'local', 'staff', 'admin');
create type member_plan as enum ('free', 'premium');
create type member_status as enum ('active', 'suspended');

-- members -----------------------------------------------------
-- auth.users と 1:1。id は auth.users.id をそのまま使う。
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

-- updated_at 自動更新 -----------------------------------------
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

-- 新規ユーザー登録時に members 行を自動作成 -------------------
-- 既定は role=beginner / plan=free（仕様書: 登録時の既定値）
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
-- ※ ポリシー内で members を直接参照すると無限再帰になるため、必ずこの関数を使う。
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

-- RLS（UI + API + RLS の三重化の「DB層」）---------------------
alter table public.members enable row level security;

-- 認証済みは会員プロフィールを参照可能（ぼかし等のUI制御はアプリ層）
create policy "authenticated can read profiles"
  on public.members for select
  to authenticated
  using (true);

-- 自分のプロフィールのみ更新可能
-- （role / plan / status の本人変更は次フェーズで管理用 RPC に限定する）
create policy "members can update own profile"
  on public.members for update
  to authenticated
  using (auth.uid() = id)
  with check (auth.uid() = id);

-- staff / admin は全会員を更新可能（管理画面用）。再帰回避のため is_staff() を使用
create policy "staff can update all members"
  on public.members for update
  to authenticated
  using (public.is_staff());


-- ============================================================
-- IWASAWA SURF BASE — 0002 コミュニティ（posts / comments / likes）
-- 仕様書 v2.0 / セクション5-D・6
-- ============================================================

create type post_category as enum ('waves', 'experiences', 'questions', 'events', 'gear');
create type post_status   as enum ('draft', 'published', 'hidden');
create type comment_status as enum ('published', 'hidden');

-- posts -------------------------------------------------------
create table public.posts (
  id          uuid primary key default gen_random_uuid(),
  author_id   uuid not null references public.members (id) on delete cascade,
  category    post_category not null,
  title       text,
  body        text not null,
  status      post_status not null default 'published',
  is_featured boolean not null default false,
  view_count  integer not null default 0,
  like_count  integer not null default 0,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
create index posts_category_idx on public.posts (category, created_at desc);
create index posts_status_idx on public.posts (status, created_at desc);

create trigger posts_set_updated_at
  before update on public.posts
  for each row execute function public.set_updated_at();

-- comments ----------------------------------------------------
create table public.comments (
  id         uuid primary key default gen_random_uuid(),
  post_id    uuid not null references public.posts (id) on delete cascade,
  author_id  uuid not null references public.members (id) on delete cascade,
  body       text not null,
  status     comment_status not null default 'published',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index comments_post_idx on public.comments (post_id, created_at);

create trigger comments_set_updated_at
  before update on public.comments
  for each row execute function public.set_updated_at();

-- likes -------------------------------------------------------
create table public.likes (
  id         uuid primary key default gen_random_uuid(),
  member_id  uuid not null references public.members (id) on delete cascade,
  post_id    uuid not null references public.posts (id) on delete cascade,
  created_at timestamptz not null default now(),
  unique (member_id, post_id)
);

-- like_count を自動更新 ---------------------------------------
create or replace function public.sync_like_count()
returns trigger language plpgsql as $$
begin
  if tg_op = 'INSERT' then
    update public.posts set like_count = like_count + 1 where id = new.post_id;
  elsif tg_op = 'DELETE' then
    update public.posts set like_count = greatest(like_count - 1, 0) where id = old.post_id;
  end if;
  return null;
end;
$$;

create trigger likes_sync_count
  after insert or delete on public.likes
  for each row execute function public.sync_like_count();

-- ============================================================
-- RLS
-- ============================================================
alter table public.posts enable row level security;
alter table public.comments enable row level security;
alter table public.likes enable row level security;

-- posts: 公開済みは誰でも（ゲスト含む）参照可。下書き/非公開は本人かstaffのみ
create policy "anyone can read published posts"
  on public.posts for select
  using (status = 'published' or author_id = auth.uid() or public.is_staff());

-- posts: 投稿作成は認証済み（自分名義のみ）。waves は local 以上に限定
create policy "members can create posts"
  on public.posts for insert
  to authenticated
  with check (
    author_id = auth.uid()
    and (
      category <> 'waves'
      or public.current_member_role() in ('local', 'staff', 'admin')
    )
  );

-- posts: 編集・削除は本人かstaff
create policy "author or staff can update posts"
  on public.posts for update
  to authenticated
  using (author_id = auth.uid() or public.is_staff());

create policy "author or staff can delete posts"
  on public.posts for delete
  to authenticated
  using (author_id = auth.uid() or public.is_staff());

-- comments: 公開コメントは誰でも参照可
create policy "anyone can read published comments"
  on public.comments for select
  using (status = 'published' or author_id = auth.uid() or public.is_staff());

create policy "members can create comments"
  on public.comments for insert
  to authenticated
  with check (author_id = auth.uid());

create policy "author or staff can update comments"
  on public.comments for update
  to authenticated
  using (author_id = auth.uid() or public.is_staff());

create policy "author or staff can delete comments"
  on public.comments for delete
  to authenticated
  using (author_id = auth.uid() or public.is_staff());

-- likes: 集計のため誰でも参照可。作成/削除は本人のみ
create policy "anyone can read likes"
  on public.likes for select
  using (true);

create policy "members can like"
  on public.likes for insert
  to authenticated
  with check (member_id = auth.uid());

create policy "members can unlike"
  on public.likes for delete
  to authenticated
  using (member_id = auth.uid());


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


-- ============================================================
-- IWASAWA SURF BASE — 0004 運営（reports / admin_audit_logs）
-- 仕様書 v2.0 / セクション6・7
-- ============================================================

create type report_target as enum ('post', 'comment', 'skill');
create type report_status as enum ('open', 'reviewing', 'resolved', 'dismissed');

-- reports（通報／要確認）--------------------------------------
create table public.reports (
  id          uuid primary key default gen_random_uuid(),
  target_type report_target not null,
  target_id   uuid not null,
  reporter_id uuid references public.members (id) on delete set null,
  reason      text,
  status      report_status not null default 'open',
  handled_by  uuid references public.members (id) on delete set null,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
create index reports_status_idx on public.reports (status, created_at desc);

create trigger reports_set_updated_at
  before update on public.reports
  for each row execute function public.set_updated_at();

-- admin_audit_logs（操作ログ・最小）---------------------------
create table public.admin_audit_logs (
  id          uuid primary key default gen_random_uuid(),
  actor_id    uuid references public.members (id) on delete set null,
  action      text not null,             -- 例: post.hide / member.suspend
  target_type text,
  target_id   uuid,
  meta        jsonb,
  created_at  timestamptz not null default now()
);
create index audit_logs_created_idx on public.admin_audit_logs (created_at desc);

-- ============================================================
-- RLS
-- ============================================================
alter table public.reports enable row level security;
alter table public.admin_audit_logs enable row level security;

-- 通報の作成は認証済みなら誰でも（自分名義 or 匿名reporter）
create policy "members can create reports"
  on public.reports for insert
  to authenticated
  with check (reporter_id = auth.uid() or reporter_id is null);

-- 閲覧・対応は staff のみ
create policy "staff can read reports"
  on public.reports for select
  to authenticated
  using (public.is_staff());

create policy "staff can update reports"
  on public.reports for update
  to authenticated
  using (public.is_staff());

-- 監査ログは staff のみ閲覧・記録
create policy "staff can read audit logs"
  on public.admin_audit_logs for select
  to authenticated
  using (public.is_staff());

create policy "staff can insert audit logs"
  on public.admin_audit_logs for insert
  to authenticated
  with check (public.is_staff() and actor_id = auth.uid());
