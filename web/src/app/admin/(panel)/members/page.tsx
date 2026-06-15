import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { requireStaff } from "@/lib/admin";
import { ConfirmSubmit } from "@/components/admin/confirm-submit";
import { setMemberRole, setMemberStatus } from "@/app/admin/actions";

export const metadata: Metadata = { title: "会員管理｜運営管理" };

type Row = {
  id: string;
  display_name: string | null;
  email: string;
  role: string;
  plan: string;
  status: string;
  created_at: string;
};

const ROLES = ["visitor", "beginner", "local", "staff", "admin"];

export default async function AdminMembers() {
  const me = await requireStaff();
  const supabase = await createClient();
  const { data } = await supabase
    .from("members")
    .select("id, display_name, email, role, plan, status, created_at")
    .order("created_at", { ascending: false })
    .limit(200);
  const members = (data ?? []) as unknown as Row[];

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">会員管理</h1>
      <p className="mt-1 text-sm text-slate-500">{members.length} 名</p>

      <div className="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table className="w-full min-w-[640px] text-sm">
          <thead className="border-b border-slate-200 text-left text-xs text-slate-500">
            <tr>
              <th className="px-4 py-3">会員</th>
              <th className="px-4 py-3">種別</th>
              <th className="px-4 py-3">プラン</th>
              <th className="px-4 py-3">状態</th>
              <th className="px-4 py-3">操作</th>
            </tr>
          </thead>
          <tbody>
            {members.map((m) => {
              const isSelf = m.id === me.id;
              return (
                <tr key={m.id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-800">
                      {m.display_name ?? "—"}
                    </p>
                    <p className="text-xs text-slate-400">{m.email}</p>
                  </td>
                  <td className="px-4 py-3">
                    <form action={setMemberRole} className="flex items-center gap-1">
                      <input type="hidden" name="id" value={m.id} />
                      <select
                        name="role"
                        defaultValue={m.role}
                        disabled={isSelf}
                        className="rounded border border-slate-300 px-2 py-1 text-xs disabled:opacity-50"
                      >
                        {ROLES.map((r) => (
                          <option key={r} value={r}>
                            {r}
                          </option>
                        ))}
                      </select>
                      {!isSelf ? (
                        <button className="rounded bg-slate-200 px-2 py-1 text-xs text-slate-700 hover:bg-slate-300">
                          変更
                        </button>
                      ) : null}
                    </form>
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-500">
                    {m.plan === "premium" ? "Premium" : "Free"}
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`rounded px-2 py-0.5 text-xs ${
                        m.status === "active"
                          ? "bg-emerald-100 text-emerald-700"
                          : "bg-red-100 text-red-700"
                      }`}
                    >
                      {m.status === "active" ? "有効" : "停止"}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    {isSelf ? (
                      <span className="text-xs text-slate-400">自分</span>
                    ) : m.status === "active" ? (
                      <form action={setMemberStatus}>
                        <input type="hidden" name="id" value={m.id} />
                        <input type="hidden" name="status" value="suspended" />
                        <ConfirmSubmit
                          className="rounded bg-red-600 px-2.5 py-1 text-xs font-medium text-white"
                          message="この会員を停止します。よろしいですか？"
                        >
                          停止
                        </ConfirmSubmit>
                      </form>
                    ) : (
                      <form action={setMemberStatus}>
                        <input type="hidden" name="id" value={m.id} />
                        <input type="hidden" name="status" value="active" />
                        <button className="rounded bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white">
                          有効化
                        </button>
                      </form>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <p className="mt-4 text-xs text-slate-400">
        ※ 種別を local にすると波情報の投稿が、staff/admin にすると管理画面が使えるようになります。
      </p>
    </div>
  );
}
