-- ============================================================
-- IWASAWA SURF BASE — 0009 オンラインショップ（products / orders）
-- 無在庫・受注起点型。即納表現禁止・納期は具体・PSP前提（カード情報は自社非保持）。
-- ============================================================

create type product_category as enum ('board', 'wetsuit', 'accessory', 'apparel');
create type product_status   as enum ('active', 'draft', 'soldout_paused');
create type order_status      as enum (
  'pending',        -- 注文受付（未確定）
  'stock_check',    -- 仕入先在庫確認中
  'confirmed',      -- 受注確定（在庫確保後）
  'shipped',        -- 発送済み
  'delivered',      -- お届け完了
  'cancelled',      -- キャンセル
  'refunded'        -- 返金
);

create table public.products (
  id             uuid primary key default gen_random_uuid(),
  name           text not null,
  description    text,
  category       product_category not null default 'accessory',
  price          integer not null,          -- 税込価格（円）
  shipping_fee   integer not null default 0,
  -- 無在庫のため「即納」ではなく具体的な納期文言を必須運用にする
  lead_time_text text not null default '受注確定後 3〜7営業日で発送',
  image_url      text,
  status         product_status not null default 'active',
  is_featured    boolean not null default false,
  sort_order     integer not null default 0,
  created_at     timestamptz not null default now(),
  updated_at     timestamptz not null default now()
);
create index products_status_idx on public.products (status, sort_order);

create trigger products_set_updated_at
  before update on public.products
  for each row execute function public.set_updated_at();

create table public.orders (
  id            uuid primary key default gen_random_uuid(),
  buyer_id      uuid references public.members (id) on delete set null,
  -- ゲスト注文にも対応（連絡先を保持）
  contact_name  text not null,
  contact_email text not null,
  shipping_addr text,
  items         jsonb not null,            -- [{product_id, name, price, qty}]
  subtotal      integer not null,
  shipping_fee  integer not null default 0,
  total         integer not null,
  status        order_status not null default 'pending',
  psp_ref       text,                       -- 決済参照（カード情報は保持しない）
  note          text,
  created_at    timestamptz not null default now(),
  updated_at    timestamptz not null default now()
);
create index orders_status_idx on public.orders (status, created_at desc);
create index orders_buyer_idx on public.orders (buyer_id, created_at desc);

create trigger orders_set_updated_at
  before update on public.orders
  for each row execute function public.set_updated_at();

alter table public.products enable row level security;
alter table public.orders enable row level security;

-- 商品：公開中は誰でも閲覧可。編集は staff
create policy "anyone can read active products"
  on public.products for select
  using (status = 'active' or public.is_staff());
create policy "staff can insert products"
  on public.products for insert to authenticated with check (public.is_staff());
create policy "staff can update products"
  on public.products for update to authenticated using (public.is_staff());
create policy "staff can delete products"
  on public.products for delete to authenticated using (public.is_staff());

-- 注文：作成は誰でも可（ゲスト購入）。閲覧・更新は本人か staff
create policy "anyone can create order"
  on public.orders for insert to anon, authenticated with check (true);
create policy "owner or staff can read orders"
  on public.orders for select to authenticated
  using (buyer_id = auth.uid() or public.is_staff());
create policy "staff can update orders"
  on public.orders for update to authenticated using (public.is_staff());
