import type { Metadata } from "next";
import Link from "next/link";
import { AuthShell } from "@/components/auth/auth-shell";
import { ResetForm } from "@/components/auth/reset-form";

export const metadata: Metadata = {
  title: "パスワード再設定｜IWASAWA SURF BASE",
};

export default function ResetPage() {
  return (
    <AuthShell
      title="パスワード再設定"
      subtitle="登録メールアドレスに、再設定リンクをお送りします。"
      footer={
        <p>
          <Link href="/login" className="font-medium text-ocean hover:underline">
            ← ログインに戻る
          </Link>
        </p>
      }
    >
      <ResetForm />
    </AuthShell>
  );
}
