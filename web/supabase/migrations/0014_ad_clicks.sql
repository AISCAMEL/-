-- ============================================================
-- IWASAWA SURF BASE — 0014 広告クリック計測
-- 掲載中の広告のクリック数を +1 して、リンク先URLを返す。
-- 誰でも呼べるが、更新できるのは clicks だけ（security definer）。
-- ============================================================

create or replace function public.register_ad_click(ad_id uuid)
returns text
language sql
security definer
set search_path = public
as $$
  update public.ad_banners
     set clicks = clicks + 1
   where id = ad_id
     and is_active = true
     and (starts_at is null or starts_at <= now())
     and (ends_at is null or ends_at > now())
  returning href;
$$;

grant execute on function public.register_ad_click(uuid) to anon, authenticated;
