-- ============================================================
-- IWASAWA SURF BASE — 0004 運営（reports / admin_audit_logs）
-- 仕様書 v2.0 / セクション6・7
-- ============================================================

create type report_target as enum ('post', 'comment', 'skill');
create type report_status as enum ('open', 'reviewing', 'resolved', 'dismissed');

-- reports（通報／要確認）--------------------------------------
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

-- admin_audit_logs（操作ログ・最小）---------------------------
create table public.admin_audit_logs (
  id          uuid primary key default gen_random_uuid(),
  actor_id    uuid references public.members (id) on delete set null,
  action      text not null,             -- 例: post.hide / member.suspend
  target_type text,
  target_id   uuid,
  meta        jsonb,
  created_at  timestamptz not null default now()
);
create index audit_logs_created_idx on public.admin_audit_logs (created_at desc);

-- ============================================================
-- RLS
-- ============================================================
alter table public.reports enable row level security;
alter table public.admin_audit_logs enable row level security;

-- 通報の作成は認証済みなら誰でも（自分名義 or 匿名reporter）
create policy "members can create reports"
  on public.reports for insert
  to authenticated
  with check (reporter_id = auth.uid() or reporter_id is null);

-- 閲覧・対応は staff のみ
create policy "staff can read reports"
  on public.reports for select
  to authenticated
  using (public.is_staff());

create policy "staff can update reports"
  on public.reports for update
  to authenticated
  using (public.is_staff());

-- 監査ログは staff のみ閲覧・記録
create policy "staff can read audit logs"
  on public.admin_audit_logs for select
  to authenticated
  using (public.is_staff());

create policy "staff can insert audit logs"
  on public.admin_audit_logs for insert
  to authenticated
  with check (public.is_staff() and actor_id = auth.uid());
