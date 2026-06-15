import { redirect } from "next/navigation";
import type { Metadata } from "next";
import Link from "next/link";
import { getCurrentMember } from "@/lib/auth";
import { SkillForm } from "@/components/skills/skill-form";

export const metadata: Metadata = {
  title: "スキルを出品する｜IWASAWA SURF BASE",
};

export default async function NewSkillPage() {
  const member = await getCurrentMember();
  if (!member) redirect("/login?next=/skills/new");

  return (
    <main className="mx-auto max-w-2xl px-4 py-8">
      <Link href="/skills" className="text-sm text-ocean hover:underline">
        ← スキル掲示板へ
      </Link>
      <h1 className="mt-4 text-2xl font-semibold text-navy">スキルを出品する</h1>
      <p className="mt-1 text-sm text-navy/60">
        あなたの「できる」を、これからの人へ。
      </p>
      <div className="mt-8">
        <SkillForm />
      </div>
    </main>
  );
}
