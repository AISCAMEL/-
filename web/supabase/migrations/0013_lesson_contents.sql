-- ============================================================
-- IWASAWA SURF BASE — 0013 レッスン有料コンテンツの分離（ペイウォール強化）
-- ------------------------------------------------------------
-- 背景：lessons は「メタ情報は誰でも閲覧可」なRLS。だが RLS は行単位で
-- 列を絞れないため、video_url / body（有料の動画・マニュアル本文）まで
-- 匿名キーで SELECT できてしまい、アプリ層のペイウォールを迂回できた。
--
-- 対策：有料コンテンツ（body / video_url）を lesson_contents に分離し、
-- 「無料プレビュー or プレミアム会員 or staff」だけが読めるRLSで守る。
-- これで権限を UI＋API＋RLS の三重化に戻す（将来のネイティブアプリも同じ）。
-- ============================================================

-- プレミアム会員判定ヘルパー（is_staff と同じ書式）
create or replace function public.is_premium()
returns boolean language sql stable security definer set search_path = public as $$
  select coalesce(
    (select plan = 'premium' from public.members where id = auth.uid()),
    false
  );
$$;

-- 有料コンテンツ用テーブル（lessons と 1:1）
create table public.lesson_contents (
  lesson_id  uuid primary key references public.lessons (id) on delete cascade,
  body       text,   -- マニュアル（テキスト解説）
  video_url  text,   -- 動画URL（埋め込み）
  updated_at timestamptz not null default now()
);

create trigger lesson_contents_set_updated_at
  before update on public.lesson_contents
  for each row execute function public.set_updated_at();

-- 既存データを移送してから、公開読取テーブルの列を落とす
insert into public.lesson_contents (lesson_id, body, video_url)
  select id, body, video_url from public.lessons;

alter table public.lessons drop column body;
alter table public.lessons drop column video_url;

alter table public.lesson_contents enable row level security;

-- 読取：無料プレビュー or プレミアム or staff のみ。かつ公開中コースに限る。
create policy "free preview or premium or staff can read lesson content"
  on public.lesson_contents for select
  using (
    public.is_staff()
    or exists (
      select 1
      from public.lessons l
      join public.courses c on c.id = l.course_id
      where l.id = lesson_id
        and c.status = 'active'
        and (l.is_free or public.is_premium())
    )
  );

-- 編集は staff のみ
create policy "staff can write lesson content"
  on public.lesson_contents for all to authenticated
  using (public.is_staff()) with check (public.is_staff());
