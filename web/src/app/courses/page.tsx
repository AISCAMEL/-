/* eslint-disable @next/next/no-img-element */
import Link from "next/link";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { LEVEL_LABEL, LEVEL_BADGE, type CourseLevel } from "@/lib/courses";
import { DEMO, demoCourses } from "@/lib/demo";

export const metadata: Metadata = {
  title: "オンライン講座｜IWASAWA SURF BASE",
  description: "動画とマニュアルで学ぶサーフィン。来訪前の予習に。月額プレミアムで全編見放題。",
};

type Card = {
  id: string;
  title: string;
  description: string;
  level: CourseLevel;
  cover_url: string | null;
  lessonCount: number;
  freeCount: number;
};

export default async function CoursesPage() {
  const member = await getCurrentMember();
  const isPremium = DEMO || member?.plan === "premium";

  let cards: Card[] = [];
  if (DEMO) {
    cards = demoCourses.map((c) => ({
      id: c.id,
      title: c.title,
      description: c.description,
      level: c.level,
      cover_url: c.cover_url,
      lessonCount: c.lessons.length,
      freeCount: c.lessons.filter((l) => l.is_free).length,
    }));
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("courses")
      .select("id, title, description, level, cover_url, lessons(id, is_free)")
      .eq("status", "active")
      .order("sort_order", { ascending: true });
    cards = ((data ?? []) as unknown as {
      id: string;
      title: string;
      description: string;
      level: CourseLevel;
      cover_url: string | null;
      lessons: { id: string; is_free: boolean }[];
    }[]).map((c) => ({
      id: c.id,
      title: c.title,
      description: c.description,
      level: c.level,
      cover_url: c.cover_url,
      lessonCount: c.lessons?.length ?? 0,
      freeCount: c.lessons?.filter((l) => l.is_free).length ?? 0,
    }));
  }

  return (
    <main className="mx-auto max-w-3xl px-4 py-8">
      <div className="rounded-2xl bg-ocean-gradient p-6 text-foam">
        <p className="text-xs tracking-[0.3em] text-teal">ONLINE COURSE</p>
        <h1 className="mt-2 text-2xl font-semibold">動画とマニュアルで、家から学ぶ。</h1>
        <p className="mt-1 text-sm text-sand/90">
          海に入る前の予習に。ルール・パドリング・テイクオフを、プロがやさしく解説。
          {isPremium ? "" : "月額プレミアムで全編見放題。"}
        </p>
        {!isPremium ? (
          <Link
            href="/premium"
            className="mt-4 inline-block rounded-full bg-teal px-5 py-2 text-sm font-medium text-navy transition hover:bg-foam"
          >
            ✦ プレミアムを見る
          </Link>
        ) : null}
      </div>

      <div className="mt-6 space-y-4">
        {cards.map((c) => (
          <Link
            key={c.id}
            href={`/courses/${c.id}`}
            className="flex gap-4 overflow-hidden rounded-2xl border border-navy/10 bg-white p-3 shadow-sm transition hover:border-ocean/40"
          >
            {c.cover_url ? (
              <img src={c.cover_url} alt={c.title} className="h-24 w-36 shrink-0 rounded-xl object-cover" />
            ) : null}
            <div className="min-w-0 flex-1">
              <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${LEVEL_BADGE[c.level]}`}>
                {LEVEL_LABEL[c.level]}
              </span>
              <p className="mt-1 font-semibold text-navy line-clamp-1">{c.title}</p>
              <p className="mt-1 line-clamp-2 text-sm text-navy/60">{c.description}</p>
              <p className="mt-2 text-xs text-navy/40">
                全{c.lessonCount}レッスン・無料プレビュー{c.freeCount}本
              </p>
            </div>
          </Link>
        ))}
      </div>
    </main>
  );
}
