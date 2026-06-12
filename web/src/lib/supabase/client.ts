import { createBrowserClient } from "@supabase/ssr";

/**
 * ブラウザ（クライアントコンポーネント）用 Supabase クライアント。
 * 環境変数が未設定でもビルドが落ちないよう、空文字フォールバックを置く。
 * （未設定時は認証操作でエラーになる＝セットアップ未完了のサイン）
 */
export function createClient() {
  return createBrowserClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL ?? "",
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY ?? "",
  );
}

/** Supabase の接続情報が設定済みかどうか。UI でセットアップ案内を出すのに使う。 */
export function isSupabaseConfigured() {
  return Boolean(
    process.env.NEXT_PUBLIC_SUPABASE_URL &&
      process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY,
  );
}
