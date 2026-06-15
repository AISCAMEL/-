import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { resolveReport } from "@/app/admin/actions";

export const metadata: Metadata = { title: "通報・要確認｜運営管理" };

type Row = {
  id: string;
  target_type: string;
  target_id: string;
  reason: string | null;
  status: string;
  created_at: string;
};

const TARGET_LABEL: Record<string, string> = {
  post: "投稿",
  comment: "コメント",
  skill: "スキル",
};

export default async function AdminReports() {
  const supabase = await createClient();
  const { data } = await supabase
    .from("reports")
    .select("id, target_type, target_id, reason, status, created_at")
    .eq("status", "open")
    .order("created_at", { ascending: false })
    .limit(100);
  const reports = (data ?? []) as unknown as Row[];

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">通報・要確認</h1>
      <p className="mt-1 text-sm text-slate-500">未対応 {reports.length} 件</p>

      <div className="mt-6 space-y-3">
        {reports.length === 0 ? (
          <p className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-400">
            未対応の通報はありません。
          </p>
        ) : (
          reports.map((r) => (
            <div key={r.id} className="rounded-lg border border-slate-200 bg-white p-4">
              <div className="flex items-center gap-2 text-xs">
                <span className="rounded bg-slate-100 px-2 py-0.5 text-slate-600">
                  {TARGET_LABEL[r.target_type] ?? r.target_type}
                </span>
                <span className="text-slate-400">
                  {new Date(r.created_at).toLocaleString("ja-JP")}
                </span>
              </div>
              <p className="mt-2 text-sm text-slate-700">
                理由：{r.reason ?? "（記載なし）"}
              </p>
              <p className="mt-1 text-xs text-slate-400">対象ID：{r.target_id}</p>

              <div className="mt-3 flex gap-2">
                <form action={resolveReport}>
                  <input type="hidden" name="id" value={r.id} />
                  <input type="hidden" name="action" value="hide" />
                  <input type="hidden" name="target_type" value={r.target_type} />
                  <input type="hidden" name="target_id" value={r.target_id} />
                  <button className="rounded bg-red-600 px-2.5 py-1 text-xs font-medium text-white">
                    対象を非公開にして解決
                  </button>
                </form>
                <form action={resolveReport}>
                  <input type="hidden" name="id" value={r.id} />
                  <input type="hidden" name="action" value="dismiss" />
                  <input type="hidden" name="target_type" value={r.target_type} />
                  <input type="hidden" name="target_id" value={r.target_id} />
                  <button className="rounded bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-300">
                    問題なし（却下）
                  </button>
                </form>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
