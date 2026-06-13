import Link from "next/link";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import {
  CATEGORIES,
  GUEST_VISIBLE_POSTS,
  isCategory,
} from "@/lib/community";
import { PostCard, type PostSummary } from "@/components/community/post-card";
import { WaveWidget } from "@/components/waves/wave-widget";

export const metadata: Metadata = {
  title: "コミュニティ｜IWASAWA SURF BASE",
};

type Props = {
  searchParams: Promise<{ category?: string }>;
};

export default async function CommunityFeed({ searchParams }: Props) {
  const { category } = await searchParams;
  const activeCategory = category && isCategory(category) ? category : null;

  const member = await getCurrentMember();
  const isGuest = !member;

  const supabase = await createClient();
  let query = supabase
    .from("posts")
    .select(
      "id, category, title, body, like_count, created_at, author:members!posts_author_id_fkey(display_name, role)",
    )
    .eq("status", "published")
    .order("created_at", { ascending: false })
    .limit(30);

  if (activeCategory) query = query.eq("category", activeCategory);

  const { data, error } = await query;
  const posts = (data ?? []) as unknown as PostSummary[];

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      <div className="rounded-2xl bg-ocean-gradient p-6 text-foam">
        <h1 className="text-2xl font-semibold">岩沢の、今日。</h1>
        <p className="mt-1 text-sm text-sand/90">
          波・体験・質問・イベント。海の入口で、ゆるくつながる場所。
        </p>
      </div>

      {/* 波情報ウィジェット（WV-01） */}
      <div className="mt-6">
        <WaveWidget />
      </div>

      {/* カテゴリフィルター */}
      <nav className="mt-6 flex flex-wrap gap-2">
        <FilterChip label="すべて" href="/community" active={!activeCategory} />
        {CATEGORIES.map((c) => (
          <FilterChip
            key={c.key}
            label={c.label}
            href={`/community?category=${c.key}`}
            active={activeCategory === c.key}
          />
        ))}
      </nav>

      {/* フィード */}
      <div className="mt-6 space-y-4">
        {error ? (
          <p className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            読み込みに失敗しました。Supabase の設定（SETUP.md）をご確認ください。
          </p>
        ) : posts.length === 0 ? (
          <EmptyState />
        ) : (
          posts.map((post, i) => {
            const blurred = isGuest && i >= GUEST_VISIBLE_POSTS;
            return <PostCard key={post.id} post={post} blurred={blurred} />;
          })
        )}

        {/* ゲストには続きを登録で開放するCTA */}
        {isGuest && posts.length > GUEST_VISIBLE_POSTS ? (
          <div className="rounded-2xl border border-ocean/20 bg-white p-6 text-center">
            <p className="text-sm text-navy/70">
              ここから先は、メンバーの声です。
            </p>
            <Link
              href="/signup"
              className="mt-3 inline-block rounded-full bg-ocean px-6 py-2.5 font-medium text-foam transition hover:bg-navy"
            >
              登録して、続きを読む 🌊
            </Link>
          </div>
        ) : null}
      </div>
    </main>
  );
}

function FilterChip({
  label,
  href,
  active,
}: {
  label: string;
  href: string;
  active: boolean;
}) {
  return (
    <Link
      href={href}
      className={`rounded-full border px-3.5 py-1.5 text-sm transition ${
        active
          ? "border-ocean bg-ocean text-foam"
          : "border-navy/15 bg-white text-navy/70 hover:border-ocean"
      }`}
    >
      {label}
    </Link>
  );
}

function EmptyState() {
  return (
    <div className="rounded-2xl border border-dashed border-navy/20 bg-white p-10 text-center">
      <p className="text-sm text-navy/60">
        まだ投稿がありません。最初の波を、あなたから。
      </p>
    </div>
  );
}
