-- ============================================================
-- IWASAWA SURF BASE — 統合スキーマ（丸ごとコピー → SQL Editor → Run）
-- 0001会員/0002コミュニティ/0003スキル/0004運営/0005スクール/0006画像/0007問い合わせ/0008広告
-- ※ 0006 の前に Storage バケット 'avatars'(Public) を作成してください
-- ============================================================

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


-- ============================================================
-- IWASAWA SURF BASE — 0002 コミュニティ（posts / comments / likes）
-- 仕様書 v2.0 / セクション5-D・6
-- ============================================================

create type post_category as enum ('waves', 'experiences', 'questions', 'events', 'gear');
create type post_status   as enum ('draft', 'published', 'hidden');
create type comment_status as enum ('published', 'hidden');

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

create table public.likes (
  id         uuid primary key default gen_random_uuid(),
  member_id  uuid not null references public.members (id) on delete cascade,
  post_id    uuid not null references public.posts (id) on delete cascade,
  created_at timestamptz not null default now(),
  unique (member_id, post_id)
);

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

alter table public.posts enable row level security;
alter table public.comments enable row level security;
alter table public.likes enable row level security;

create policy "anyone can read published posts"
  on public.posts for select
  using (status = 'published' or author_id = auth.uid() or public.is_staff());

create policy "members can create posts"
  on public.posts for insert
  to authenticated
  with check (
    author_id = auth.uid()
    and (category <> 'waves' or public.current_member_role() in ('local', 'staff', 'admin'))
  );

create policy "author or staff can update posts"
  on public.posts for update
  to authenticated
  using (author_id = auth.uid() or public.is_staff());

create policy "author or staff can delete posts"
  on public.posts for delete
  to authenticated
  using (author_id = auth.uid() or public.is_staff());

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

