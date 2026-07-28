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
