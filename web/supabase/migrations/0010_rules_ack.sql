-- ============================================================
-- IWASAWA SURF BASE — 0010 海のルール学習の記録
-- 「海に入る前に」の5つの約束を確認した日時を会員に記録する。
-- ============================================================

alter table public.members
  add column if not exists rules_ack_at timestamptz;

comment on column public.members.rules_ack_at is '海のルール・マナー学習を完了した日時（修了バッジ用）';
