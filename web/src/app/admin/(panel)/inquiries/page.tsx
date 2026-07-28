import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { markInquiry } from "@/app/admin/actions";

export const metadata: Metadata = { title: "お問い合わせ｜運営管理" };

type Row = {
  id: string;
  name: string;
  email: string;
  category: string | null;
  body: string;
  status: string;
  created_at: string;
};

export default async function AdminInquiries() {
  const supabase = await createClient();
  const { data } = await supabase
    .from("inquiries")
    .select("id, name, email, category, body, status, created_at")
    .order("created_at", { ascending: false })
    .limit(100);
  const rows = (data ?? []) as unknown as Row[];
  const open = rows.filter((r) => r.status === "open").length;

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">お問い合わせ</h1>
      <p className="mt-1 text-sm text-slate-500">未対応 {open} 件 / 全 {rows.length} 件</p>

      <div className="mt-6 space-y-3">
        {rows.length === 0 ? (
          <p className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-400">
            お問い合わせはありません。
          </p>
        ) : (
          rows.map((r) => (
            <div key={r.id} className="rounded-lg border border-slate-200 bg-white p-4">
              <div className="flex flex-wrap items-center gap-2 text-xs">
                <span
                  className={`rounded px-2 py-0.5 ${
                    r.status === "open"
                      ? "bg-amber-100 text-amber-700"
                      : "bg-slate-100 text-slate-500"
                  }`}
                >
                  {r.status === "open" ? "未対応" : "対応済み"}
                </span>
                {r.category ? (
                  <span className="rounded bg-slate-100 px-2 py-0.5 text-slate-600">
                    {r.category}
                  </span>
                ) : null}
                <span className="text-slate-400">
                  {new Date(r.created_at).toLocaleString("ja-JP")}
                </span>
              </div>
              <p className="mt-2 text-sm font-medium text-slate-800">
                {r.name}
                <a href={`mailto:${r.email}`} className="ml-2 text-xs font-normal text-ocean hover:underline">
                  {r.email}
                </a>
              </p>
              <p className="mt-1 whitespace-pre-wrap text-sm text-slate-600">{r.body}</p>
              <div className="mt-3">
                {r.status === "open" ? (
                  <form action={markInquiry}>
                    <input type="hidden" name="id" value={r.id} />
                    <input type="hidden" name="status" value="handled" />
                    <button className="rounded bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white">
                      対応済みにする
                    </button>
                  </form>
                ) : (
                  <form action={markInquiry}>
                    <input type="hidden" name="id" value={r.id} />
                    <input type="hidden" name="status" value="open" />
                    <button className="rounded bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-300">
                      未対応に戻す
                    </button>
                  </form>
                )}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
