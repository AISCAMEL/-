-- ============================================================
-- IWASAWA SURF BASE — 0015 セキュリティ強化（列レベルの保護）
-- RLS は「どの行を触れるか」しか制御しないため、本人が自分の行を更新するときに
-- 権限・プラン・評価などの列まで書き換えられてしまう。ここでトリガーで防ぐ。
-- auth.uid() が null（SQL Editor・サーバー側のサービスロール）の操作は対象外。
-- ============================================================

-- 運営が非表示にした投稿かどうか（本人が勝手に再公開できないように）
alter table public.posts add column if not exists moderated boolean not null default false;

-- ---- members：本人は 種別・プラン・状態・メール を変えられない ----
create or replace function public.guard_member_update()
returns trigger language plpgsql as $$
begin
  if auth.uid() is null then
    return new;
  end if;
  if not public.is_staff() then
    if new.id is distinct from old.id
       or new.role is distinct from old.role
       or new.plan is distinct from old.plan
       or new.status is distinct from old.status
       or new.email is distinct from old.email then
      raise exception 'この項目は変更できません' using errcode = '42501';
    end if;
  elsif new.role = 'admin' and old.role is distinct from 'admin'
        and public.current_member_role() <> 'admin' then
    -- 管理者（admin）にできるのは admin だけ
    raise exception '管理者の付与は admin のみ可能です' using errcode = '42501';
  end if;
  return new;
end;
$$;

drop trigger if exists members_guard_update on public.members;
create trigger members_guard_update
  before update on public.members
  for each row execute function public.guard_member_update();

-- ---- instructor_profiles：本人はランク・評価・おすすめ表示を変えられない ----
create or replace function public.guard_instructor_update()
returns trigger language plpgsql as $$
begin
  if auth.uid() is null or public.is_staff() then
    return new;
  end if;
  if new.member_id is distinct from old.member_id
     or new.rank is distinct from old.rank
     or new.rating_avg is distinct from old.rating_avg
     or new.rating_count is distinct from old.rating_count
     or new.is_featured is distinct from old.is_featured then
    raise exception 'この項目は変更できません' using errcode = '42501';
  end if;
  return new;
end;
$$;

drop trigger if exists instructor_profiles_guard_update on public.instructor_profiles;
create trigger instructor_profiles_guard_update
  before update on public.instructor_profiles
  for each row execute function public.guard_instructor_update();

-- ---- posts：作者は いいね数・閲覧数・おすすめ・作者 を変えられず、
--      運営が非表示にした投稿は再公開できない ----
create or replace function public.guard_post_update()
returns trigger language plpgsql as $$
begin
  if auth.uid() is null or public.is_staff() then
    return new;
  end if;
  if new.author_id is distinct from old.author_id
     or new.like_count is distinct from old.like_count
     or new.view_count is distinct from old.view_count
     or new.is_featured is distinct from old.is_featured
     or new.moderated is distinct from old.moderated then
    raise exception 'この項目は変更できません' using errcode = '42501';
  end if;
  if old.moderated and new.status is distinct from old.status then
    raise exception '運営が非表示にした投稿は変更できません' using errcode = '42501';
  end if;
  return new;
end;
$$;

drop trigger if exists posts_guard_update on public.posts;
create trigger posts_guard_update
  before update on public.posts
  for each row execute function public.guard_post_update();

-- 投稿の新規作成時も、いいね数などは初期値に固定
create or replace function public.guard_post_insert()
returns trigger language plpgsql as $$
begin
  if auth.uid() is not null and not public.is_staff() then
    new.like_count := 0;
    new.view_count := 0;
    new.is_featured := false;
    new.moderated := false;
  end if;
  return new;
end;
$$;

drop trigger if exists posts_guard_insert on public.posts;
create trigger posts_guard_insert
  before insert on public.posts
  for each row execute function public.guard_post_insert();

-- ---- skill_applications：申込者は自分で「承認」「完了」にできない ----
create or replace function public.guard_skill_application_update()
returns trigger language plpgsql as $$
declare
  is_owner boolean;
begin
  if auth.uid() is null or public.is_staff() then
    return new;
  end if;
  select exists (select 1 from public.skills s where s.id = old.skill_id and s.owner_id = auth.uid())
    into is_owner;
  if new.skill_id is distinct from old.skill_id or new.applicant_id is distinct from old.applicant_id then
    raise exception 'この項目は変更できません' using errcode = '42501';
  end if;
  if not is_owner and new.status is distinct from old.status and new.status in ('accepted', 'done') then
    raise exception '承認・完了は出品者のみ可能です' using errcode = '42501';
  end if;
  return new;
end;
$$;

drop trigger if exists skill_applications_guard_update on public.skill_applications;
create trigger skill_applications_guard_update
  before update on public.skill_applications
  for each row execute function public.guard_skill_application_update();

-- ---- subscriptions：会員が自分で「有効な契約」を作れないように ----
-- 契約は決済（PSP）の確認後にサーバー側（サービスロール）で作成する。
drop policy if exists "member can create subscription" on public.subscriptions;

-- ---- orders：一般の注文は必ず「受付」状態から始まる（支払済みの偽装を防ぐ） ----
create or replace function public.guard_order_insert()
returns trigger language plpgsql as $$
begin
  if not public.is_staff() then
    new.status := 'pending';
    new.psp_ref := null;
  end if;
  return new;
end;
$$;

drop trigger if exists orders_guard_insert on public.orders;
create trigger orders_guard_insert
  before insert on public.orders
  for each row execute function public.guard_order_insert();
