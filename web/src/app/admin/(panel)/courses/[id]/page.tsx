import Link from "next/link";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { LEVEL_LABEL, type CourseLevel } from "@/lib/courses";
import { ConfirmSubmit } from "@/components/admin/confirm-submit";
import {
  updateCourse,
  deleteCourse,
  createLesson,
  updateLesson,
  deleteLesson,
} from "@/app/admin/actions";

export const metadata: Metadata = { title: "講座編集｜運営管理" };

type Props = { params: Promise<{ id: string }> };
type Lesson = {
  id: string;
  title: string;
  body: string | null;
  video_url: string | null;
  is_free: boolean;
  duration_min: number | null;
  sort_order: number;
};
type Course = {
  id: string;
  title: string;
  description: string | null;
  level: CourseLevel;
  cover_url: string | null;
  status: string;
  lessons: Lesson[];
};

const LEVELS: CourseLevel[] = ["prep", "beginner", "intermediate"];
const input = "w-full rounded border border-slate-300 px-3 py-2 text-sm";

export default async function AdminCourseEdit({ params }: Props) {
  const { id } = await params;
  const supabase = await createClient();
  const { data } = await supabase
    .from("courses")
    .select("id, title, description, level, cover_url, status, lessons(id, title, body, video_url, is_free, duration_min, sort_order)")
    .eq("id", id)
    .single();
  if (!data) notFound();
  const course = data as unknown as Course;
  const lessons = [...(course.lessons ?? [])].sort((a, b) => a.sort_order - b.sort_order);

  return (
    <div>
      <Link href="/admin/courses" className="text-sm text-ocean hover:underline">← 講座一覧へ</Link>
      <h1 className="mt-2 text-xl font-semibold text-slate-900">講座編集</h1>

      {/* 講座情報 */}
      <form action={updateCourse} className="mt-6 space-y-3 rounded-lg border border-slate-200 bg-white p-4">
        <input type="hidden" name="id" value={course.id} />
        <input name="title" defaultValue={course.title} className={input} />
        <textarea name="description" rows={2} defaultValue={course.description ?? ""} className={input} />
        <input name="cover_url" defaultValue={course.cover_url ?? ""} placeholder="カバー画像URL" className={input} />
        <div className="flex flex-wrap items-center gap-2">
          <select name="level" defaultValue={course.level} className="rounded border border-slate-300 px-2 py-1.5 text-sm">
            {LEVELS.map((l) => <option key={l} value={l}>{LEVEL_LABEL[l]}</option>)}
          </select>
          <select name="status" defaultValue={course.status} className="rounded border border-slate-300 px-2 py-1.5 text-sm">
            <option value="draft">下書き</option>
            <option value="active">公開中</option>
          </select>
          <button className="rounded bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white">保存</button>
          <span className="flex-1" />
        </div>
      </form>

      {/* レッスン一覧 */}
      <h2 className="mt-8 text-sm font-semibold text-slate-600">レッスン（{lessons.length}）</h2>
      <div className="mt-3 space-y-3">
        {lessons.map((l) => (
          <div key={l.id} className="space-y-2 rounded-lg border border-slate-200 bg-white p-4">
            <form action={updateLesson} className="space-y-2">
              <input type="hidden" name="id" value={l.id} />
              <input type="hidden" name="course_id" value={course.id} />
              <div className="flex gap-2">
                <input name="sort_order" defaultValue={l.sort_order} className="w-16 rounded border border-slate-300 px-2 py-2 text-sm" title="表示順" />
                <input name="title" defaultValue={l.title} className={input} />
              </div>
              <textarea name="body" rows={4} defaultValue={l.body ?? ""} placeholder="マニュアル（テキスト解説）" className={input} />
              <input name="video_url" defaultValue={l.video_url ?? ""} placeholder="動画URL（埋め込み・任意）" className={input} />
              <div className="flex flex-wrap items-center gap-3">
                <input name="duration_min" defaultValue={l.duration_min ?? ""} placeholder="分" className="w-20 rounded border border-slate-300 px-2 py-1.5 text-sm" />
                <label className="flex items-center gap-1.5 text-sm text-slate-700">
                  <input type="checkbox" name="is_free" defaultChecked={l.is_free} className="h-4 w-4" />
                  無料プレビュー
                </label>
                <button className="rounded bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white">保存</button>
              </div>
            </form>
            <form action={deleteLesson} className="border-t border-slate-100 pt-2">
              <input type="hidden" name="id" value={l.id} />
              <input type="hidden" name="course_id" value={course.id} />
              <ConfirmSubmit
                message="このレッスンを削除します。よろしいですか？"
                className="rounded bg-red-600 px-2.5 py-1 text-xs font-medium text-white"
              >
                削除
              </ConfirmSubmit>
            </form>
          </div>
        ))}
      </div>

      {/* レッスン追加 */}
      <form action={createLesson} className="mt-4 space-y-2 rounded-lg border border-dashed border-slate-300 bg-white p-4">
        <p className="text-sm font-semibold text-slate-700">＋ レッスンを追加</p>
        <input type="hidden" name="course_id" value={course.id} />
        <input name="title" placeholder="レッスンタイトル（必須）" required className={input} />
        <textarea name="body" rows={3} placeholder="マニュアル（テキスト解説）" className={input} />
        <input name="video_url" placeholder="動画URL（任意）" className={input} />
        <div className="flex flex-wrap items-center gap-3">
          <input name="sort_order" placeholder="順" className="w-16 rounded border border-slate-300 px-2 py-1.5 text-sm" />
          <input name="duration_min" placeholder="分" className="w-20 rounded border border-slate-300 px-2 py-1.5 text-sm" />
          <label className="flex items-center gap-1.5 text-sm text-slate-700">
            <input type="checkbox" name="is_free" className="h-4 w-4" />
            無料プレビュー
          </label>
          <button className="rounded bg-slate-700 px-4 py-1.5 text-sm font-medium text-white">追加</button>
        </div>
      </form>

      {/* 講座削除 */}
      <form action={deleteCourse} className="mt-8">
        <input type="hidden" name="id" value={course.id} />
        <ConfirmSubmit
          message="この講座を（レッスンごと）削除します。よろしいですか？"
          className="rounded border border-red-300 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
        >
          この講座を削除
        </ConfirmSubmit>
      </form>
    </div>
  );
}
