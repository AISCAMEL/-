import { redirect } from "next/navigation";
import type { Metadata } from "next";
import Link from "next/link";
import { getCurrentMember } from "@/lib/auth";
import { PostForm } from "@/components/community/post-form";

export const metadata: Metadata = {
  title: "投稿する｜IWASAWA SURF BASE",
};

export default async function NewPostPage() {
  // API層のゲート（proxy でも保護しているが二重で確認）
  const member = await getCurrentMember();
  if (!member) redirect("/login?next=/community/new");

  return (
    <main className="mx-auto max-w-2xl px-4 py-8">
      <Link href="/community" className="text-sm text-ocean hover:underline">
        ← コミュニティへ
      </Link>
      <h1 className="mt-4 text-2xl font-semibold text-navy">投稿する</h1>
      <p className="mt-1 text-sm text-navy/60">
        できる人が、これからの人へ。あなたの一言が、誰かの海の入口になります。
      </p>
      <div className="mt-8">
        <PostForm role={member.role} />
      </div>
    </main>
  );
}
