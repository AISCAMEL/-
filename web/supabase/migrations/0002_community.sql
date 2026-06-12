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
