import Link from "next/link";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { CommunityHeader } from "@/components/community/community-header";
import { VideoPlayer } from "@/components/courses/video-player";
import { PremiumCTA } from "@/components/courses/premium-cta";
import { DEMO, demoCourses } from "@/lib/demo";

export const metadata: Metadata = { title: "レッスン｜IWASAWA SURF BASE" };

type Props = { params: Promise<{ id: string }> };

type LessonView = {
  id: string;
  title: string;
  body: string | null;
  video_url: string | null;
  is_free: boolean;
  courseId: string;
  courseTitle: string;
  cover: string | null;
};

export default async function LessonPage({ params }: Props) {
  const { id } = await params;
  const member = await getCurrentMember();
  const isPremium = DEMO || member?.plan === "premium";

  let lesson: LessonView | null = null;
  if (DEMO) {
    for (const c of demoCourses) {
      const l = c.lessons.find((x) => x.id === id);
      if (l) {
        lesson = {
          id: l.id,
          title: l.title,
          body: l.body,
          video_url: null,
          is_free: l.is_free,
          courseId: c.id,
          courseTitle: c.title,
          cover: c.cover_url,
        };
        break;
      }
    }
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("lessons")
      .select("id, title, body, video_url, is_free, course:courses!inner(id, title, cover_url)")
      .eq("id", id)
      .single();
    if (data) {
      const d = data as unknown as {
        id: string;
        title: string;
        body: string | null;
        video_url: string | null;
        is_free: boolean;
        course: { id: string; title: string; cover_url: string | null } | null;
      };
      lesson = {
        id: d.id,
        title: d.title,
        body: d.body,
        video_url: d.video_url,
        is_free: d.is_free,
        courseId: d.course?.id ?? "",
        courseTitle: d.course?.title ?? "",
        cover: d.course?.cover_url ?? null,
      };
    }
  }

  if (!lesson) notFound();
  const canView = lesson.is_free || isPremium;

  return (
    <div className="min-h-screen bg-foam">
      <CommunityHeader />
      <main className="mx-auto max-w-2xl px-4 py-8">
        <Link href={`/courses/${lesson.courseId}`} className="text-sm text-ocean hover:underline">
          ← {lesson.courseTitle}
        </Link>
        <h1 className="mt-3 text-xl font-semibold text-navy">{lesson.title}</h1>

        {canView ? (
          <>
            <div className="mt-4">
              <VideoPlayer videoUrl={lesson.video_url} poster={lesson.cover} title={lesson.title} />
            </div>

            {/* マニュアル（テキスト解説） */}
            <section className="mt-6">
              <h2 className="text-sm font-semibold text-navy/70">📖 マニュアル</h2>
              <div className="mt-3 whitespace-pre-wrap rounded-2xl border border-navy/10 bg-white p-5 text-sm leading-relaxed text-navy/80">
                {lesson.body}
              </div>
            </section>

            {!isPremium && lesson.is_free ? (
              <div className="mt-6">
                <PremiumCTA />
              </div>
            ) : null}
          </>
        ) : (
          <div className="mt-6">
            {/* ロック時は動画をぼかし＋サブスク誘導 */}
            <div className="relative">
              <div className="pointer-events-none blur-sm">
                <VideoPlayer videoUrl={null} poster={lesson.cover} title={lesson.title} />
              </div>
              <div className="absolute inset-0 flex items-center justify-center">
                <span className="rounded-full bg-navy/70 px-4 py-2 text-sm text-foam">🔒 プレミアム限定</span>
              </div>
            </div>
            <div className="mt-6">
              <PremiumCTA />
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
