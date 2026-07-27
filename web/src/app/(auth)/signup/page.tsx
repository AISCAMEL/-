import type { Metadata } from "next";
import Link from "next/link";
import { AuthShell } from "@/components/auth/auth-shell";
import { SignupForm } from "@/components/auth/signup-form";

export const metadata: Metadata = { title: "新規登録｜IWASAWA SURF BASE" };

export default function SignupPage() {
  return (
    <AuthShell
      title="仲間になる"
      subtitle="続きは、仲間になってから。30秒で登録できます。"
      footer={
        <p>
          すでにアカウントをお持ちの方は{" "}
          <Link href="/login" className="font-medium text-ocean hover:underline">
            ログイン
          </Link>
        </p>
      }
    >
      <SignupForm />
    </AuthShell>
  );
}
