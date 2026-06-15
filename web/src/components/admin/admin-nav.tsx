"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const NAV = [
  { href: "/admin", label: "ダッシュボード" },
  { href: "/admin/posts", label: "投稿管理" },
  { href: "/admin/members", label: "会員管理" },
  { href: "/admin/reports", label: "通報・要確認" },
];

export function AdminNav({ variant }: { variant: "side" | "top" }) {
  const pathname = usePathname();

  if (variant === "top") {
    return (
      <nav className="flex gap-1 overflow-x-auto border-b border-slate-200 bg-white px-3 py-2 text-sm md:hidden">
        {NAV.map((n) => (
          <Link
            key={n.href}
            href={n.href}
            className={`whitespace-nowrap rounded-md px-3 py-1.5 ${
              pathname === n.href
                ? "bg-slate-100 font-medium text-slate-900"
                : "text-slate-600 hover:bg-slate-100"
            }`}
          >
            {n.label}
          </Link>
        ))}
      </nav>
    );
  }

  return (
    <nav className="flex-1 space-y-1 p-3 text-sm">
      {NAV.map((n) => (
        <Link
          key={n.href}
          href={n.href}
          className={`block rounded-md px-3 py-2 hover:bg-slate-100 ${
            pathname === n.href
              ? "bg-slate-100 font-medium text-slate-900"
              : "text-slate-600"
          }`}
        >
          {n.label}
        </Link>
      ))}
    </nav>
  );
}
