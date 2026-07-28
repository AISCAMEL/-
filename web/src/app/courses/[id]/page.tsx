/* eslint-disable @next/next/no-img-element */
import Link from "next/link";
import { notFound } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getCurrentMember } from "@/lib/auth";
import { LEVEL_LABEL, LEVEL_BADGE, type CourseLevel } from "@/lib/courses";
import { PremiumCTA } from "@/components/courses/premium-cta";
import { DEMO, demoCourses } from "@/lib/demo";

type Props = { params: Promise<{ id: string }> };

type Lesson = { id: string; title: string; is_free: boolean; duration_min: number | null };
type Course = {
  id: string;
  title: string;
  description: string;
  level: CourseLevel;
  cover_url: string | null;
  lessons: Lesson[];
};

export default async function CourseDetail({ params }: Props) {
  const { id } = await params;
  const member = await getCurrentMember();
  const isPremium = DEMO || member?.plan === "premium";

  let course: Course | null = null;
  if (DEMO) {
    const c = demoCourses.find((x) => x.id === id);
    if (!c) notFound();
    course = {
      id: c.id,
      title: c.title,
      description: c.description,
      level: c.level,
      cover_url: c.cover_url,
      lessons: c.lessons.map((l) => ({ id: l.id, title: l.title, is_free: l.is_free, duration_min: l.duration_min })),
    };
  } else {
    const supabase = await createClient();
    const { data } = await supabase
      .from("courses")
      .select("id, title, description, level, cover_url, lessons(id, title, is_free, duration_min, sort_order)")
      .eq("id", id)
      .single();
    if (!data) notFound();
    const d = data as unknown as {
      id: string;
      title: string;
      description: string;
      level: CourseLevel;
      cover_url: string | null;
      lessons: (Lesson & { sort_order: number })[];
    };
    const sorted = [...(d.lessons ?? [])].sort((a, b) => a.sort_order - b.sort_order);
    course = {
      id: d.id,
      title: d.title,
      description: d.description,
      level: d.level,
      cover_url: d.cover_url,
      lessons: sorted.map((l) => ({
        id: l.id,
        title: l.title,
        is_free: l.is_free,
        duration_min: l.duration_min,
      })),
    };
  }
  if (!course) notFound();

  return (
    <main className="mx-auto max-w-2xl px-4 py-8">
      <Link href="/courses" className="text-sm text-ocean hover:underline">
        ← オンライン講座へ
      </Link>

      <div className="mt-4 overflow-hidden rounded-2xl border border-navy/10 bg-white shadow-sm">
        {course.cover_url ? (
          <img src={course.cover_url} alt={course.title} className="aspect-video w-full object-cover" />
        ) : null}
        <div className="p-5">
          <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${LEVEL_BADGE[course.level]}`}>
            {LEVEL_LABEL[course.level]}
          </span>
          <h1 className="mt-2 text-xl font-semibold text-navy">{course.title}</h1>
          <p className="mt-2 text-sm text-navy/70">{course.description}</p>
        </div>
      </div>

      <div className="mt-6 space-y-2">
        {course.lessons.map((l, i) => {
          const locked = !l.is_free && !isPremium;
          return (
            <Link
              key={l.id}
              href={`/lessons/${l.id}`}
              className="flex items-center justify-between rounded-xl border border-navy/10 bg-white px-4 py-3 transition hover:border-ocean/40"
            >
              <div className="flex items-center gap-3">
                <span className="text-sm font-semibold text-navy/40">{i + 1}</span>
                <div>
                  <p className="text-sm font-medium text-navy">{l.title}</p>
                  <p className="text-xs text-navy/40">
                    {l.duration_min ? `約${l.duration_min}分` : "動画＋マニュアル"}
                  </p>
                </div>
              </div>
              {l.is_free ? (
                <span className="rounded-full bg-teal/15 px-2.5 py-0.5 text-xs font-medium text-teal">無料</span>
              ) : locked ? (
                <span className="text-xs text-navy/40">🔒 プレミアム</span>
              ) : (
                <span className="text-xs text-ocean">▶ 見る</span>
              )}
            </Link>
          );
        })}
      </div>

      {!isPremium ? (
        <div className="mt-6">
          <PremiumCTA />
        </div>
      ) : null}
    </main>
  );
}
