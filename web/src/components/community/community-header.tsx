import Link from "next/link";
import { Brand } from "@/components/brand";
import { getCurrentMember } from "@/lib/auth";
import { MAIN_NAV } from "@/lib/nav";

/** コミュニティ共通ヘッダー。LINE CTA は強い導線として常設（仕様書 セクション5） */
export async function CommunityHeader() {
  const member = await getCurrentMember();
  return (
    <header className="sticky top-0 z-10 border-b border-navy/10 bg-white/90 backdrop-blur">
      <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
        <Brand />
        <nav className="flex items-center gap-2 text-sm">
          {MAIN_NAV.map((l) => (
            <Link key={l.href} href={l.href} className="hidden text-navy/70 hover:text-ocean lg:inline">
              {l.label}
            </Link>
          ))}
          {member ? (
            <>
              <Link
                href="/community/new"
                className="rounded-lg bg-ocean px-3 py-1.5 font-medium text-foam transition hover:bg-navy"
              >
                投稿する
              </Link>
              <Link href="/me" className="text-navy/70 hover:text-ocean">
                マイページ
              </Link>
            </>
          ) : (
            <>
              <Link href="/login" className="text-navy/70 hover:text-ocean">
                ログイン
              </Link>
              <Link
                href="/signup"
                className="rounded-lg bg-ocean px-3 py-1.5 font-medium text-foam transition hover:bg-navy"
              >
                仲間になる
              </Link>
            </>
          )}
        </nav>
      </div>
    </header>
  );
}
