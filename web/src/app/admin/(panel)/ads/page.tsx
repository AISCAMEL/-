/* eslint-disable @next/next/no-img-element */
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { toggleAd, createAd, deleteAd } from "@/app/admin/actions";
import { AD_SLOTS, AD_PLACEMENT_LABEL } from "@/lib/ads";

export const metadata: Metadata = { title: "広告枠｜運営管理" };

type Row = {
  id: string;
  advertiser: string;
  image_url: string;
  href: string;
  placement: string;
  is_active: boolean;
  starts_at: string | null;
  ends_at: string | null;
  impressions: number;
  clicks: number;
};

export default async function AdminAds() {
  const supabase = await createClient();
  const { data } = await supabase
    .from("ad_banners")
    .select("id, advertiser, image_url, href, placement, is_active, starts_at, ends_at, impressions, clicks")
    .order("created_at", { ascending: false })
    .limit(100);
  const ads = (data ?? []) as unknown as Row[];

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">広告枠</h1>
      <p className="mt-1 text-sm text-slate-500">
        業者バナーの掲載を管理します。{ads.length} 件
      </p>

      {/* 新規登録 */}
      <form action={createAd} className="mt-6 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2">
        <p className="text-sm font-semibold text-slate-800 sm:col-span-2">広告を登録</p>
        <Field label="広告主名" name="advertiser" required />
        <Field label="代替テキスト（任意）" name="title" />
        <Field label="バナー画像URL（1600×680推奨／https または /ads/...）" name="image_url" required />
        <Field label="リンク先URL（https://…）" name="href" required />
        <label className="text-xs text-slate-500">
          掲載面
          <select name="placement" className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-800">
            {AD_SLOTS.map((s) => (
              <option key={s.key} value={s.key}>
                {s.label}（{s.where}）
              </option>
            ))}
            <option value="all">全体（フッター以外のすべての枠）</option>
          </select>
        </label>
        <Field label="表示順（小さいほど優先・任意）" name="sort_order" type="number" />
        <Field label="掲載開始日（任意）" name="starts_at" type="date" />
        <Field label="掲載終了日（任意）" name="ends_at" type="date" />
        <div className="sm:col-span-2">
          <button className="rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">登録して掲載</button>
        </div>
      </form>

      <div className="mt-6 space-y-3">
        {ads.length === 0 ? (
          <p className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-400">
            まだ広告がありません。バナー画像とリンクを登録すると掲載できます。
          </p>
        ) : (
          ads.map((a) => (
            <div key={a.id} className="rounded-lg border border-slate-200 bg-white p-4">
              <div className="flex items-center gap-4">
                <img
                  src={a.image_url}
                  alt={a.advertiser}
                  className="h-14 w-40 shrink-0 rounded object-cover"
                />
                <div className="min-w-0 flex-1">
                  <p className="font-medium text-slate-800">{a.advertiser}</p>
                  <p className="text-xs text-slate-400">
                    掲載：{AD_PLACEMENT_LABEL[a.placement] ?? a.placement}・クリック{a.clicks}
                    {a.starts_at || a.ends_at
                      ? `・期間 ${a.starts_at?.slice(0, 10) ?? "—"}〜${a.ends_at?.slice(0, 10) ?? "—"}`
                      : ""}
                  </p>
                </div>
                <form action={toggleAd}>
                  <input type="hidden" name="id" value={a.id} />
                  <input type="hidden" name="active" value={String(!a.is_active)} />
                  <button
                    className={`rounded px-2.5 py-1 text-xs font-medium ${
                      a.is_active
                        ? "bg-emerald-100 text-emerald-700"
                        : "bg-slate-200 text-slate-500"
                    }`}
                  >
                    {a.is_active ? "掲載中" : "停止中"}
                  </button>
                </form>
                <form action={deleteAd}>
                  <input type="hidden" name="id" value={a.id} />
                  <button className="rounded px-2 py-1 text-xs text-slate-400 hover:bg-red-50 hover:text-red-600">削除</button>
                </form>
              </div>
            </div>
          ))
        )}
      </div>

      <p className="mt-4 text-xs text-slate-400">
        ※ 掲載のない枠には「スポンサー募集中」が表示され、広告掲載のご案内（/ads）へ誘導します。
        クリック数は広告のリンクが押されるたびに記録されます（DBに 0014_ad_clicks.sql の適用が必要）。
      </p>
    </div>
  );
}

function Field({
  label,
  name,
  type = "text",
  required = false,
}: {
  label: string;
  name: string;
  type?: string;
  required?: boolean;
}) {
  return (
    <label className="text-xs text-slate-500">
      {label}
      <input
        name={name}
        type={type}
        required={required}
        className="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-800"
      />
    </label>
  );
}
