import Link from "next/link";
import { Brand } from "@/components/brand";
import { getCurrentMember } from "@/lib/auth";

/** コミュニティ共通ヘッダー。LINE CTA は強い導線として常設（仕様書 セクション5） */
export async function CommunityHeader() {
  const member = await getCurrentMember();
  return (
    <header className="sticky top-0 z-10 border-b border-navy/10 bg-white/90 backdrop-blur">
      <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
        <Brand href="/community" />
        <nav className="flex items-center gap-2 text-sm">
          <Link href="/instructors" className="hidden text-navy/70 hover:text-ocean sm:inline">
            スクール
          </Link>
          <Link href="/courses" className="hidden text-navy/70 hover:text-ocean sm:inline">
            講座
          </Link>
          <Link href="/rules" className="hidden text-navy/70 hover:text-ocean sm:inline">
            海のルール
          </Link>
          <Link href="/articles" className="hidden text-navy/70 hover:text-ocean sm:inline">
            ブログ
          </Link>
          <Link href="/waves" className="hidden text-navy/70 hover:text-ocean sm:inline">
            波情報
          </Link>
          <Link href="/spots" className="hidden text-navy/70 hover:text-ocean sm:inline">
            全国ポイント
          </Link>
          <Link href="/partners" className="hidden text-navy/70 hover:text-ocean sm:inline">
            岩沢まわり
          </Link>
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
