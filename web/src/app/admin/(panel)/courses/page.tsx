import Link from "next/link";
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { LEVEL_LABEL, type CourseLevel } from "@/lib/courses";
import { createCourse } from "@/app/admin/actions";

export const metadata: Metadata = { title: "講座管理｜運営管理" };

type Row = {
  id: string;
  title: string;
  level: CourseLevel;
  status: string;
  lessons: { id: string }[];
};

const LEVELS: CourseLevel[] = ["prep", "beginner", "intermediate"];

export default async function AdminCourses() {
  const supabase = await createClient();
  const { data } = await supabase
    .from("courses")
    .select("id, title, level, status, lessons(id)")
    .order("sort_order", { ascending: true })
    .order("created_at", { ascending: false });
  const courses = (data ?? []) as unknown as Row[];

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">講座管理</h1>
      <p className="mt-1 text-sm text-slate-500">
        オンライン講座（動画＋マニュアル）を登録・編集します。{courses.length} 講座
      </p>

      {/* 新規講座 */}
      <form action={createCourse} className="mt-6 space-y-3 rounded-lg border border-slate-200 bg-white p-4">
        <p className="text-sm font-semibold text-slate-700">＋ 新しい講座</p>
        <input name="title" placeholder="講座タイトル（必須）" required className="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
        <textarea name="description" rows={2} placeholder="説明" className="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
        <div className="flex flex-wrap gap-2">
          <select name="level" className="rounded border border-slate-300 px-2 py-1.5 text-sm">
            {LEVELS.map((l) => <option key={l} value={l}>{LEVEL_LABEL[l]}</option>)}
          </select>
          <input name="cover_url" placeholder="カバー画像URL（任意）" className="min-w-0 flex-1 rounded border border-slate-300 px-3 py-1.5 text-sm" />
          <button className="rounded bg-slate-700 px-4 py-1.5 text-sm font-medium text-white">作成</button>
        </div>
        <p className="text-xs text-slate-400">作成後は「下書き」です。編集画面で公開に切り替えます。</p>
      </form>

      <div className="mt-6 space-y-2">
        {courses.map((c) => (
          <Link
            key={c.id}
            href={`/admin/courses/${c.id}`}
            className="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3 hover:border-slate-300"
          >
            <div>
              <p className="font-medium text-slate-800">{c.title}</p>
              <p className="text-xs text-slate-400">
                {LEVEL_LABEL[c.level]}・{c.lessons?.length ?? 0} レッスン
              </p>
            </div>
            <span
              className={`rounded px-2 py-0.5 text-xs ${
                c.status === "active" ? "bg-emerald-100 text-emerald-700" : "bg-slate-200 text-slate-500"
              }`}
            >
              {c.status === "active" ? "公開中" : "下書き"}
            </span>
          </Link>
        ))}
      </div>
    </div>
  );
}
