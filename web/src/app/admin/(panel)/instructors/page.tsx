import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { setInstructorRank, toggleInstructorFlag } from "@/app/admin/actions";
import type { InstructorRank } from "@/lib/instructors";

export const metadata: Metadata = { title: "講師管理｜運営管理" };

type Member = { id: string; display_name: string | null; email: string };
type Prof = {
  member_id: string;
  rank: InstructorRank;
  is_featured: boolean;
  accepting: boolean;
  rating_avg: number;
  rating_count: number;
};

const RANK_OPTIONS = [
  { v: "none", label: "（講師でない）" },
  { v: "instructor", label: "インストラクター" },
  { v: "top_amateur", label: "トップアマ" },
  { v: "pro", label: "プロ" },
];

export default async function AdminInstructors() {
  const supabase = await createClient();
  const [{ data: mData }, { data: pData }] = await Promise.all([
    supabase
      .from("members")
      .select("id, display_name, email")
      .order("created_at", { ascending: false })
      .limit(200),
    supabase
      .from("instructor_profiles")
      .select("member_id, rank, is_featured, accepting, rating_avg, rating_count"),
  ]);

  const members = (mData ?? []) as unknown as Member[];
  const profs = new Map(
    ((pData ?? []) as unknown as Prof[]).map((p) => [p.member_id, p]),
  );

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">講師管理</h1>
      <p className="mt-1 text-sm text-slate-500">
        会員を講師（プロ／トップアマ／インストラクター）に設定します。ランクの承認は運営のみ。
      </p>

      <div className="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table className="w-full min-w-[720px] text-sm">
          <thead className="border-b border-slate-200 text-left text-xs text-slate-500">
            <tr>
              <th className="px-4 py-3">会員</th>
              <th className="px-4 py-3">ランク</th>
              <th className="px-4 py-3">評価</th>
              <th className="px-4 py-3">表示</th>
              <th className="px-4 py-3">受付</th>
            </tr>
          </thead>
          <tbody>
            {members.map((m) => {
              const p = profs.get(m.id);
              return (
                <tr key={m.id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-800">{m.display_name ?? "—"}</p>
                    <p className="text-xs text-slate-400">{m.email}</p>
                  </td>
                  <td className="px-4 py-3">
                    <form action={setInstructorRank} className="flex items-center gap-1">
                      <input type="hidden" name="id" value={m.id} />
                      <select
                        name="rank"
                        defaultValue={p?.rank ?? "none"}
                        className="rounded border border-slate-300 px-2 py-1 text-xs"
                      >
                        {RANK_OPTIONS.map((o) => (
                          <option key={o.v} value={o.v}>
                            {o.label}
                          </option>
                        ))}
                      </select>
                      <button className="rounded bg-slate-200 px-2 py-1 text-xs text-slate-700 hover:bg-slate-300">
                        設定
                      </button>
                    </form>
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-500">
                    {p ? `★${p.rating_avg.toFixed(1)}（${p.rating_count}）` : "—"}
                  </td>
                  <td className="px-4 py-3">
                    {p ? (
                      <form action={toggleInstructorFlag}>
                        <input type="hidden" name="id" value={m.id} />
                        <input type="hidden" name="field" value="is_featured" />
                        <input type="hidden" name="value" value={String(!p.is_featured)} />
                        <button
                          className={`rounded px-2.5 py-1 text-xs font-medium ${
                            p.is_featured
                              ? "bg-amber-100 text-amber-700"
                              : "bg-slate-100 text-slate-500"
                          }`}
                        >
                          {p.is_featured ? "注目中" : "通常"}
                        </button>
                      </form>
                    ) : (
                      <span className="text-xs text-slate-300">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    {p ? (
                      <form action={toggleInstructorFlag}>
                        <input type="hidden" name="id" value={m.id} />
                        <input type="hidden" name="field" value="accepting" />
                        <input type="hidden" name="value" value={String(!p.accepting)} />
                        <button
                          className={`rounded px-2.5 py-1 text-xs font-medium ${
                            p.accepting
                              ? "bg-emerald-100 text-emerald-700"
                              : "bg-slate-200 text-slate-500"
                          }`}
                        >
                          {p.accepting ? "受付中" : "満員"}
                        </button>
                      </form>
                    ) : (
                      <span className="text-xs text-slate-300">—</span>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <p className="mt-4 text-xs text-slate-400">
        ※ 講師にすると本人が「マイページ → 講師プロフィール」で写真・実績・月額を編集できます。
      </p>
    </div>
  );
}
