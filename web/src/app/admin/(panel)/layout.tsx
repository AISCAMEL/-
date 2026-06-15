import Link from "next/link";
import { requireStaff } from "@/lib/admin";
import { AdminNav } from "@/components/admin/admin-nav";
import { SignOutButton } from "@/components/auth/sign-out-button";

export default async function PanelLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const member = await requireStaff();

  return (
    <div className="flex min-h-screen bg-slate-100 text-slate-800">
      {/* サイドナビ（実務的・装飾控えめ） */}
      <aside className="hidden w-56 shrink-0 flex-col border-r border-slate-200 bg-white md:flex">
        <div className="border-b border-slate-200 px-5 py-4">
          <p className="text-xs tracking-widest text-slate-400">IWASAWA</p>
          <p className="text-sm font-semibold text-slate-700">運営管理</p>
        </div>
        <AdminNav variant="side" />
        <div className="border-t border-slate-200 p-3">
          <p className="px-2 pb-2 text-xs text-slate-400">
            {member.display_name}（{member.role}）
          </p>
          <Link href="/" className="block px-2 py-1 text-xs text-slate-500 hover:text-slate-800">
            ← サイトを見る
          </Link>
        </div>
      </aside>

      <div className="flex flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 md:hidden">
          <span className="text-sm font-semibold">運営管理</span>
          <SignOutButton />
        </header>
        <AdminNav variant="top" />
        <main className="flex-1 p-4 md:p-8">{children}</main>
      </div>
    </div>
  );
}
