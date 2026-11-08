-- ============================================================
-- IWASAWA SURF BASE — 0016 アバター画像のバケット作成
-- 0006 はポリシーのみだったため、公開バケット "avatars" をSQLで作成する
-- （ダッシュボードでの手作業を不要にする）。2MBまでの画像のみ。
-- ============================================================

insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('avatars', 'avatars', true, 2097152, array['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
on conflict (id) do update
  set public = excluded.public,
      file_size_limit = excluded.file_size_limit,
      allowed_mime_types = excluded.allowed_mime_types;
