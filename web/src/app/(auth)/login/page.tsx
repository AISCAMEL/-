import { Suspense } from "react";
import type { Metadata } from "next";
import Link from "next/link";
import { AuthShell } from "@/components/auth/auth-shell";
import { LoginForm } from "@/components/auth/login-form";

export const metadata: Metadata = { title: "ログイン｜IWASAWA SURF BASE" };

export default function LoginPage() {
  return (
    <AuthShell
      title="おかえりなさい"
      subtitle="メンバーとして、岩沢の今日につながる。"
      footer={
        <p>
          はじめての方は{" "}
          <Link href="/signup" className="font-medium text-ocean hover:underline">
            新規登録
          </Link>
        </p>
      }
    >
      <Suspense fallback={null}>
        <LoginForm />
      </Suspense>
    </AuthShell>
  );
}
