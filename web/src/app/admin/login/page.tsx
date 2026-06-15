import { Suspense } from "react";
import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { getCurrentMember } from "@/lib/auth";
import { AuthShell } from "@/components/auth/auth-shell";
import { LoginForm } from "@/components/auth/login-form";

export const metadata: Metadata = { title: "運営ログイン｜IWASAWA SURF BASE" };

export default async function AdminLoginPage() {
  const member = await getCurrentMember();
  // すでに staff/admin ならダッシュボードへ
  if (member && (member.role === "staff" || member.role === "admin")) {
    redirect("/admin");
  }

  return (
    <AuthShell
      title="運営ログイン"
      subtitle="運営メンバー（Staff / Admin）専用の入口です。"
      footer={
        member ? (
          <p className="text-sm text-navy/60">
            現在ログイン中ですが、運営権限がありません。権限の付与は管理者にご依頼ください。
          </p>
        ) : null
      }
    >
      <Suspense fallback={null}>
        <LoginForm />
      </Suspense>
    </AuthShell>
  );
}
