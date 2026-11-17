-- =====================================================================
-- 合同会社アイズ 会計 ― Supabase バックエンド スキーマ
-- Supabase の SQL Editor に貼り付けて実行してください（1回だけ）。
-- ・ledgers … 会社の帳簿（全データのスナップショットを jsonb で保持）
-- ・ledger_members … だれがどの台帳にアクセスできるか（ユーザー単位の保護）
-- RLS（行レベルセキュリティ）で、所属メンバーだけが読み書きできます。
-- =====================================================================

create extension if not exists pgcrypto;

create table if not exists public.ledgers (
  id uuid primary key default gen_random_uuid(),
  name text not null default '会計帳簿',
  version integer not null default 0,
  data jsonb not null default '{}'::jsonb,
  updated_at timestamptz not null default now(),
  created_by uuid not null default auth.uid()
);

create table if not exists public.ledger_members (
  ledger_id uuid references public.ledgers(id) on delete cascade,
  user_id uuid references auth.users(id) on delete cascade,
  role text not null default 'member',
  primary key (ledger_id, user_id)
);

alter table public.ledgers enable row level security;
alter table public.ledger_members enable row level security;

-- メンバー判定（RLSの再帰を避けるため security definer）
create or replace function public.is_member(l uuid) returns boolean
  language sql security definer stable set search_path = public as $$
  select exists(select 1 from public.ledger_members m where m.ledger_id = l and m.user_id = auth.uid());
$$;
-- オーナー判定（招待・削除などの管理操作に使用）
create or replace function public.is_owner(l uuid) returns boolean
  language sql security definer stable set search_path = public as $$
  select exists(select 1 from public.ledger_members m where m.ledger_id = l and m.user_id = auth.uid() and m.role = 'owner');
$$;

-- 台帳：所属メンバーのみ参照・更新可
drop policy if exists ledgers_select on public.ledgers;
create policy ledgers_select on public.ledgers for select using (public.is_member(id));
drop policy if exists ledgers_update on public.ledgers;
create policy ledgers_update on public.ledgers for update using (public.is_member(id)) with check (public.is_member(id));

-- メンバー表：自分に関係する行のみ参照可
drop policy if exists members_select on public.ledger_members;
create policy members_select on public.ledger_members for select using (user_id = auth.uid() or public.is_member(ledger_id));

-- 台帳作成（作成者を owner として自動登録）
create or replace function public.create_ledger(p_name text) returns uuid
  language plpgsql security definer set search_path = public as $$
declare new_id uuid;
begin
  insert into public.ledgers(name) values (coalesce(nullif(p_name,''),'会計帳簿')) returning id into new_id;
  insert into public.ledger_members(ledger_id, user_id, role) values (new_id, auth.uid(), 'owner');
  return new_id;
end; $$;

-- メンバー招待（★オーナーのみ★が、登録済みユーザーを email で追加）
create or replace function public.add_member(p_ledger uuid, p_email text) returns void
  language plpgsql security definer set search_path = public as $$
declare uid uuid;
begin
  if not public.is_owner(p_ledger) then raise exception 'only an owner can invite members'; end if;
  select id into uid from auth.users where lower(email) = lower(p_email);
  if uid is null then raise exception 'user not found: %', p_email; end if;
  insert into public.ledger_members(ledger_id, user_id, role) values (p_ledger, uid, 'member') on conflict do nothing;
end; $$;

-- メンバー削除（★オーナーのみ★。最後のオーナーは削除不可）
create or replace function public.remove_member(p_ledger uuid, p_email text) returns void
  language plpgsql security definer set search_path = public as $$
declare uid uuid; owners int;
begin
  if not public.is_owner(p_ledger) then raise exception 'only an owner can remove members'; end if;
  select id into uid from auth.users where lower(email) = lower(p_email);
  if uid is null then raise exception 'user not found: %', p_email; end if;
  select count(*) into owners from public.ledger_members where ledger_id = p_ledger and role = 'owner';
  if owners <= 1 and exists(select 1 from public.ledger_members where ledger_id = p_ledger and user_id = uid and role = 'owner') then
    raise exception 'cannot remove the last owner'; end if;
  delete from public.ledger_members where ledger_id = p_ledger and user_id = uid;
end; $$;

-- 自分で台帳から退出（最後のオーナーは退出不可）
create or replace function public.leave_ledger(p_ledger uuid) returns void
  language plpgsql security definer set search_path = public as $$
declare owners int;
begin
  if not public.is_member(p_ledger) then return; end if;
  select count(*) into owners from public.ledger_members where ledger_id = p_ledger and role = 'owner';
  if owners <= 1 and public.is_owner(p_ledger) then raise exception 'the last owner cannot leave; delete the ledger or assign another owner'; end if;
  delete from public.ledger_members where ledger_id = p_ledger and user_id = auth.uid();
end; $$;

-- 台帳の削除（★オーナーのみ★）
create or replace function public.delete_ledger(p_ledger uuid) returns void
  language plpgsql security definer set search_path = public as $$
begin
  if not public.is_owner(p_ledger) then raise exception 'only an owner can delete the ledger'; end if;
  delete from public.ledgers where id = p_ledger;
end; $$;

-- 権限付与（メンバー表への直接 insert/update/delete はRLSで既定拒否。管理はRPC経由のみ）
grant execute on function public.create_ledger(text) to authenticated;
grant execute on function public.add_member(uuid, text) to authenticated;
grant execute on function public.remove_member(uuid, text) to authenticated;
grant execute on function public.leave_ledger(uuid) to authenticated;
grant execute on function public.delete_ledger(uuid) to authenticated;
grant execute on function public.is_member(uuid) to authenticated;
grant execute on function public.is_owner(uuid) to authenticated;

-- 自分が所属する台帳の一覧（UI用）
create or replace function public.my_ledgers() returns table(id uuid, name text, version integer, updated_at timestamptz)
  language sql security definer set search_path = public as $$
  select l.id, l.name, l.version, l.updated_at
  from public.ledgers l
  join public.ledger_members m on m.ledger_id = l.id and m.user_id = auth.uid()
  order by l.updated_at desc;
$$;
grant execute on function public.my_ledgers() to authenticated;
