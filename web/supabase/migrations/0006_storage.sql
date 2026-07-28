-- ============================================================
-- IWASAWA SURF BASE — 0006 画像アップロード（アバター）
-- ※ 事前に Supabase Dashboard で Storage バケット "avatars" を
--   「Public bucket」で作成してください（このSQLはポリシーのみ）。
-- ============================================================

-- 誰でも閲覧可（公開バケット）
create policy "avatar public read"
  on storage.objects for select
  using (bucket_id = 'avatars');

-- 本人フォルダ（avatars/<uid>/...）にのみアップロード可
create policy "avatar owner upload"
  on storage.objects for insert to authenticated
  with check (
    bucket_id = 'avatars'
    and (storage.foldername(name))[1] = auth.uid()::text
  );

create policy "avatar owner update"
  on storage.objects for update to authenticated
  using (
    bucket_id = 'avatars'
    and (storage.foldername(name))[1] = auth.uid()::text
  );

create policy "avatar owner delete"
  on storage.objects for delete to authenticated
  using (
    bucket_id = 'avatars'
    and (storage.foldername(name))[1] = auth.uid()::text
  );
