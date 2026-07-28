-- ============================================================
-- IWASAWA SURF BASE — 0012 仕入れ先 → BASE 自動掲載
-- 仕入れ先と仕入れ商品を登録し、BASE（オンラインショップ本体）へ出品する。
-- 楽天/Yahoo!/Amazon などの販売チャネル情報も持たせる（多店舗展開の土台）。
-- ============================================================

create type base_publish_status as enum ('draft', 'published', 'error');

create table public.suppliers (
  id         uuid primary key default gen_random_uuid(),
  name       text not null,
  contact    text,                      -- 連絡先・担当
  note       text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger suppliers_set_updated_at
  before update on public.suppliers
  for each row execute function public.set_updated_at();

create table public.supplier_products (
  id           uuid primary key default gen_random_uuid(),
  supplier_id  uuid references public.suppliers (id) on delete set null,
  name         text not null,
  description  text,
  category     text,
  cost         integer,                 -- 仕入値（円）
  price        integer not null,        -- 販売価格（円・BASE掲載価格）
  image_url    text,
  -- BASE 連携
  base_item_id text,                    -- BASE の item_id（掲載後に格納）
  base_status  base_publish_status not null default 'draft',
  base_message text,                    -- 直近の掲載結果メッセージ
  published_at timestamptz,
  -- 多店舗（掲載したい販売チャネル）
  channels     text[] not null default array['base'],
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
create index supplier_products_status_idx on public.supplier_products (base_status, created_at desc);

create trigger supplier_products_set_updated_at
  before update on public.supplier_products
  for each row execute function public.set_updated_at();

alter table public.suppliers enable row level security;
alter table public.supplier_products enable row level security;

-- 仕入れ・掲載情報は運営(staff)のみ
create policy "staff manage suppliers"
  on public.suppliers for all to authenticated
  using (public.is_staff()) with check (public.is_staff());
create policy "staff manage supplier_products"
  on public.supplier_products for all to authenticated
  using (public.is_staff()) with check (public.is_staff());