create table public.skills (
  id          uuid primary key default gen_random_uuid(),
  owner_id    uuid not null references public.members (id) on delete cascade,
  category    skill_category not null default 'other',
  title       text not null,
  description text not null,
  price       integer,               -- 将来の決済用。MVP は null（断定価格表現を避ける）
  area        text,
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

create table public.skill_applications (
  id           uuid primary key default gen_random_uuid(),
  skill_id     uuid not null references public.skills (id) on delete cascade,
  applicant_id uuid not null references public.members (id) on delete cascade,
  message      text,
  status       skill_app_status not null default 'applied',
  order_id     uuid,                 -- 将来 skill_orders と紐づける予約列
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now(),
  unique (skill_id, applicant_id)
);
create index skill_apps_skill_idx on public.skill_applications (skill_id, created_at desc);
create index skill_apps_applicant_idx on public.skill_applications (applicant_id, created_at desc);

create trigger skill_apps_set_updated_at
  before update on public.skill_applications
  for each row execute function public.set_updated_at();

alter table public.skills enable row level security;
alter table public.skill_applications enable row level security;

create policy "anyone can read open skills"
  on public.skills for select
  using (status = 'open' or owner_id = auth.uid() or public.is_staff());

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

create policy "involved can read applications"
  on public.skill_applications for select
  to authenticated
  using (
    applicant_id = auth.uid()
    or public.is_staff()
    or exists (select 1 from public.skills s where s.id = skill_id and s.owner_id = auth.uid())
  );

create policy "members can apply"
  on public.skill_applications for insert
  to authenticated
  with check (
    applicant_id = auth.uid()
    and not exists (select 1 from public.skills s where s.id = skill_id and s.owner_id = auth.uid())
  );

create policy "involved can update applications"
  on public.skill_applications for update
  to authenticated
  using (
    applicant_id = auth.uid()
    or public.is_staff()
    or exists (select 1 from public.skills s where s.id = skill_id and s.owner_id = auth.uid())
  );


-- ============================================================
-- IWASAWA SURF BASE — 0004 運営（reports / admin_audit_logs）
-- 仕様書 v2.0 / セクション6・7
-- ============================================================

create type report_target as enum ('post', 'comment', 'skill');
create type report_status as enum ('open', 'reviewing', 'resolved', 'dismissed');

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

create table public.admin_audit_logs (
  id          uuid primary key default gen_random_uuid(),
  actor_id    uuid references public.members (id) on delete set null,
  action      text not null,
  target_type text,
  target_id   uuid,
  meta        jsonb,
  created_at  timestamptz not null default now()
);
create index audit_logs_created_idx on public.admin_audit_logs (created_at desc);

alter table public.reports enable row level security;
alter table public.admin_audit_logs enable row level security;

create policy "members can create reports"
  on public.reports for insert
  to authenticated
  with check (reporter_id = auth.uid() or reporter_id is null);

create policy "staff can read reports"
  on public.reports for select
  to authenticated
  using (public.is_staff());

create policy "staff can update reports"
  on public.reports for update
  to authenticated
  using (public.is_staff());

create policy "staff can read audit logs"
  on public.admin_audit_logs for select
  to authenticated
  using (public.is_staff());

create policy "staff can insert audit logs"
  on public.admin_audit_logs for insert
  to authenticated
  with check (public.is_staff() and actor_id = auth.uid());


-- ============================================================
-- IWASAWA SURF BASE — 0005 サーフィンスクール
-- プロ講師プロフィール / 口コミ・評価 / プロのブログ / 月額＋手数料(仕込み)
-- ============================================================

create type instructor_rank   as enum ('pro', 'top_amateur', 'instructor');
create type review_target      as enum ('skill', 'instructor');
create type article_status     as enum ('draft', 'published');
create type subscription_status as enum ('active', 'canceled', 'past_due');

-- 講師プロフィール（member に付与）--------------------------------
create table public.instructor_profiles (
  member_id    uuid primary key references public.members (id) on delete cascade,
  rank         instructor_rank not null default 'instructor',
  headline     text,                 -- キャッチコピー
  bio          text,
  achievements text,                 -- 主な実績（大会成績など）
  home_break   text,                 -- ホームの海
  years        integer,              -- 経験年数
  monthly_price integer,             -- 月額の目安（円・将来の課金用）
  is_featured  boolean not null default false,
  accepting    boolean not null default true,  -- 受付中か
  rating_avg   numeric(3,2) not null default 0, -- 評価キャッシュ
  rating_count integer not null default 0,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
create index instructor_rank_idx on public.instructor_profiles (rank, is_featured desc, rating_avg desc);

create trigger instructor_set_updated_at
  before update on public.instructor_profiles
  for each row execute function public.set_updated_at();

-- 口コミ・評価 ---------------------------------------------------
create table public.reviews (
  id          uuid primary key default gen_random_uuid(),
  target_type review_target not null,
  target_id   uuid not null,         -- skill.id または members.id(講師)
  author_id   uuid not null references public.members (id) on delete cascade,
  rating      integer not null check (rating between 1 and 5),
  body        text,
  created_at  timestamptz not null default now(),
  unique (target_type, target_id, author_id)
);
create index reviews_target_idx on public.reviews (target_type, target_id, created_at desc);

-- 講師の評価キャッシュを自動更新
create or replace function public.sync_instructor_rating()
returns trigger language plpgsql as $$
declare
  tgt uuid;
begin
  tgt := coalesce(new.target_id, old.target_id);
  if coalesce(new.target_type, old.target_type) = 'instructor' then
    update public.instructor_profiles ip
    set rating_count = sub.cnt, rating_avg = sub.avg
    from (
      select count(*) cnt, coalesce(round(avg(rating), 2), 0) avg
      from public.reviews
      where target_type = 'instructor' and target_id = tgt
    ) sub
    where ip.member_id = tgt;
  end if;
  return null;
end;
$$;

create trigger reviews_sync_instructor
  after insert or update or delete on public.reviews
  for each row execute function public.sync_instructor_rating();

-- プロのブログ／コラム -------------------------------------------
create table public.articles (
  id           uuid primary key default gen_random_uuid(),
  author_id    uuid not null references public.members (id) on delete cascade,
  title        text not null,
  body         text not null,
  cover_url    text,
  status       article_status not null default 'published',
  view_count   integer not null default 0,
  created_at   timestamptz not null default now(),
  published_at timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
create index articles_status_idx on public.articles (status, published_at desc);

create trigger articles_set_updated_at
  before update on public.articles
  for each row execute function public.set_updated_at();

-- サブスク（月額＋手数料）— 仕込み（処理は将来 PSP で）-----------
create table public.subscriptions (
  id              uuid primary key default gen_random_uuid(),
  subscriber_id   uuid not null references public.members (id) on delete cascade,
  instructor_id   uuid references public.members (id) on delete set null, -- どのプロに付くか
  monthly_price   integer not null,           -- 月額（円）
  commission_rate numeric(4,3) not null default 0.200, -- 運営手数料率（例 20%）
  psp_ref         text,                        -- 決済参照（自社保持しない）
  status          subscription_status not null default 'active',
  started_at      timestamptz not null default now(),
  current_period_end timestamptz,
  created_at      timestamptz not null default now(),
  updated_at      timestamptz not null default now()
);
create index subscriptions_sub_idx on public.subscriptions (subscriber_id, status);

create trigger subscriptions_set_updated_at
  before update on public.subscriptions
  for each row execute function public.set_updated_at();

-- ============================================================
-- RLS
-- ============================================================
alter table public.instructor_profiles enable row level security;
alter table public.reviews enable row level security;
alter table public.articles enable row level security;
alter table public.subscriptions enable row level security;

-- 講師プロフィールは誰でも閲覧可。編集は本人か staff（付与は staff）
create policy "anyone can read instructors"
  on public.instructor_profiles for select using (true);
create policy "owner or staff can update instructor"
  on public.instructor_profiles for update to authenticated
  using (member_id = auth.uid() or public.is_staff());
create policy "staff can insert instructor"
  on public.instructor_profiles for insert to authenticated
  with check (public.is_staff());
create policy "staff can delete instructor"
  on public.instructor_profiles for delete to authenticated
  using (public.is_staff());

-- 口コミは誰でも閲覧可。投稿は認証済み（自分名義・自分自身は評価不可）
create policy "anyone can read reviews"
  on public.reviews for select using (true);
create policy "members can write review"
  on public.reviews for insert to authenticated
  with check (author_id = auth.uid() and not (target_type = 'instructor' and target_id = auth.uid()));
create policy "author or staff can update review"
  on public.reviews for update to authenticated
  using (author_id = auth.uid() or public.is_staff());
create policy "author or staff can delete review"
  on public.reviews for delete to authenticated
  using (author_id = auth.uid() or public.is_staff());

-- ブログは公開分は誰でも。編集は著者か staff
create policy "anyone can read published articles"
  on public.articles for select
  using (status = 'published' or author_id = auth.uid() or public.is_staff());
create policy "author can write article"
  on public.articles for insert to authenticated
  with check (author_id = auth.uid());
create policy "author or staff can update article"
  on public.articles for update to authenticated
  using (author_id = auth.uid() or public.is_staff());

-- サブスクは本人か staff のみ
create policy "owner or staff can read subscriptions"
  on public.subscriptions for select to authenticated
  using (subscriber_id = auth.uid() or instructor_id = auth.uid() or public.is_staff());
create policy "member can create subscription"
  on public.subscriptions for insert to authenticated
  with check (subscriber_id = auth.uid());


-- ============================================================
-- IWASAWA SURF BASE — 0006 画像アップロード（アバター）
-- ※ 事前に Supabase Dashboard で Storage バケット "avatars" を
--   「Public bucket」で作成してください（このSQLはポリシーのみ）。
-- ============================================================

-- 誰でも閲覧可（公開バケット）
create policy "avatar public read"
  on storage.objects for select
  using (bucket_id = 'avatars');

-- 本人フォルダ（avatars/<uid>/...）にのみアップロード可
create policy "avatar owner upload"
  on storage.objects for insert to authenticated
  with check (
    bucket_id = 'avatars'
    and (storage.foldername(name))[1] = auth.uid()::text
  );

create policy "avatar owner update"
  on storage.objects for update to authenticated
  using (
    bucket_id = 'avatars'
    and (storage.foldername(name))[1] = auth.uid()::text
  );

create policy "avatar owner delete"
  on storage.objects for delete to authenticated
  using (
    bucket_id = 'avatars'
    and (storage.foldername(name))[1] = auth.uid()::text
  );


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


-- ============================================================
-- IWASAWA SURF BASE — 0008 広告枠（ad_banners）
-- 業者バナー掲載でスポンサー収入を得る枠。運営が掲載を管理。
-- ============================================================

create table public.ad_banners (
  id          uuid primary key default gen_random_uuid(),
  advertiser  text not null,           -- 広告主（業者名）
  title       text,
  subtitle    text,
  image_url   text not null,           -- バナー画像
  href        text not null,           -- リンク先
  placement   text not null default 'feed', -- feed / waves / blog / all
  is_active   boolean not null default true,
  sort_order  integer not null default 0,
  starts_at   timestamptz,
  ends_at     timestamptz,
  impressions integer not null default 0, -- 表示回数（将来のレポート用）
  clicks      integer not null default 0,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
create index ad_banners_placement_idx on public.ad_banners (placement, is_active, sort_order);

create trigger ad_banners_set_updated_at
  before update on public.ad_banners
  for each row execute function public.set_updated_at();

alter table public.ad_banners enable row level security;

-- 掲載中の広告は誰でも閲覧可
create policy "anyone can read active ads"
  on public.ad_banners for select
  using (is_active = true or public.is_staff());

-- 作成・編集・削除は staff のみ
create policy "staff can insert ads"
  on public.ad_banners for insert to authenticated with check (public.is_staff());
create policy "staff can update ads"
  on public.ad_banners for update to authenticated using (public.is_staff());
create policy "staff can delete ads"
  on public.ad_banners for delete to authenticated using (public.is_staff());


