"use client";

import { useRouter } from "next/navigation";
import { createClient } from "@/lib/supabase/client";

export function SignOutButton() {
  const router = useRouter();
  async function signOut() {
    const supabase = createClient();
    await supabase.auth.signOut();
    router.push("/");
    router.refresh();
  }
  return (
    <button
      onClick={signOut}
      className="rounded-lg border border-navy/15 px-4 py-2 text-sm text-navy/70 transition hover:border-ocean hover:text-ocean"
    >
      ログアウト
    </button>
  );
}
