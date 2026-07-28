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
